import { describe, expect, it, vi } from 'vitest';
import {
    buildMemberPopup,
    createLegendMarkup,
    defaultLegendItems,
    escapeHtml,
    getRoleIconType,
    normalizeRole,
} from '@/js/mitglieder/map-utils';

describe('map utils role detection', () => {
    it('normalizes roles to lower case', () => {
        expect(normalizeRole(' Vorstand ')).toBe('vorstand');
        expect(normalizeRole(null)).toBe('');
    });

    it('returns vorstand for leadership roles', () => {
        expect(getRoleIconType('Vorstand')).toBe('vorstand');
        expect(getRoleIconType('Kassenwart')).toBe('vorstand');
    });

    it('returns ehrenmitglied when applicable', () => {
        expect(getRoleIconType('Ehrenmitglied')).toBe('ehrenmitglied');
        expect(getRoleIconType('ehrenmitglied des jahres')).toBe('ehrenmitglied');
    });

    it('falls back to mitglied for other roles', () => {
        expect(getRoleIconType('Admin')).toBe('mitglied');
        expect(getRoleIconType(undefined)).toBe('mitglied');
    });
});

describe('global exposure', () => {
    it('preserves custom properties while updating helper methods', async () => {
        vi.resetModules();
        global.window = {
            omxfcMemberMap: {
                custom: 'value',
                getRoleIconType: () => 'custom',
            },
        };

        const module = await import('@/js/mitglieder/map-utils');

        expect(window.omxfcMemberMap.custom).toBe('value');
        expect(window.omxfcMemberMap.getRoleIconType).toBe(module.getRoleIconType);
        expect(window.omxfcMemberMap.buildMemberPopup).toBe(module.buildMemberPopup);

        delete global.window;
    });

    it('initializes helpers when an invalid map utils object exists', async () => {
        vi.resetModules();
        global.window = { omxfcMemberMap: 'invalid' };

        const module = await import('@/js/mitglieder/map-utils');

        expect(typeof window.omxfcMemberMap.getRoleIconType).toBe('function');
        expect(window.omxfcMemberMap.defaultLegendItems).toEqual(module.defaultLegendItems);

        delete global.window;
    });
});

describe('map utils popup rendering', () => {
    it('escapes html content for popup fields', () => {
        const popup = buildMemberPopup({
            name: '<Holger>',
            city: 'Musterstadt & Co',
            role: 'Vorstand',
            profile_url: 'https://example.com?name=<Holger>',
        });

        expect(popup).not.toContain('<Holger>');
        expect(popup).toContain('&lt;Holger&gt;');
        expect(popup).toContain('Musterstadt &amp; Co');
        expect(popup).toContain('https://example.com?name=&lt;Holger&gt;');
        expect(popup).toContain('aria-label="Profil von &lt;Holger&gt; öffnen"');
    });

    it('adds accessible structure to popup content', () => {
        const popup = buildMemberPopup({
            name: 'Holger Ehrmann',
            city: 'Musterstadt',
            role: 'Mitglied',
            profile_url: '/profile/1',
        });

        expect(popup).toContain('role="group"');
        expect(popup).toContain('aria-label="Mitglied Holger Ehrmann"');
        expect(popup).toContain('Zum Profil');
    });
});

describe('legend markup', () => {
    it('renders default legend items as an accessible list', () => {
        const markup = createLegendMarkup();

        expect(markup).toContain('role="list"');
        expect(markup).toContain('aria-labelledby="member-map-legend-heading"');
        expect(markup.match(/role="listitem"/g)?.length).toBe(defaultLegendItems.length);
    });

    it('escapes custom legend labels', () => {
        const markup = createLegendMarkup([
            { key: 'custom', label: '<script>alert(1)</script>' },
        ]);

        expect(markup).not.toContain('<script>alert(1)</script>');
        expect(markup).toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
    });

    it('exposes escapeHtml for other helpers', () => {
        expect(escapeHtml('A & B')).toBe('A &amp; B');
    });
});
