import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { STLLoader } from 'three/addons/loaders/STLLoader.js';
import { OBJLoader } from 'three/addons/loaders/OBJLoader.js';
import { FBXLoader } from 'three/addons/loaders/FBXLoader.js';

/**
 * Initialisiert den 3D-Viewer in einem Container-Element.
 *
 * @param {HTMLElement} container - Das DOM-Element für den Viewer
 * @param {string} fileUrl - URL zur 3D-Datei (über Controller-Route)
 * @param {string} format - Dateiformat ('stl', 'obj', 'fbx')
 * @returns {Function} Cleanup-Funktion
 */
export function initThreeDViewer(container, fileUrl, format) {
    // Scene Setup
    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0xe0e0e0);

    // Camera
    const camera = new THREE.PerspectiveCamera(
        60,
        container.clientWidth / container.clientHeight,
        0.1,
        10000
    );

    // Renderer
    const renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(container.clientWidth, container.clientHeight);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    container.appendChild(renderer.domElement);

    // Lighting
    const ambientLight = new THREE.AmbientLight(0x404040, 2);
    scene.add(ambientLight);

    const directionalLight1 = new THREE.DirectionalLight(0xffffff, 2);
    directionalLight1.position.set(1, 1, 1);
    scene.add(directionalLight1);

    const directionalLight2 = new THREE.DirectionalLight(0xffffff, 1);
    directionalLight2.position.set(-1, -1, -1);
    scene.add(directionalLight2);

    // Controls
    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.05;

    // Loader basierend auf Format wählen
    const loaderMap = {
        stl: () => new STLLoader(),
        obj: () => new OBJLoader(),
        fbx: () => new FBXLoader(),
    };

    const createLoader = loaderMap[format];
    if (!createLoader) {
        console.error(`Unbekanntes 3D-Format: ${format}`);
        return () => {};
    }

    const loader = createLoader();

    // Loading Indicator
    const loadingEl = document.createElement('div');
    loadingEl.className = 'absolute inset-0 flex items-center justify-center bg-base-300/80 z-10';
    loadingEl.innerHTML = '<span class="loading loading-spinner loading-lg"></span>';
    container.style.position = 'relative';
    container.appendChild(loadingEl);

    // Standardmaterial im OMXFC-Rot
    const defaultMaterial = new THREE.MeshStandardMaterial({
        color: 0x8b0116,
        metalness: 0.3,
        roughness: 0.6,
    });

    loader.load(
        fileUrl,
        (result) => {
            let object;

            if (format === 'stl') {
                // STLLoader gibt BufferGeometry zurück
                object = new THREE.Mesh(result, defaultMaterial);
            } else {
                // OBJ/FBX geben Group/Object3D zurück
                object = result;
                // Fallback-Material wenn keins vorhanden
                object.traverse((child) => {
                    if (child.isMesh && !child.material) {
                        child.material = defaultMaterial;
                    }
                });
            }

            scene.add(object);

            // Kamera auf Modell ausrichten (Auto-Fit)
            const box = new THREE.Box3().setFromObject(object);
            const center = box.getCenter(new THREE.Vector3());
            const size = box.getSize(new THREE.Vector3());
            const maxDim = Math.max(size.x, size.y, size.z);
            const distance = maxDim * 2;

            camera.position.set(
                center.x + distance * 0.5,
                center.y + distance * 0.5,
                center.z + distance
            );
            camera.lookAt(center);
            controls.target.copy(center);
            controls.update();

            // Loading Indicator entfernen
            loadingEl.remove();
        },
        // Progress Callback
        (xhr) => {
            if (xhr.lengthComputable) {
                const percent = Math.round((xhr.loaded / xhr.total) * 100);
                loadingEl.innerHTML = `<div class="text-center">
                    <span class="loading loading-spinner loading-lg"></span>
                    <p class="mt-2">${percent}%</p>
                </div>`;
            }
        },
        // Error Callback
        (error) => {
            loadingEl.innerHTML = `<div class="alert alert-error">
                <span>Fehler beim Laden des 3D-Modells.</span>
            </div>`;
            console.error('3D-Modell konnte nicht geladen werden:', error);
        }
    );

    // Animation Loop
    let animationFrameId;
    function animate() {
        animationFrameId = requestAnimationFrame(animate);
        controls.update();
        renderer.render(scene, camera);
    }
    animate();

    // Responsive: Resize Handler
    const resizeObserver = new ResizeObserver(() => {
        const width = container.clientWidth;
        const height = container.clientHeight;
        if (width > 0 && height > 0) {
            camera.aspect = width / height;
            camera.updateProjectionMatrix();
            renderer.setSize(width, height);
        }
    });
    resizeObserver.observe(container);

    // Cleanup-Funktion zurückgeben
    return () => {
        resizeObserver.disconnect();
        cancelAnimationFrame(animationFrameId);

        // Geometrien, Materialien und Texturen traversieren und disposen
        scene.traverse((object) => {
            if (object.isMesh) {
                if (object.geometry) {
                    object.geometry.dispose();
                }
                if (object.material) {
                    const materials = Array.isArray(object.material)
                        ? object.material
                        : [object.material];
                    materials.forEach((material) => {
                        Object.values(material).forEach((value) => {
                            if (value && typeof value.dispose === 'function') {
                                value.dispose();
                            }
                        });
                        material.dispose();
                    });
                }
            }
        });

        renderer.domElement.remove();
        renderer.dispose();
        controls.dispose();
    };
}

// Aktive Cleanup-Funktionen pro Container speichern
const activeCleanups = new Map();

// Auto-Init: Alle Viewer-Container auf der Seite initialisieren
export function initThreeDViewers() {
    document.querySelectorAll('[data-three-d-viewer]').forEach((container) => {
        // Doppelte Initialisierung verhindern
        if (container.dataset.threeDInitialized) {
            return;
        }
        container.dataset.threeDInitialized = 'true';

        const fileUrl = container.dataset.fileUrl;
        const format = container.dataset.format;
        if (fileUrl && format) {
            const cleanup = initThreeDViewer(container, fileUrl, format);
            activeCleanups.set(container, cleanup);
        }
    });
}

// Cleanup bei SPA-Navigation: WebGL-Context, Animation-Loop und Observer freigeben
function cleanupAllViewers() {
    activeCleanups.forEach((cleanup, container) => {
        cleanup();
        delete container.dataset.threeDInitialized;
    });
    activeCleanups.clear();
}

// Cleanup bei SPA-Navigation registrieren (Init wird von app.js gesteuert)
document.addEventListener('livewire:navigating', cleanupAllViewers);
