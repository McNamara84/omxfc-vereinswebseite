import { describe, expect, it, beforeEach } from 'vitest';
import { buildMembersTableSummary, enhanceMembersTable, setupMitgliederAccessibility } from '@/js/mitglieder/accessibility';

describe('buildMembersTableSummary', () => {
    it('describes sorting, filters and totals in German', () => {
        const summary = buildMembersTableSummary({
            sortBy: 'role',
            sortDir: 'desc',
            filterOnline: true,
            totalMembers: 3,
        });

        expect(summary).toContain('Rolle');
        expect(summary).toContain('absteigender');
        expect(summary).toContain('nur Mitglieder angezeigt, die aktuell online sind');
        expect(summary).toContain('3 Mitglieder');
    });

    it('falls back to Nachname and ignores invalid totals', () => {
        const summary = buildMembersTableSummary({
            sortBy: 'unbekannt',
            sortDir: 'asc',
            filterOnline: false,
            totalMembers: 'abc',
        });

        expect(summary).toContain('Nachname');
        expect(summary).toContain('aufsteigender');
        expect(summary).not.toContain('Mitglieder sichtbar. Insgesamt sind sichtbar.');
    });
});

describe('enhanceMembersTable', () => {
    let table;
    let summary;
    let headerName;
    let headerRole;

    beforeEach(() => {
        document.body.innerHTML = '';

        summary = document.createElement('p');
        summary.id = 'members-table-summary';
        summary.dataset.membersSummary = '';
        document.body.appendChild(summary);

        table = document.createElement('table');
        table.dataset.membersTable = '';
        table.dataset.membersSort = 'role';
        table.dataset.membersDir = 'desc';
        table.dataset.membersFilterOnline = 'false';
        table.dataset.membersTotal = '4';
        table.dataset.membersSummaryId = summary.id;

        const thead = document.createElement('thead');
        const row = document.createElement('tr');
        headerName = document.createElement('th');
        headerName.dataset.membersSortColumn = 'nachname';
        headerRole = document.createElement('th');
        headerRole.dataset.membersSortColumn = 'role';

        row.appendChild(headerName);
        row.appendChild(headerRole);
        thead.appendChild(row);
        table.appendChild(thead);

        document.body.appendChild(table);
    });

    it('updates aria-sort attributes and summary text', () => {
        const summaryText = enhanceMembersTable(table);

        expect(summary.textContent).toBe(summaryText);
        expect(summaryText).toContain('Rolle');
        expect(headerRole.getAttribute('aria-sort')).toBe('descending');
        expect(headerName.getAttribute('aria-sort')).toBe('none');
    });

    it('gracefully handles missing tables', () => {
        expect(enhanceMembersTable(null)).toBe('');
    });
});

describe('setupMitgliederAccessibility', () => {
    it('enhances all matching tables in the provided root', () => {
        document.body.innerHTML = `
            <p id="members-table-summary" data-members-summary></p>
            <table data-members-table data-members-sort="mitglied_seit" data-members-dir="asc" data-members-summary-id="members-table-summary">
                <thead>
                    <tr>
                        <th data-members-sort-column="mitglied_seit"></th>
                        <th data-members-sort-column="nachname"></th>
                    </tr>
                </thead>
            </table>
        `;

        const results = setupMitgliederAccessibility(document);

        expect(results).toHaveLength(1);
        expect(document.querySelector('[data-members-sort-column="mitglied_seit"]').getAttribute('aria-sort')).toBe('ascending');
    });
});
