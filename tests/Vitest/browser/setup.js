import { afterEach, vi } from 'vitest';
import Alpine from 'alpinejs';

afterEach(() => {
    for (const root of document.querySelectorAll('[x-data]')) Alpine.destroyTree(root);
    Alpine.stopObservingMutations();
    document.body.replaceChildren();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});
