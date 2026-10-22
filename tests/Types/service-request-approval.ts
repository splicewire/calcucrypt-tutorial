import type { WorkflowTransitionAttemptData } from '../../resources/js/generated/Splicewire/Beam/Workflows/Data';
import { approveServiceRequest } from '../../examples/service-request-approval-client';

const consumer: (id: number) => Promise<WorkflowTransitionAttemptData> =
    approveServiceRequest;
void consumer;
function inspectAttempt(result: WorkflowTransitionAttemptData) {
    const applied: boolean = result.applied;
    const current: string | undefined = result.projection?.current;
    // @ts-expect-error The actual generated outcome is boolean, not an untyped string.
    const incorrect: string = result.applied;

    return { applied, current, incorrect };
}
void inspectAttempt;
