import type { ServiceRequestData } from '../../resources/js/generated/App/Data';

const request: ServiceRequestData = {
    id: 1,
    title: 'Restore email delivery',
    status: 'open',
    approvalState: 'pending',
};
void request;
const invalid: ServiceRequestData = {
    id: 1,
    // @ts-expect-error The emitted response requires a string title, not a number.
    title: 42,
    status: 'open',
    approvalState: 'pending',
};
void invalid;
