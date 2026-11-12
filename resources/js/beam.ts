import { authFeaturesFrom } from '@splicewire/beam-inertia';

export const authFeatures = authFeaturesFrom([
    // Only where the registration door is open (ACCOUNT_REGISTRATION), as the server offers the register island.
    ...(import.meta.env.VITE_ACCOUNT_REGISTRATION === 'open' ? (['registration'] as const) : []),
    'email-verification',
    '2fa',
    'passkeys',
    'password-confirmation',
]);
