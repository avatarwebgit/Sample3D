<!DOCTYPE html>
<html>
<head>
    <title>3D Model Viewer</title>
    <style>
        body {
            margin: 0;
            height: 400vh; /* افزایش ارتفاع برای 4 مرحله */
            background: #f0f0f0;
        }
        #canvas-container {
            position: fixed;
            width: 100vw;
            height: 100vh;
            top: 0;
            left: 0;
        }
    </style>
</head>
<body>
<div id="canvas-container"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js"></script>

<script>
    let scene, camera, renderer, model;
    const totalStages = 3; // افزایش به 4 مرحله
    let lastScrollPercent = 0;
    let maxSize = 1;

    // تنظیمات جدید برای هر مرحله
    const stageSettings = {
        1: {
            cameraPos: { x: 0, y: 10, z: 0 }, // نمای بالا
            cameraLookAt: { x: 0, y: 0, z: 0 },
            modelRotation: { x: 0, y: 0, z: 0 },
            scale: 1
        },
        2: {
            cameraPos: { x: 0, y: 0, z: 7 }, // نمای جلو
            cameraLookAt: { x: 0, y: 0, z: 0 },
            modelRotation: { x: 0, y: 0, z: 0 },
            scale: 1
        },
        // 3: {
        //     cameraPos: { x: 2, y: 0, z: 7 }, // چرخش جزئی به راست
        //     cameraLookAt: { x: 0, y: 0, z: 0 },
        //     modelRotation: { x: 0, y: -Math.PI / 6, z: 0 },
        //     scale: 1.2
        // },
        3: {
            cameraPos: { x: -6, y: 0, z: 0 }, // نمای چپ
            cameraLookAt: { x: 0, y: 0, z: 0 },
            modelRotation: { x: 0, y: Math.PI / 2, z: 0 },
            scale: 1.5
        }
    };

    function init() {
        scene = new THREE.Scene();
        scene.background = new THREE.Color(0xffffff);
        scene.fog = new THREE.Fog(0xffffff, 10, 50);


        camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 1000);
        // camera.position.set(0, 0, 7); // موقعیت اولیه دوربین
        camera.position.set(stageSettings[1].cameraPos.x, stageSettings[1].cameraPos.y, stageSettings[1].cameraPos.z);
        camera.lookAt(stageSettings[1].cameraLookAt.x, stageSettings[1].cameraLookAt.y, stageSettings[1].cameraLookAt.z);
        renderer = new THREE.WebGLRenderer({
            antialias: true,
            powerPreference: "high-performance",
        });
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setPixelRatio(window.devicePixelRatio);
        renderer.physicallyCorrectLights = true;
        renderer.outputEncoding = THREE.sRGBEncoding;
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.0;
        document.getElementById('canvas-container').appendChild(renderer.domElement);

        setupLighting();

        const loader = new THREE.GLTFLoader();
        loader.load(
            '{{asset('home/maxenTv.glb')}}',
            function(gltf) {
                model = gltf.scene;

                model.traverse((node) => {
                    if (node.isMesh) {
                        if (node.material) {
                            node.material.roughness = 0.3;
                            node.material.metalness = 0.7;
                            node.material.envMapIntensity = 1.5;
                        }
                        node.castShadow = true;
                        node.receiveShadow = true;
                    }
                });

                const box = new THREE.Box3().setFromObject(model);
                const size = box.getSize(new THREE.Vector3());
                maxSize = Math.max(size.x, size.y, size.z);
                const scale = 3 / maxSize;
                model.scale.setScalar(scale);

                const center = box.getCenter(new THREE.Vector3());
                model.position.x = -center.x * scale;
                model.position.y = -center.y * scale;
                model.position.z = -center.z * scale;

                scene.add(model);
            }
        );

        window.addEventListener('scroll', onScroll);
        window.addEventListener('resize', onWindowResize);
        animate();
    }

    function setupLighting() {
        const mainLight = new THREE.DirectionalLight(0xffffff, 1.5);
        mainLight.position.set(5, 5, 5);
        mainLight.castShadow = true;
        scene.add(mainLight);

        const fillLight = new THREE.DirectionalLight(0xffffff, 0.8);
        fillLight.position.set(-5, 0, -5);
        scene.add(fillLight);

        const topLight = new THREE.DirectionalLight(0xffffff, 0.8);
        topLight.position.set(0, 5, 0);
        scene.add(topLight);

        const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
        scene.add(ambientLight);

        const hemiLight = new THREE.HemisphereLight(0xffffff, 0x444444, 0.5);
        scene.add(hemiLight);

        const pmremGenerator = new THREE.PMREMGenerator(renderer);
        pmremGenerator.compileEquirectangularShader();
        const environmentMap = pmremGenerator.fromScene(new THREE.Scene()).texture;
        scene.environment = environmentMap;
        pmremGenerator.dispose();
    }

    function lerp(start, end, t) {
        if (typeof start === 'object') {
            return {
                x: start.x + (end.x - start.x) * t,
                y: start.y + (end.y - start.y) * t,
                z: start.z + (end.z - start.z) * t
            };
        }
        return start + (end - start) * t;
    }

    function smoothstep(x) {
        return x * x * (3 - 2 * x);
    }

    function getStageTransition(stage, progress) {
        stage = Math.min(Math.max(1, stage), totalStages);
        const current = stageSettings[stage];
        const next = stage < totalStages ? stageSettings[stage + 1] : stageSettings[totalStages];
        progress = Math.min(Math.max(0, progress), 1);

        const smoothProgress = smoothstep(progress);

        return {
            cameraPos: lerp(current.cameraPos, next.cameraPos, smoothProgress),
            cameraLookAt: lerp(current.cameraLookAt, next.cameraLookAt, smoothProgress),
            modelRotation: lerp(current.modelRotation, next.modelRotation, smoothProgress),
            scale: lerp(current.scale, next.scale, smoothProgress)
        };
    }

    function updateModel(scrollPercent) {
        if (!model) return;

        scrollPercent = Math.min(Math.max(0, scrollPercent), 1);
        const stage = Math.min(Math.floor(scrollPercent * totalStages) + 1, totalStages);
        const progress = Math.min((scrollPercent * totalStages) % 1, 1);

        const transition = getStageTransition(stage, progress);
        const smoothness = 0.1;

        // آپدیت موقعیت دوربین
        camera.position.x += (transition.cameraPos.x - camera.position.x) * smoothness;
        camera.position.y += (transition.cameraPos.y - camera.position.y) * smoothness;
        camera.position.z += (transition.cameraPos.z - camera.position.z) * smoothness;

        // آپدیت نقطه دید دوربین
        camera.lookAt(
            transition.cameraLookAt.x,
            transition.cameraLookAt.y,
            transition.cameraLookAt.z
        );

        // آپدیت چرخش مدل
        model.rotation.x += (transition.modelRotation.x - model.rotation.x) * smoothness;
        model.rotation.y += (transition.modelRotation.y - model.rotation.y) * smoothness;
        model.rotation.z += (transition.modelRotation.z - model.rotation.z) * smoothness;

        // آپدیت مقیاس
        const baseScale = 3 / maxSize;
        const targetScale = baseScale * transition.scale;
        model.scale.x += (targetScale - model.scale.x) * smoothness;
        model.scale.y += (targetScale - model.scale.y) * smoothness;
        model.scale.z += (targetScale - model.scale.z) * smoothness;
    }

    function onScroll() {
        const scrollPercent = window.scrollY / (document.documentElement.scrollHeight - window.innerHeight);
        updateModel(scrollPercent);
        lastScrollPercent = scrollPercent;
    }

    function onWindowResize() {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    }

    function animate() {
        requestAnimationFrame(animate);
        renderer.render(scene, camera);
    }

    init();
</script>
</body>
</html>
