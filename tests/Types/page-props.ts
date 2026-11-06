import type {
    AuthEntryPageData,
    ProfilePageData,
    ResetPasswordPageData,
} from '../../resources/js/generated/Splicewire/Beam/Accounts/Data/Pages';
import type { FrameConsolePageData } from '../../resources/js/generated/App/Data/Pages';

const profile: ProfilePageData = {
    entry: null,
    mustVerifyEmail: false,
    status: null,
};
// `realm` is nullable to match beam-inertia's `FrameRealmContext` — an unscoped console passes null.
const frameConsole: FrameConsolePageData = {
    realm: null,
    basename: '/operator',
    manifestUrl: '/operator/frame/manifest',
};
const auth: AuthEntryPageData = {
    slug: 'confirm-password',
    entry: null,
    body: null,
};
const reset: ResetPasswordPageData = {
    slug: 'reset-password',
    entry: null,
    body: null,
    email: null,
    token: 'token',
    passwordRules: '',
};
// These must fail even if a generator starts emitting any or loses a nested reference.
// @ts-expect-error profile status is nullable text, not a number
const badStatus: ProfilePageData['status'] = 7;
// @ts-expect-error the console's manifest address is a required string, not optional
const badConsole: FrameConsolePageData = {
    realm: 'operator',
    basename: '/operator',
};
const badDemo: AuthEntryPageData['demoAccounts'] = [
    // @ts-expect-error demo account links carry string URLs
    { key: 'demo', label: 'Demo', url: 7 },
];

void [profile, frameConsole, auth, reset, badStatus, badConsole, badDemo];
