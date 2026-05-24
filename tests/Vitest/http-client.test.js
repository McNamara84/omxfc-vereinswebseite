import http from '@/http/client';

function jsonResponse(data, { ok = true, status = 200, statusText = 'OK' } = {}) {
    return {
        ok,
        status,
        statusText,
        headers: { get: () => 'application/json' },
        json: vi.fn().mockResolvedValue(data),
        text: vi.fn().mockResolvedValue(JSON.stringify(data)),
    };
}

describe('http client', () => {
    beforeEach(() => {
        document.head.innerHTML = '';
        global.fetch = vi.fn();
        http.defaults.headers.common = {
            'X-Requested-With': 'XMLHttpRequest',
        };
    });

    it('sendet Standard-Header und parsed JSON-Antworten', async () => {
        global.fetch.mockResolvedValue(jsonResponse({ status: 'ok' }));

        const response = await http.get('/api/test');
        const [, options] = global.fetch.mock.calls[0];

        expect(options.method).toBe('GET');
        expect(options.headers.get('X-Requested-With')).toBe('XMLHttpRequest');
        expect(response).toMatchObject({
            data: { status: 'ok' },
            status: 200,
        });
    });

    it('serialisiert JSON-POST-Bodies und sendet den CSRF-Header', async () => {
        document.head.innerHTML = '<meta name="csrf-token" content="TOKEN">';
        global.fetch.mockResolvedValue(jsonResponse({ created: true }, { status: 201, statusText: 'Created' }));

        await http.post('/api/test', { answer: 42 });
        const [, options] = global.fetch.mock.calls[0];

        expect(options.method).toBe('POST');
        expect(options.body).toBe('{"answer":42}');
        expect(options.headers.get('Content-Type')).toBe('application/json');
        expect(options.headers.get('X-CSRF-TOKEN')).toBe('TOKEN');
    });

    it('sendet den CSRF-Header nicht an fremde Origins', async () => {
        document.head.innerHTML = '<meta name="csrf-token" content="TOKEN">';
        global.fetch.mockResolvedValue(jsonResponse({ ok: true }));

        await http.get('https://example.com/api/test');
        const [, options] = global.fetch.mock.calls[0];

        expect(options.headers.get('X-CSRF-TOKEN')).toBeNull();
    });

    it('beruecksichtigt Laufzeit-Aenderungen an den globalen Default-Headern', async () => {
        http.defaults.headers.common.Accept = 'application/json';
        global.fetch.mockResolvedValue(jsonResponse({ status: 'ok' }));

        await http.get('/api/test');
        const [, options] = global.fetch.mock.calls[0];

        expect(options.headers.get('Accept')).toBe('application/json');
    });

    it('wirft Fehler mit response-Payload bei nicht erfolgreichen Antworten', async () => {
        global.fetch.mockResolvedValue(jsonResponse({ message: 'Nein' }, {
            ok: false,
            status: 401,
            statusText: 'Unauthorized',
        }));

        await expect(http.get('/api/forbidden')).rejects.toMatchObject({
            response: {
                status: 401,
                data: { message: 'Nein' },
            },
        });
    });

    it('liest ungueltiges JSON nur einmal und faellt auf Text zurueck', async () => {
        global.fetch.mockResolvedValue(new Response('kein-json', {
            status: 200,
            headers: {
                'Content-Type': 'application/json',
            },
        }));

        await expect(http.get('/api/malformed')).resolves.toMatchObject({
            data: 'kein-json',
            status: 200,
        });
    });
});