export async function gotoMitgliedWerden(page) {
  await page.goto('/mitglied-werden');
  await page.waitForLoadState('networkidle');
  await page.waitForFunction(() => typeof window.Livewire !== 'undefined');
}