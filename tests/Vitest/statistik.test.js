import { describe, it, expect, vi } from 'vitest';

vi.mock('chart.js/auto', () => ({
  default: vi.fn(),
}));

import { drawAuthorChart } from '@/js/statistik.js';
import Chart from 'chart.js/auto';

describe('drawAuthorChart', () => {
  it('skips chart creation if canvas is missing', () => {
    drawAuthorChart('missing', ['A'], [1]);
    expect(Chart).not.toHaveBeenCalled();
  });

  it('creates chart when canvas exists', () => {
    const canvas = document.createElement('canvas');
    canvas.id = 'chart';
    HTMLCanvasElement.prototype.getContext = vi.fn();
    document.body.appendChild(canvas);
    drawAuthorChart('chart', ['A'], [1]);
    expect(Chart).toHaveBeenCalledTimes(1);
  });
});
