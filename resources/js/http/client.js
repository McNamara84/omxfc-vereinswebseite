const defaultHeaders = {
    'X-Requested-With': 'XMLHttpRequest',
};

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? null;
}

function isJsonBody(body) {
    return body !== null
        && typeof body !== 'undefined'
        && typeof body !== 'string'
        && !(body instanceof FormData)
        && !(body instanceof URLSearchParams)
        && !(body instanceof Blob)
        && !(body instanceof ArrayBuffer);
}

function defaultRequestHeaders() {
    return http.defaults?.headers?.common ?? defaultHeaders;
}

function buildHeaders(headers = {}, body) {
    const mergedHeaders = new Headers(defaultRequestHeaders());

    Object.entries(headers).forEach(([key, value]) => {
        if (value !== null && typeof value !== 'undefined') {
            mergedHeaders.set(key, value);
        }
    });

    const token = csrfToken();
    if (token && !mergedHeaders.has('X-CSRF-TOKEN')) {
        mergedHeaders.set('X-CSRF-TOKEN', token);
    }

    if (isJsonBody(body) && !mergedHeaders.has('Content-Type')) {
        mergedHeaders.set('Content-Type', 'application/json');
    }

    return mergedHeaders;
}

function buildBody(body) {
    if (!isJsonBody(body)) {
        return body;
    }

    return JSON.stringify(body);
}

function responseContentType(response) {
    return response.headers?.get?.('content-type')?.toLowerCase() ?? '';
}

function shouldParseJsonResponse(response) {
    const contentType = responseContentType(response);

    return contentType.includes('/json') || contentType.includes('+json');
}

async function parseResponseData(response) {
    if (response.status === 204 || response.status === 205) {
        return null;
    }

    if (typeof response.text === 'function') {
        const text = await response.text();

        if (text === '') {
            return null;
        }

        if (!shouldParseJsonResponse(response)) {
            return text;
        }

        try {
            return JSON.parse(text);
        } catch {
            return text;
        }
    }

    if (typeof response.json === 'function') {
        return response.json();
    }

    return null;
}

function toResponseObject(response, data) {
    return {
        data,
        status: response.status,
        statusText: response.statusText ?? '',
        headers: response.headers ?? null,
    };
}

async function request(input, { method = 'GET', headers, body, ...options } = {}) {
    const response = await fetch(input, {
        method,
        headers: buildHeaders(headers, body),
        body: typeof body === 'undefined' ? undefined : buildBody(body),
        ...options,
    });

    const data = await parseResponseData(response);
    const result = toResponseObject(response, data);

    if (!response.ok) {
        const error = new Error(`HTTP ${response.status} ${response.statusText ?? ''}`.trim());
        error.response = result;
        throw error;
    }

    return result;
}

export const http = {
    defaults: {
        headers: {
            common: { ...defaultHeaders },
        },
    },
    request,
    get(input, options = {}) {
        return request(input, {
            ...options,
            method: 'GET',
        });
    },
    post(input, body, options = {}) {
        return request(input, {
            ...options,
            method: 'POST',
            body,
        });
    },
};

export default http;