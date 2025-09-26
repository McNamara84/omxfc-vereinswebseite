import { z } from 'zod';

const navigationSchema = z
  .object({
    domContentLoadedEventEnd: z.number().nonnegative().nullable().optional(),
    domContentLoadedEventStart: z.number().nonnegative().nullable().optional(),
    loadEventEnd: z.number().nonnegative().nullable().optional(),
    loadEventStart: z.number().nonnegative().nullable().optional(),
    duration: z.number().nonnegative().nullable().optional(),
    // Firefox may report -1 when request timing is unavailable, so we only validate that a number is provided.
    requestStart: z
      .number()
      .nullable()
      .optional(),
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

const summarySchema = z
  .object({
    metrics: z
      .object({
        totalLoadTime: z.number().nonnegative().nullable(),
        domContentLoaded: z.number().nonnegative().nullable().optional(),
        timeToFirstByte: z.number().nonnegative().nullable().optional(),
      })
      .passthrough(),
    raw: z
      .object({
        navigation: navigationSchema,
      })
      .partial()
      .passthrough()
      .optional(),
    runs: z.array(z.any()).optional(),
  })
  .passthrough();

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

export function formatBenchmarkTitle(summary) {
  const { metrics } = summarySchema.parse(summary);
  const totalLoadTime = metrics.totalLoadTime;
  const formattedValue =
    typeof totalLoadTime === 'number' ? `${Math.round(totalLoadTime)} ms` : 'n/a ms';

  return `Benchmark: Homepage loaded in ${formattedValue}`;
}

function toFiniteOrNull(value) {
  return typeof value === 'number' && Number.isFinite(value) ? value : null;
}

export function extractBenchmarkOutputs(summary) {
  const parsed = summarySchema.parse(summary);
  const navigation = parsed.raw?.navigation ?? null;
  const metrics = parsed.metrics ?? {};

  const runs = Array.isArray(parsed.runs) ? parsed.runs : [];
  const runLoadTimes = runs
    .map((run) => {
      if (!run || typeof run !== 'object') {
        return null;
      }

      if ('totalLoadTimeMs' in run) {
        return toFiniteOrNull(run.totalLoadTimeMs);
      }

      if ('totalLoadTime' in run) {
        return toFiniteOrNull(run.totalLoadTime);
      }

      if ('metrics' in run && run.metrics && typeof run.metrics === 'object') {
        return toFiniteOrNull(run.metrics.totalLoadTime);
      }

      return null;
    })
    .filter((value) => value !== null);

  const loadTime = toFiniteOrNull(metrics.totalLoadTime ?? null);
  const navigationDuration = toFiniteOrNull(navigation?.duration ?? null);
  const domContentLoaded = toFiniteOrNull(
    metrics.domContentLoaded ?? navigation?.domContentLoadedEventEnd ?? null,
  );

  return {
    loadTime,
    navigationDuration,
    domContentLoaded,
    runLoadTimes,
  };
}
