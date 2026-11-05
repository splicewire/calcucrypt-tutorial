import type { ApprovalUsageData } from '../../resources/js/generated/App/Data';

const valid: ApprovalUsageData = {
    id: 'approvals',
    meter: 'service-request.approval',
    quantity: 2,
    unit: 'approvals',
    period: 'lifetime',
};
const invalid: ApprovalUsageData = {
    ...valid,
    // @ts-expect-error The exported usage quantity is numeric, not a formatted string.
    quantity: '2',
};
void invalid;
