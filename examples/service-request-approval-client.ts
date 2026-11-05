import { defaults } from '../resources/js/generated/routes';
import type { WorkflowTransitionAttemptData } from '../resources/js/generated/Splicewire/Beam/Workflows/Data';

// Browser session example: this operation is a web/CSRF mount, not a bearer approval API.
export async function approveServiceRequest(
    id: number,
): Promise<WorkflowTransitionAttemptData> {
    const path = defaults['service-requests.approve'];

    if (!path) {
        throw new Error('Regenerate the declared approval route first.');
    }

    const xsrf = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);
    const response = await fetch(
        `/${path.replace('{id}', encodeURIComponent(String(id)))}`,
        {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf) } : {}),
            },
        },
    );
    const result = (await response.json()) as {
        data: WorkflowTransitionAttemptData;
        message?: string;
    };

    if (!response.ok) {
        throw new Error(
            result.message ?? `Approval refused (${response.status}).`,
        );
    }

    return result.data;
}
