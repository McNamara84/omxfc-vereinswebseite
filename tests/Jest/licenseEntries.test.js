import { canAdd } from '../../resources/js/utils/licenseEntries.js';

describe('canAdd', () => {
  test('returns true for empty list', () => {
    expect(canAdd([])).toBe(true);
  });

  test('returns false when last entry lacks end', () => {
    expect(canAdd([{ id: 1 }])).toBe(false);
  });

  test('returns true when last entry has end', () => {
    expect(canAdd([{ end: '2025-01-01' }])).toBe(true);
  });
});
