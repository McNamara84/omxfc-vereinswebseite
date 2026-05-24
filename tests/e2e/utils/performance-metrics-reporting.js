function toFiniteOrNull(value) {
  return typeof value === 'number' && Number.isFinite(value) ? value : null;
}

function getMetrics(summary) {
  const candidate = summary?.metrics;

  return candidate && typeof candidate === 'object' ? candidate : {};
}

function getNavigation(summary) {
  const candidate = summary?.raw?.navigation;

  return candidate && typeof candidate === 'object' ? candidate : null;
}

export function formatMetricsForSummary(summary) {
  const metrics = getMetrics(summary);
  const format = (value) => (typeof value === 'number' && Number.isFinite(value) ? `${value.toFixed(1)} ms` : 'n/a');

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
  const totalLoadTime = toFiniteOrNull(getMetrics(summary).totalLoadTime ?? null);
  const formattedValue = totalLoadTime !== null ? `${Math.round(totalLoadTime)} ms` : 'n/a ms';

  return `Benchmark: Homepage loaded in ${formattedValue}`;
}

export function extractBenchmarkOutputs(summary) {
  const navigation = getNavigation(summary);
  const metrics = getMetrics(summary);
  const runs = Array.isArray(summary?.runs) ? summary.runs : [];

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

  return {
    loadTime: toFiniteOrNull(metrics.totalLoadTime ?? null),
    navigationDuration: toFiniteOrNull(navigation?.duration ?? null),
    domContentLoaded: toFiniteOrNull(metrics.domContentLoaded ?? navigation?.domContentLoadedEventEnd ?? null),
    runLoadTimes,
  };
}