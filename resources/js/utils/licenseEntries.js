export function canAdd(licenseEntries = []) {
  if (!Array.isArray(licenseEntries) || licenseEntries.length === 0) {
    return true;
  }
  const lastEntry = licenseEntries[licenseEntries.length - 1];
  return !!(lastEntry && lastEntry.end);
}
