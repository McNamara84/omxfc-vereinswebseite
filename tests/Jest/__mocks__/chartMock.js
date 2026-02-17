import { jest } from '@jest/globals';
export const mockChart = jest.fn();
mockChart.getChart = jest.fn(() => null);
export default mockChart;
