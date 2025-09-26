import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import {
  summarizeNavigationPerformance,
  formatBenchmarkTitle,
} from './utils/performance-metrics.js';

const OUTPUT_FILE = 'homepage.json';

function resolveOutputPath(testInfo) {
  const customDir = process.env.PERFORMANCE_RESULTS_DIR;
  const targetDir = customDir ? path.resolve(customDir) : path.resolve(testInfo.project.outputDir, '..', 'performance-results');
  return path.join(targetDir, OUTPUT_FILE);
}

test.describe('Homepage performance benchmark', () => {
  test('collects navigation timing metrics', async ({ page }, testInfo) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    const rawMetrics = await page.evaluate(() => {
      const [navigationEntry] = performance.getEntriesByType('navigation');
      const paintEntries = performance.getEntriesByType('paint');
      const lcpEntries = performance.getEntriesByType('largest-contentful-paint');

      return {
        url: document.location.href,
        timestamp: Date.now(),
        navigation: navigationEntry
          ? {
              domContentLoadedEventEnd: navigationEntry.domContentLoadedEventEnd,
              domContentLoadedEventStart: navigationEntry.domContentLoadedEventStart,
              loadEventEnd: navigationEntry.loadEventEnd,
              loadEventStart: navigationEntry.loadEventStart,
              duration: navigationEntry.duration,
              requestStart: navigationEntry.requestStart,
              responseStart: navigationEntry.responseStart,
              startTime: navigationEntry.startTime,
              transferSize: navigationEntry.transferSize,
              encodedBodySize: navigationEntry.encodedBodySize,
              decodedBodySize: navigationEntry.decodedBodySize,
            }
          : null,
        paint: paintEntries.map((entry) => ({
          name: entry.name,
          startTime: entry.startTime,
        })),
        largestContentfulPaint: lcpEntries.length ? lcpEntries[lcpEntries.length - 1].startTime : null,
      };
    });

    const summary = summarizeNavigationPerformance(rawMetrics);
    const outputPath = resolveOutputPath(testInfo);

    fs.mkdirSync(path.dirname(outputPath), { recursive: true });
    fs.writeFileSync(outputPath, JSON.stringify(summary, null, 2));
    await testInfo.attach('homepage-performance.json', {
      body: JSON.stringify(summary, null, 2),
      contentType: 'application/json',
    });

    expect(summary.metrics.totalLoadTime).toBeGreaterThan(0);

    if (summary.metrics.timeToFirstByte !== null) {
      expect(summary.metrics.timeToFirstByte).toBeGreaterThanOrEqual(0);
    }

    if (summary.metrics.firstContentfulPaint !== null) {
      expect(summary.metrics.firstContentfulPaint).toBeGreaterThanOrEqual(0);
    }

    console.log(formatBenchmarkTitle(summary));
    console.log(`Homepage LCP: ${summary.metrics.largestContentfulPaint?.toFixed(1) ?? 'n/a'} ms`);
  });
});
