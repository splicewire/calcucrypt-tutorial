/** TeamPage's thin adapter; token URLs/client behavior belong to the package factory. */
import { createSessionRequest } from '@schemastud/frame/session';
import type { TeamClient } from '@splicewire/beam-accounts';

// No packaged TeamClient factory yet: this literal must follow config('beam.accounts.api_root') if it moves.
const ROOT = '/beam/accounts';
const request = createSessionRequest();

async function call<T>(
    method: string,
    path: string,
    body?: unknown,
): Promise<T> {
    const response = await request<{ data: T } | null>(
        method,
        `${ROOT}${path}`,
        undefined,
        body,
    );

    return response.data?.data as T;
}

export const teamClient: TeamClient = {
    members: () => call('GET', '/members'),
    invitations: () => call('GET', '/invitations'),
    roles: () => call('GET', '/members/roles'),
    updateRole: ({ userId, role }) =>
        call('PUT', `/members/${userId}/role`, { role }),
    removeMember: (id) => call('DELETE', `/members/${id}`),
    sendInvitation: (body) => call('POST', '/invitations', body),
    revokeInvitation: (id) => call('DELETE', `/invitations/${id}`),
    resendInvitation: (id) => call('POST', `/invitations/${id}/resend`),
};
