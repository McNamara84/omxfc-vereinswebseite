import {
  createDatetimeLocalRange,
  formatDatetimeLocalFromInstant,
  formatEpochMillisecondsAsIsoInstant,
} from '../e2e/utils/temporal.js';

describe('temporal utils', () => {
  it('formatiert Epoch-Millis als ISO-Instant mit Millisekunden', () => {
    expect(formatEpochMillisecondsAsIsoInstant(Date.UTC(2024, 0, 1))).toBe('2024-01-01T00:00:00.000Z');
  });

  it('formatiert datetime-local-Werte minuten-genau in UTC', () => {
    const instant = Temporal.Instant.from('2026-05-24T13:47:53.120Z');

    expect(formatDatetimeLocalFromInstant(instant)).toBe('2026-05-24T13:47');
  });

  it('bildet Start- und Endwert aus demselben Basis-Instant', () => {
    const range = createDatetimeLocalRange({
      now: Temporal.Instant.from('2026-03-25T22:15:00Z'),
      durationDays: 7,
    });

    expect(range).toEqual({
      start: '2026-03-25T22:15',
      end: '2026-04-01T22:15',
    });
  });

  it('haelt die gleiche Wandzeit ueber DST-Grenzen in Europe/Berlin stabil', () => {
    const range = createDatetimeLocalRange({
      now: Temporal.Instant.from('2026-03-28T09:30:00Z'),
      timeZone: 'Europe/Berlin',
      durationDays: 2,
    });

    expect(range).toEqual({
      start: '2026-03-28T10:30',
      end: '2026-03-30T10:30',
    });
  });
});