const DEFAULT_TIME_ZONE = 'UTC';

function toInstant(value) {
  if (value instanceof Temporal.Instant) {
    return value;
  }

  if (typeof value === 'number') {
    return Temporal.Instant.fromEpochMilliseconds(value);
  }

  return Temporal.Instant.from(value);
}

export function formatEpochMillisecondsAsIsoInstant(value) {
  return toInstant(value).toString({ smallestUnit: 'millisecond' });
}

export function formatDatetimeLocalFromInstant(value, {
  timeZone = DEFAULT_TIME_ZONE,
  days = 0,
} = {}) {
  const zonedDateTime = toInstant(value)
    .toZonedDateTimeISO(timeZone)
    .add({ days })
    .with({ second: 0, millisecond: 0, microsecond: 0, nanosecond: 0 });

  return zonedDateTime.toPlainDateTime().toString({ smallestUnit: 'minute' });
}

export function createDatetimeLocalRange({
  now = Temporal.Now.instant(),
  timeZone = DEFAULT_TIME_ZONE,
  durationDays = 7,
} = {}) {
  return {
    start: formatDatetimeLocalFromInstant(now, { timeZone }),
    end: formatDatetimeLocalFromInstant(now, { timeZone, days: durationDays }),
  };
}