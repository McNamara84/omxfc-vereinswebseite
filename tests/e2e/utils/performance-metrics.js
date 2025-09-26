import { z } from 'zod';

const navigationSchema = z
  .object({
    domContentLoadedEventEnd: z.number().nonnegative().nullable().optional(),
    domContentLoadedEventStart: z.number().nonnegative().nullable().optional(),
    loadEventEnd: z.number().nonnegative().nullable().optional(),
    loadEventStart: z.number().nonnegative().nullable().optional(),
    duration: z.number().nonnegative().nullable().optional(),
    requestStart: z.number().nonnegative().nullable().optional(),
    responseStart: z.number().nonnegative().nullable().optional(),
    startTime: z.number().nonnegative().nullable().optional(),
    transferSize: z.number().nonnegative().nullable().optional(),
    encodedBodySize: z.number().nonnegative().nullable().optional(),
    decodedBodySize: z.number().nonnegative().nullable().optional(),
  })
  .nullable()
  .optional();

const paintEntrySchema = z.object({
  name: z.string(),
  startTime: z.number().nonnegative(),
});

const rawMetricsSchema = z.object({
  url: z.string().url(),
  timestamp: z.number(),
  navigation: navigationSchema,
  paint: z.array(paintEntrySchema),
  largestContentfulPaint: z.number().nonnegative().nullable(),
});

/**
 * Summarizes navigation timing metrics collected from the browser.
 *
 * @param {import('zod').infer<typeof rawMetricsSchema>} rawMetrics
 */
export function summarizeNavigationPerformance(rawMetrics) {
  const { url, timestamp, navigation, paint, largestContentfulPaint } = rawMetricsSchema.parse(rawMetrics);

  const firstContentfulPaint = paint.find((entry) => entry.name === 'first-contentful-paint');
  const firstPaint = paint.find((entry) => entry.name === 'first-paint');

  const totalLoadTime = navigation?.loadEventEnd ?? navigation?.duration ?? null;
  const domContentLoaded = navigation?.domContentLoadedEventEnd ?? null;
  const timeToFirstByte = navigation && navigation.responseStart != null && navigation.requestStart != null
    ? Math.max(0, navigation.responseStart - navigation.requestStart)
    : null;

  return {
    url,
    recordedAt: new Date(timestamp).toISOString(),
    metrics: {
      totalLoadTime,
      domContentLoaded,
      timeToFirstByte,
      firstContentfulPaint: firstContentfulPaint?.startTime ?? null,
      firstPaint: firstPaint?.startTime ?? null,
      largestContentfulPaint,
      transferSize: navigation?.transferSize ?? null,
      encodedBodySize: navigation?.encodedBodySize ?? null,
      decodedBodySize: navigation?.decodedBodySize ?? null,
    },
    raw: {
      navigation,
      paint,
      largestContentfulPaint,
    },
  };
}

export function formatMetricsForSummary(summary) {
  const metrics = summary.metrics;
  const format = (value) => (typeof value === 'number' ? `${value.toFixed(1)} ms` : 'n/a');

  return [
    ['Total Load', format(metrics.totalLoadTime)],
    ['DOM Content Loaded', format(metrics.domContentLoaded)],
    ['Time to First Byte', format(metrics.timeToFirstByte)],
    ['First Paint', format(metrics.firstPaint)],
    ['First Contentful Paint', format(metrics.firstContentfulPaint)],
    ['Largest Contentful Paint', format(metrics.largestContentfulPaint)],
  ];
}
