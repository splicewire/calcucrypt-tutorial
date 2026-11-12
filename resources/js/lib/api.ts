import { createSessionRequest } from '@schemastud/frame/session';

// Beam client runtime — the `client_import` half of the generated client's runtime contract
// (`beam.client.client_import`, default '@/lib/api'). Published by
// `php artisan vendor:publish --tag=beam-client-runtime`; after publishing this file is YOURS —
// the generator imports it but never writes it. Contract reference:
// splicewire/laravel-beam docs/client-runtime-contract.md.
//
// This is the one-tier (satellite) reference implementation on `fetch`. A host with an operator
// tier additionally exports `operatorApi` with the same shape (see the platform host for the
// axios-based, interceptor-carrying precedent — both are legitimate; only the exported contract
// is fixed).

/** The double-unwrap envelope generated hooks destructure: `res.data.data` is the payload. */
export type ApiResponse<T> = { data: { data: T | undefined } };

const sessionRequest = createSessionRequest();

async function request<T>(
    method: string,
    url: string,
    body?: unknown,
): Promise<ApiResponse<T>> {
    const response = await sessionRequest<{ data: T } | null>(
        method,
        url,
        undefined,
        body,
    );

    // Preserve the generated hooks' double unwrap, including an empty DELETE response.
    return { data: response.data ?? { data: undefined } };
}

export const api = {
    get: <T = unknown>(url: string) => request<T>('GET', url),
    post: <T = unknown>(url: string, body?: unknown) =>
        request<T>('POST', url, body),
    put: <T = unknown>(url: string, body?: unknown) =>
        request<T>('PUT', url, body),
    patch: <T = unknown>(url: string, body?: unknown) =>
        request<T>('PATCH', url, body),
    delete: <T = unknown>(url: string) => request<T>('DELETE', url),
};
