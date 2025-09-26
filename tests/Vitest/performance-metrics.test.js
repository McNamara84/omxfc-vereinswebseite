import { describe, it, expect } from 'vitest';
import {
  summarizeNavigationPerformance,
  formatMetricsForSummary,
  formatBenchmarkTitle,
} from '../e2e/utils/performance-metrics.js';

describe('performance metrics utilities', () => {
  const baseMetrics = {
    url: 'https://example.com/',
    timestamp: Date.UTC(2024, 0, 1),
    navigation: {
      domContentLoadedEventEnd: 1234.5,
      domContentLoadedEventStart: 1200.0,
      loadEventEnd: 2345.6,
      loadEventStart: 2300.0,
      duration: 2500.1,
      requestStart: 50.0,
      responseStart: 150.0,
      startTime: 0,
      transferSize: 102400,
      encodedBodySize: 20480,
      decodedBodySize: 51200,
    },
    paint: [
      { name: 'first-paint', startTime: 90.1 },
      { name: 'first-contentful-paint', startTime: 180.2 },
    ],
    largestContentfulPaint: 1200.4,
  };

  it('summarizes navigation performance data', () => {
    const summary = summarizeNavigationPerformance(baseMetrics);

    expect(summary.url).toBe('https://example.com/');
    expect(summary.recordedAt).toBe('2024-01-01T00:00:00.000Z');
    expect(summary.metrics.totalLoadTime).toBeCloseTo(2345.6);
    expect(summary.metrics.domContentLoaded).toBeCloseTo(1234.5);
    expect(summary.metrics.timeToFirstByte).toBeCloseTo(100);
    expect(summary.metrics.firstPaint).toBeCloseTo(90.1);
    expect(summary.metrics.firstContentfulPaint).toBeCloseTo(180.2);
    expect(summary.metrics.largestContentfulPaint).toBeCloseTo(1200.4);
  });

  it('creates formatted summary table', () => {
    const summary = summarizeNavigationPerformance(baseMetrics);
    const rows = formatMetricsForSummary(summary);

    expect(rows).toEqual([
      ['Total Load', '2345.6 ms'],
      ['DOM Content Loaded', '1234.5 ms'],
      ['Time to First Byte', '100.0 ms'],
      ['First Paint', '90.1 ms'],
      ['First Contentful Paint', '180.2 ms'],
      ['Largest Contentful Paint', '1200.4 ms'],
    ]);
  });

  it('handles missing optional values gracefully', () => {
    const summary = summarizeNavigationPerformance({
      ...baseMetrics,
      navigation: null,
      paint: [],
      largestContentfulPaint: null,
    });

    expect(summary.metrics.totalLoadTime).toBeNull();
    expect(summary.metrics.timeToFirstByte).toBeNull();
    expect(summary.metrics.firstContentfulPaint).toBeNull();
    expect(summary.metrics.largestContentfulPaint).toBeNull();

    const rows = formatMetricsForSummary(summary);
    expect(rows[0][1]).toBe('n/a');
  });

  it('accepts negative requestStart values reported by some browsers', () => {
    const summary = summarizeNavigationPerformance({
      ...baseMetrics,
      navigation: {
        ...baseMetrics.navigation,
        requestStart: -1,
      },
    });

    expect(summary.metrics.timeToFirstByte).toBeCloseTo(151);
  });

  it('generates a benchmark title with rounded load time', () => {
    const summary = summarizeNavigationPerformance(baseMetrics);
    expect(formatBenchmarkTitle(summary)).toBe('Website loaded in 2346 ms');
  });

  it('falls back to n/a when load time is missing', () => {
    const summary = summarizeNavigationPerformance({
      ...baseMetrics,
      navigation: null,
    });

    expect(formatBenchmarkTitle(summary)).toBe('Website loaded in n/a ms');
  });
});
