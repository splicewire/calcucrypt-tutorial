import { authFeaturesFrom } from '@splicewire/beam-inertia';

export const authFeatures = authFeaturesFrom([
    /* @chisel-registration */
    // Only where the registration door is open (ACCOUNT_REGISTRATION), as the server offers the register island.
    ...(import.meta.env.VITE_ACCOUNT_REGISTRATION === 'open' ? (['registration'] as const) : []),
    /* @end-chisel-registration */
    /* @chisel-email-verification */
    'email-verification',
    /* @end-chisel-email-verification */
    /* @chisel-2fa */
    '2fa',
    /* @end-chisel-2fa */
    /* @chisel-passkeys */
    'passkeys',
    /* @end-chisel-passkeys */
    /* @chisel-password-confirmation */
    'password-confirmation',
    /* @end-chisel-password-confirmation */
]);
