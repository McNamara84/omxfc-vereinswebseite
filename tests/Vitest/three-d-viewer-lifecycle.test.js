import { beforeEach, afterEach, expect, it, vi } from 'vitest';
const mocks = vi.hoisted(() => ({ loaded: null, progress: null, failed: null, renderer: null, observer: null, materials: [] }));
vi.mock('three', () => {
    class Object3D {
        children = []; position = { set: vi.fn() };
        add(child) { this.children.push(child); }
        traverse(callback) { callback(this); this.children.forEach(child => child.traverse(callback)); }
    }
    class Material { constructor() { this.dispose = vi.fn(); mocks.materials.push(this); } }
    return {
        Scene: Object3D, Color: class {}, AmbientLight: Object3D, DirectionalLight: Object3D,
        PerspectiveCamera: class extends Object3D { lookAt = vi.fn(); updateProjectionMatrix = vi.fn(); },
        WebGLRenderer: class { constructor() { this.domElement = document.createElement('canvas'); this.setSize = vi.fn(); this.setPixelRatio = vi.fn(); this.render = vi.fn(); this.dispose = vi.fn(); mocks.renderer = this; } },
        MeshStandardMaterial: Material,
        Mesh: class extends Object3D { constructor(geometry, material) { super(); this.isMesh = true; this.geometry = geometry; this.material = material; } },
        Vector3: class { x = 1; y = 1; z = 1; },
        Box3: class { setFromObject() { return this; } getCenter(vector) { return vector; } getSize(vector) { return vector; } },
    };
});
vi.mock('three/addons/controls/OrbitControls.js', () => ({ OrbitControls: class { target = { copy: vi.fn() }; update = vi.fn(); dispose = vi.fn(); } }));
function loader() { return class { load(url, loaded, progress, failed) { mocks.loaded = loaded; mocks.progress = progress; mocks.failed = failed; } }; }
vi.mock('three/addons/loaders/STLLoader.js', () => ({ STLLoader: loader() }));
vi.mock('three/addons/loaders/OBJLoader.js', () => ({ OBJLoader: loader() }));
vi.mock('three/addons/loaders/FBXLoader.js', () => ({ FBXLoader: loader() }));
import { initThreeDViewer, initThreeDViewers, cleanupAllViewers } from '../../resources/js/three-d-viewer';
beforeEach(() => {
    vi.stubGlobal('requestAnimationFrame', vi.fn(() => 1));
    vi.stubGlobal('cancelAnimationFrame', vi.fn());
    vi.stubGlobal('ResizeObserver', class { constructor(callback) { this.callback = callback; this.observe = vi.fn(); this.disconnect = vi.fn(); mocks.observer = this; } });
    vi.spyOn(console, 'error').mockImplementation(() => {});
    document.body.innerHTML = '<div data-three-d-viewer data-file-url="/model.stl" data-format="stl"></div>';
    mocks.materials = [];
});
afterEach(() => { cleanupAllViewers(); vi.unstubAllGlobals(); vi.restoreAllMocks(); });

it('disposes a model arriving after navigation and makes cleanup idempotent', () => {
    const root = document.body.firstElementChild;
    const cleanup = initThreeDViewer(root, '/model.stl', 'stl');
    cleanup(); cleanup();
    const geometry = { dispose: vi.fn() };
    mocks.loaded(geometry);
    mocks.progress({ lengthComputable: true, loaded: 1, total: 2 });
    mocks.failed(new Error('late'));
    expect(geometry.dispose).toHaveBeenCalledTimes(1);
    expect(mocks.renderer.dispose).toHaveBeenCalledTimes(1);
    expect(mocks.observer.disconnect).toHaveBeenCalledTimes(1);
    expect(root.children).toHaveLength(0);
    expect(console.error).not.toHaveBeenCalled();
});

it('renders, resizes, reports loading errors and cleans a mounted viewer', () => {
    const root = document.body.firstElementChild;
    Object.defineProperties(root, { clientWidth: { value: 300 }, clientHeight: { value: 200 } });
    initThreeDViewers(); initThreeDViewers();
    expect(root.querySelectorAll('canvas')).toHaveLength(1);
    mocks.progress({ lengthComputable: true, loaded: 1, total: 2 });
    expect(root.textContent).toContain('50%');
    mocks.progress({ lengthComputable: false });
    mocks.observer.callback();
    expect(mocks.renderer.setSize).toHaveBeenCalledWith(300, 200);
    mocks.failed(new Error('failed'));
    expect(root.textContent).toContain('Fehler');
    mocks.loaded({ dispose: vi.fn() });
    cleanupAllViewers();
    expect(root.dataset.threeDInitialized).toBeUndefined();
    expect(root.children).toHaveLength(0);
});

it('disposes object materials and textures, and rejects unknown formats before creating resources', () => {
    const root = document.body.firstElementChild;
    const cleanup = initThreeDViewer(root, '/model.obj', 'obj');
    const material = { dispose: vi.fn(), texture: { dispose: vi.fn() } };
    const geometry = { dispose: vi.fn() };
    mocks.loaded({ traverse: callback => { callback({ isMesh: true, geometry, material: [material] }); callback({ isMesh: true }); } });
    cleanup();
    expect(material.dispose).toHaveBeenCalledTimes(1);
    expect(material.texture.dispose).toHaveBeenCalledTimes(1);
    expect(geometry.dispose).toHaveBeenCalledTimes(1);
    initThreeDViewer(root, '/unknown', 'unknown')();
    expect(root.querySelectorAll('canvas')).toHaveLength(0);
});
