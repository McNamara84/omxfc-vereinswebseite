import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import {
  summarizeNavigationPerformance,
  formatBenchmarkTitle,
  combineBenchmarkRuns,
} from './utils/performance-metrics.js';

const OUTPUT_FILE = 'homepage.json';

function resolveOutputPath(testInfo) {
  const customDir = process.env.PERFORMANCE_RESULTS_DIR;
  const targetDir = customDir ? path.resolve(customDir) : path.resolve(testInfo.project.outputDir, '..', 'performance-results');
  return path.join(targetDir, OUTPUT_FILE);
}

test.describe('Homepage performance benchmark', () => {
  test('collects navigation timing metrics', async ({ page }, testInfo) => {
    const runSummaries = [];
    const runs = Number.parseInt(process.env.PERFORMANCE_BENCHMARK_RUNS ?? '3', 10) || 3;

    for (let index = 0; index < runs; index += 1) {
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
      runSummaries.push(summary);

      const runNumber = index + 1;
      const runLoad = summary.metrics.totalLoadTime?.toFixed(1) ?? 'n/a';
      console.log(`Run #${runNumber} total load: ${runLoad} ms`);
    }

    const combinedSummary = combineBenchmarkRuns(runSummaries);
    const outputPath = resolveOutputPath(testInfo);

    fs.mkdirSync(path.dirname(outputPath), { recursive: true });
    fs.writeFileSync(outputPath, JSON.stringify(combinedSummary, null, 2));
    await testInfo.attach('homepage-performance.json', {
      body: JSON.stringify(combinedSummary, null, 2),
      contentType: 'application/json',
    });
    await testInfo.attach('homepage-performance-runs.json', {
      body: JSON.stringify(runSummaries, null, 2),
      contentType: 'application/json',
    });

    expect(combinedSummary.metrics.totalLoadTime).toBeGreaterThan(0);

    if (combinedSummary.metrics.timeToFirstByte !== null) {
      expect(combinedSummary.metrics.timeToFirstByte).toBeGreaterThanOrEqual(0);
    }

    if (combinedSummary.metrics.firstContentfulPaint !== null) {
      expect(combinedSummary.metrics.firstContentfulPaint).toBeGreaterThanOrEqual(0);
    }

    console.log(formatBenchmarkTitle(combinedSummary));
    console.log(`Homepage LCP: ${combinedSummary.metrics.largestContentfulPaint?.toFixed(1) ?? 'n/a'} ms`);
  });
});
