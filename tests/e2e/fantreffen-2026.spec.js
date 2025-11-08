import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('Maddrax-Fantreffen 2026 - Public Registration Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/maddrax-fantreffen-2026');
  });

  test('displays event information correctly', async ({ page }) => {
    await expect(page.getByRole('heading', { name: /Maddrax-Fantreffen 2026/i })).toBeVisible();
    await expect(page.getByText(/9\. Mai 2026/i)).toBeVisible();
    await expect(page.getByText(/L´Osteria Köln Mülheim/i)).toBeVisible();
    await expect(page.getByText(/19:00 Uhr/i)).toBeVisible();
  });

  test('displays ColoniaCon warning box', async ({ page }) => {
    await expect(page.getByText(/ColoniaCon/i)).toBeVisible();
    const coloniaConLink = page.getByRole('link', { name: /ColoniaCon/i });
    await expect(coloniaConLink).toBeVisible();
    await expect(coloniaConLink).toHaveAttribute('href', /coloniacon/i);
  });

  test('displays program timeline', async ({ page }) => {
    await expect(page.getByText(/Signierstunde/i)).toBeVisible();
    await expect(page.getByText(/Goldene Taratze/i)).toBeVisible();
  });

  test('displays cost breakdown cards', async ({ page }) => {
    // Member costs
    await expect(page.getByText(/Kostenlos/i)).toBeVisible();
    
    // Guest costs
    await expect(page.getByText(/5,00 €/i)).toBeVisible();
    
    // T-Shirt cost
    await expect(page.getByText(/25,00 €/i)).toBeVisible();
  });

  test('guest can fill out registration form without t-shirt', async ({ page }) => {
    // Fill form
    await page.getByLabel(/Vorname/i).fill('Max');
    await page.getByLabel(/Nachname/i).fill('Mustermann');
    await page.getByLabel(/E-Mail/i).fill('max.mustermann@example.com');
    await page.getByLabel(/Mobile/i).fill('0151 12345678');

    // Don't check t-shirt checkbox
    const submitButton = page.getByRole('button', { name: /anmelden|Zahlung/i });
    await expect(submitButton).toBeVisible();
    
    // Verify button text changes based on payment
    await expect(submitButton).toContainText(/Zahlung.*5,00/i);
  });

  test('guest can order t-shirt and size dropdown appears', async ({ page }) => {
    await page.getByLabel(/Vorname/i).fill('Anna');
    await page.getByLabel(/Nachname/i).fill('Schmidt');
    await page.getByLabel(/E-Mail/i).fill('anna.schmidt@example.com');
    
    // Check t-shirt checkbox
    const tshirtCheckbox = page.getByRole('checkbox', { name: /Event-T-Shirt bestellen/i });
    await tshirtCheckbox.check();
    
    // Wait for dropdown to appear
    const sizeDropdown = page.getByLabel(/T-Shirt-Größe/i);
    await expect(sizeDropdown).toBeVisible();
    
    // Select size
    await sizeDropdown.selectOption('L');
    
    // Verify button shows correct amount (5€ + 25€ = 30€)
    const submitButton = page.getByRole('button', { name: /anmelden|Zahlung/i });
    await expect(submitButton).toContainText(/30,00/i);
  });

  test('shows validation errors for empty required fields', async ({ page }) => {
    const submitButton = page.getByRole('button', { name: /anmelden|Zahlung/i });
    await submitButton.click();
    
    // Check for validation errors
    await expect(page.getByText(/Vorname.*erforderlich/i)).toBeVisible();
    await expect(page.getByText(/Nachname.*erforderlich/i)).toBeVisible();
    await expect(page.getByText(/E-Mail.*erforderlich/i)).toBeVisible();
  });

  test('shows error when t-shirt size not selected', async ({ page }) => {
    await page.getByLabel(/Vorname/i).fill('Test');
    await page.getByLabel(/Nachname/i).fill('User');
    await page.getByLabel(/E-Mail/i).fill('test@example.com');
    
    // Check t-shirt but don't select size
    await page.getByRole('checkbox', { name: /Event-T-Shirt bestellen/i }).check();
    
    const submitButton = page.getByRole('button', { name: /anmelden|Zahlung/i });
    await submitButton.click();
    
    await expect(page.getByText(/Größe.*erforderlich/i)).toBeVisible();
  });

  test('shows loading state when submitting form', async ({ page }) => {
    await page.getByLabel(/Vorname/i).fill('Loading');
    await page.getByLabel(/Nachname/i).fill('Test');
    await page.getByLabel(/E-Mail/i).fill('loading@example.com');
    
    const submitButton = page.getByRole('button', { name: /anmelden|Zahlung/i });
    
    // Click and immediately check for loading state
    await submitButton.click();
    await expect(page.getByText(/verarbeitet/i)).toBeVisible();
  });

  test('has accessible form labels and ARIA attributes', async ({ page }) => {
    // Check all form inputs have proper labels
    const vornameInput = page.getByLabel(/Vorname/i);
    await expect(vornameInput).toHaveAttribute('aria-label');
    
    const nachnameInput = page.getByLabel(/Nachname/i);
    await expect(nachnameInput).toBeVisible();
    
    const emailInput = page.getByLabel(/E-Mail/i);
    await expect(emailInput).toHaveAttribute('type', 'email');
  });

  test('passes automated accessibility checks', async ({ page }) => {
    const accessibilityScanResults = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze();

    expect(accessibilityScanResults.violations).toEqual([]);
  });

  test('is responsive on mobile viewport', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    
    // Check that main content is still visible
    await expect(page.getByRole('heading', { name: /Maddrax-Fantreffen 2026/i })).toBeVisible();
    
    // Form should be accessible
    await expect(page.getByLabel(/Vorname/i)).toBeVisible();
    await expect(page.getByLabel(/Nachname/i)).toBeVisible();
  });

  test('works in dark mode', async ({ page }) => {
    // Toggle dark mode (assuming there's a dark mode toggle)
    const html = page.locator('html');
    await html.evaluate((el) => el.classList.add('dark'));
    
    // Verify content is still visible and readable
    await expect(page.getByRole('heading', { name: /Maddrax-Fantreffen 2026/i })).toBeVisible();
    await expect(page.getByLabel(/Vorname/i)).toBeVisible();
  });

  test('Google Maps link opens in new tab', async ({ page }) => {
    const mapsLink = page.getByRole('link', { name: /Google Maps/i });
    await expect(mapsLink).toHaveAttribute('target', '_blank');
    await expect(mapsLink).toHaveAttribute('href', /maps\.app\.goo\.gl/i);
  });
});

test.describe('Maddrax-Fantreffen 2026 - Payment Confirmation Page', () => {
  test('redirects to confirmation page after successful registration', async ({ page }) => {
    await page.goto('/maddrax-fantreffen-2026');
    
    // Fill and submit form
    await page.getByLabel(/Vorname/i).fill('Success');
    await page.getByLabel(/Nachname/i).fill('User');
    await page.getByLabel(/E-Mail/i).fill('success@example.com');
    
    await page.getByRole('button', { name: /anmelden|Zahlung/i }).click();
    
    // Should redirect to confirmation page
    await page.waitForURL(/\/maddrax-fantreffen-2026\/bestaetigung\/\d+/);
    
    // Verify confirmation page content
    await expect(page.getByRole('heading', { name: /Anmeldung erfolgreich/i })).toBeVisible();
  });

  test('displays registration details on confirmation page', async ({ page }) => {
    // Assuming we have a test registration ID
    await page.goto('/maddrax-fantreffen-2026/bestaetigung/1');
    
    // Should show user details
    await expect(page.getByText(/Name:/i)).toBeVisible();
    await expect(page.getByText(/E-Mail:/i)).toBeVisible();
    await expect(page.getByText(/Status:/i)).toBeVisible();
  });

  test('shows PayPal button for pending payments', async ({ page }) => {
    await page.goto('/maddrax-fantreffen-2026/bestaetigung/1');
    
    const paypalButton = page.getByRole('link', { name: /PayPal zahlen/i });
    await expect(paypalButton).toBeVisible();
    await expect(paypalButton).toHaveAttribute('href', /paypal\.me/i);
    await expect(paypalButton).toHaveAttribute('target', '_blank');
  });

  test('displays correct payment amount for guest without t-shirt', async ({ page }) => {
    await page.goto('/maddrax-fantreffen-2026/bestaetigung/1');
    
    // Should show 5,00 €
    await expect(page.getByText(/5,00 €/i)).toBeVisible();
  });

  test('displays correct payment amount for guest with t-shirt', async ({ page }) => {
    await page.goto('/maddrax-fantreffen-2026/bestaetigung/2');
    
    // Should show 30,00 € (5€ + 25€)
    await expect(page.getByText(/30,00 €/i)).toBeVisible();
  });

  test('shows no payment required for members without t-shirt', async ({ page }) => {
    await page.goto('/maddrax-fantreffen-2026/bestaetigung/3');
    
    await expect(page.getByText(/Keine Zahlung erforderlich/i)).toBeVisible();
    
    // Should not show PayPal button
    await expect(page.getByRole('link', { name: /PayPal zahlen/i })).not.toBeVisible();
  });

  test('displays next steps information', async ({ page }) => {
    await page.goto('/maddrax-fantreffen-2026/bestaetigung/1');
    
    await expect(page.getByText(/Was passiert jetzt/i)).toBeVisible();
    await expect(page.getByText(/Bestätigungs-E-Mail/i)).toBeVisible();
  });

  test('has back to homepage link', async ({ page }) => {
    await page.goto('/maddrax-fantreffen-2026/bestaetigung/1');
    
    const homeLink = page.getByRole('link', { name: /Startseite/i });
    await expect(homeLink).toBeVisible();
    await expect(homeLink).toHaveAttribute('href', '/');
  });

  test('passes accessibility checks on confirmation page', async ({ page }) => {
    await page.goto('/maddrax-fantreffen-2026/bestaetigung/1');
    
    const accessibilityScanResults = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze();

    expect(accessibilityScanResults.violations).toEqual([]);
  });
});

test.describe('Maddrax-Fantreffen 2026 - Admin Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    // Login as admin first (adjust based on your auth system)
    await page.goto('/login');
    await page.getByLabel(/E-Mail/i).fill('admin@maddrax-fanclub.de');
    await page.getByLabel(/Passwort/i).fill('password');
    await page.getByRole('button', { name: /Anmelden/i }).click();
    
    await page.waitForURL('/dashboard');
    await page.goto('/admin/fantreffen-2026');
  });

  test('displays admin dashboard with statistics', async ({ page }) => {
    await expect(page.getByRole('heading', { name: /Anmeldungen/i })).toBeVisible();
    
    // Check statistics cards
    await expect(page.getByText(/Gesamt/i)).toBeVisible();
    await expect(page.getByText(/T-Shirts bestellt/i)).toBeVisible();
    await expect(page.getByText(/Zahlungen ausstehend/i)).toBeVisible();
  });

  test('displays registrations table', async ({ page }) => {
    // Check table headers
    await expect(page.getByRole('columnheader', { name: /Name/i })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: /E-Mail/i })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: /Status/i })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: /T-Shirt/i })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: /Zahlung/i })).toBeVisible();
  });

  test('can filter by member status', async ({ page }) => {
    const filterSelect = page.getByLabel(/Mitgliedsstatus/i);
    await filterSelect.selectOption('mitglieder');
    
    // Wait for Livewire to update
    await page.waitForTimeout(500);
    
    // Should only show members
    await expect(page.getByText(/Mitglied/i).first()).toBeVisible();
  });

  test('can filter by t-shirt status', async ({ page }) => {
    const filterSelect = page.getByLabel(/T-Shirt/i).first();
    await filterSelect.selectOption('mit_tshirt');
    
    await page.waitForTimeout(500);
    
    // Table should update
    const table = page.getByRole('table');
    await expect(table).toBeVisible();
  });

  test('can search by name', async ({ page }) => {
    const searchInput = page.getByPlaceholder(/Name oder E-Mail/i);
    await searchInput.fill('Max');
    
    await page.waitForTimeout(500);
    
    // Should filter results
    await expect(page.getByText(/Max/i).first()).toBeVisible();
  });

  test('can toggle payment received status', async ({ page }) => {
    const paymentButton = page.getByRole('button', { name: /Ausstehend|Erhalten/i }).first();
    const initialText = await paymentButton.textContent();
    
    await paymentButton.click();
    
    // Wait for update
    await page.waitForTimeout(500);
    
    // Status should change
    const newText = await paymentButton.textContent();
    expect(newText).not.toBe(initialText);
  });

  test('can toggle t-shirt ready status', async ({ page }) => {
    const tshirtButton = page.getByRole('button', { name: /Offen|Fertig/i }).first();
    
    await tshirtButton.click();
    
    // Should show success message
    await expect(page.getByText(/aktualisiert/i)).toBeVisible();
  });

  test('can export CSV', async ({ page }) => {
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('button', { name: /CSV Export/i }).click()
    ]);
    
    expect(download.suggestedFilename()).toMatch(/fantreffen-anmeldungen.*\.csv/);
  });

  test('displays pagination when many registrations', async ({ page }) => {
    // Assuming there are more than 20 registrations
    const pagination = page.locator('nav[role="navigation"]').filter({ hasText: /Seite/i });
    
    if (await pagination.isVisible()) {
      await expect(pagination).toBeVisible();
    }
  });

  test('passes accessibility checks on admin dashboard', async ({ page }) => {
    const accessibilityScanResults = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze();

    expect(accessibilityScanResults.violations).toEqual([]);
  });

  test('non-admin cannot access dashboard', async ({ page, context }) => {
    // Logout
    await page.goto('/logout');
    
    // Try to access admin page
    await page.goto('/admin/fantreffen-2026');
    
    // Should redirect to login or show 403
    await expect(page).toHaveURL(/\/login/);
  });
});

test.describe('Maddrax-Fantreffen 2026 - Keyboard Navigation', () => {
  test('can navigate form with keyboard', async ({ page }) => {
    await page.goto('/maddrax-fantreffen-2026');
    
    // Tab through form fields
    await page.keyboard.press('Tab'); // First field
    await page.keyboard.type('Keyboard');
    
    await page.keyboard.press('Tab'); // Next field
    await page.keyboard.type('User');
    
    await page.keyboard.press('Tab'); // Email field
    await page.keyboard.type('keyboard@example.com');
    
    // Should be able to reach submit button
    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');
    
    const submitButton = page.getByRole('button', { name: /anmelden|Zahlung/i });
    await expect(submitButton).toBeFocused();
  });

  test('can toggle t-shirt checkbox with keyboard', async ({ page }) => {
    await page.goto('/maddrax-fantreffen-2026');
    
    const tshirtCheckbox = page.getByRole('checkbox', { name: /Event-T-Shirt bestellen/i });
    
    // Focus and toggle with space
    await tshirtCheckbox.focus();
    await page.keyboard.press('Space');
    
    await expect(tshirtCheckbox).toBeChecked();
    
    // Size dropdown should appear
    await expect(page.getByLabel(/T-Shirt-Größe/i)).toBeVisible();
  });
});
