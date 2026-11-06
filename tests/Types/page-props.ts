import type {
    AuthEntryPageData,
    ProfilePageData,
    ResetPasswordPageData,
} from '../../resources/js/generated/Splicewire/Beam/Accounts/Data/Pages';

const profile: ProfilePageData = {
    entry: null,
    mustVerifyEmail: false,
    status: null,
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
const badDemo: AuthEntryPageData['demoAccounts'] = [
    // @ts-expect-error demo account links carry string URLs
    { key: 'demo', label: 'Demo', url: 7 },
];

void [profile, auth, reset, badStatus, badDemo];
