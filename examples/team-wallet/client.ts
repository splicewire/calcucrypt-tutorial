import { defaults } from '../../resources/js/generated/routes';
import type {
    WalletTopupInputData,
    WalletTopupResultData,
} from '../../resources/js/generated/App/Wallet/Data';

// Optional example: generate assets in the enabled Commerce composition first.
// DTO property names are camelCase; the declared MapInputName fixes the request wire key.
export async function addSandboxFunds(input: WalletTopupInputData): Promise<WalletTopupResultData> {
    const path = defaults['team-approval-wallet.top-up'];
    if (!path) throw new Error('Generate the enabled wallet route first.');
    const token = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1];
    const response = await fetch(`/${path}`, {
        method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json',
            ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}) },
        body: JSON.stringify({ amount_usd: input.amountUsd }),
    });
    const envelope = await response.json() as { data: WalletTopupResultData; message?: string };
    if (!response.ok) {
        throw new Error(response.status === 402 ? 'Sandbox funding declined.'
            : 'Funding outcome unknown. Read the wallet before any new deliberate purchase.');
    }
    return envelope.data;
}
