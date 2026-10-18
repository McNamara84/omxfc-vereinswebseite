import { buildTopUserSummary, enhanceTopUserList, setupDashboardAccessibility } from '@/dashboard/accessibility';

describe('dashboard accessibility utilities', () => {
  const sampleUsers = [
    { name: 'Alex Beispiel', points: 180 },
    { name: 'Bianca Beispiel', points: 140 },
    { name: 'Chris Beispiel', points: 95 },
  ];

  beforeEach(() => {
    document.body.innerHTML = '';
  });

  it('builds a readable summary for top users', () => {
    const summary = buildTopUserSummary(sampleUsers);

    expect(summary).toBe('Top 3 Baxx-Sammler: 1. Alex Beispiel (180 Baxx), 2. Bianca Beispiel (140 Baxx), 3. Chris Beispiel (95 Baxx)');
  });

  it('keeps formatted point strings stable when hydrating from payload data', () => {
    const summary = buildTopUserSummary([
      { name: 'Alex Beispiel', points: 1234, formatted_points: '1.234' },
      { name: 'Bianca Beispiel', points: 1040, formatted_points: '1.040' },
    ]);

    expect(summary).toBe('Top 2 Baxx-Sammler: 1. Alex Beispiel (1.234 Baxx), 2. Bianca Beispiel (1.040 Baxx)');
  });

  it('enhances the top user list with aria attributes and summary text', () => {
    const container = document.createElement('div');
    container.dataset.dashboardTopUsers = JSON.stringify(sampleUsers);
    container.innerHTML = `
      <span data-dashboard-top-summary></span>
      <a data-dashboard-top-user-item></a>
      <a data-dashboard-top-user-item></a>
      <a data-dashboard-top-user-item></a>
    `;

    const summary = enhanceTopUserList(container);

    expect(summary).toContain('Top 3 Baxx-Sammler');
    expect(container.getAttribute('role')).toBe('list');
    expect(container.getAttribute('aria-label')).toBe(summary);
    container.querySelectorAll('[data-dashboard-top-user-item]').forEach((item) => {
      expect(item.getAttribute('role')).toBe('listitem');
    });
    expect(container.querySelector('[data-dashboard-top-summary]').textContent).toBe(summary);
  });

  it('ignores containers with invalid data without throwing', () => {
    const container = document.createElement('div');
    container.dataset.dashboardTopUsers = 'not-json';
    document.body.appendChild(container);

    expect(() => setupDashboardAccessibility(document)).not.toThrow();
    expect(container.hasAttribute('aria-label')).toBe(false);
  });

  it('keeps native links inside ordered list items in the current ranking', () => {
    const container = document.createElement('div');
    container.setAttribute('role', 'region');
    container.innerHTML = '<p data-dashboard-top-summary></p><ol><li><a href="/profil/1" data-dashboard-top-user-item>Alex</a></li></ol>';
    enhanceTopUserList(container, sampleUsers);
    expect(container.getAttribute('role')).toBe('region');
    expect(container.querySelector('a').hasAttribute('role')).toBe(false);
    expect(container.querySelector('ol > li > a').getAttribute('href')).toBe('/profil/1');
  });
});
