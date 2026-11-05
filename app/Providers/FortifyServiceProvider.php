<?php

namespace App\Providers;

/* @chisel-registration */

/* @end-chisel-registration */
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Splicewire\Beam\Accounts\Actions\DemoLoginLinks;
use Splicewire\Beam\Accounts\Data\Pages\AuthEntryPageData;
use Splicewire\Beam\Accounts\Data\Pages\ResetPasswordPageData;
use Splicewire\Beam\Accounts\Fortify\ResetUserPassword;
use Splicewire\Beam\Ux\Support\EntryBody;
use Splicewire\Beam\Ux\Support\PageEntryRef;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(EntryBody $entryBody): void
    {
        $this->configureActions();
        $this->configureViews($entryBody);
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        // Password reset and registration both use beam-accounts' actions and its camel `passwordConfirmation` wire.
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        // Registration uses beam-accounts' door-checked CreateNewUser (BUY-05); a host extends it, never copies it.
    }

    /**
     * Configure Fortify views.
     *
     * theme-entries-and-authoring STR-03: every view renders the SAME beam-ux entry-resolution page
     * (`auth/entry`), differentiated by `slug` — the `BeamUxEntry` `Splicewire\Beam\Ux\Support\EntryBody::forSlug()`
     * resolves. Every prop these views computed before (`canResetPassword`, `status`, `demoAccounts`,
     * `email`, `token`, `passwordRules`) still flows exactly as before; the sealed form island reads
     * them via `usePage()` instead of receiving them as direct page-component props (it's no longer the
     * top-level Inertia page — `resources/js/editor/registry.tsx`). Routes/controllers/business guards
     * (password-reset token validity, 2FA challenge session, etc.) are untouched — view-selection only.
     */
    private function configureViews(EntryBody $entryBody): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/entry', AuthEntryPageData::from([
            'slug' => 'login',
            'entry' => PageEntryRef::for('login'),
            'body' => $entryBody->forSlug('login'),
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
            // Quick demo sign-in — the OOTB beam-accounts login-as affordance, minted by the package
            // (`DemoLoginLinks::all()`: expiring signed `users/{id}/login-as` links, gated on demo
            // mode). Empty unless `ACCOUNT_DEMO_LOGIN_LINKS=true`, so the demo block simply doesn't render.
            'demoAccounts' => $this->demoAccounts(),
        ])));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/entry', ResetPasswordPageData::from([
            'slug' => 'reset-password',
            'entry' => PageEntryRef::for('reset-password'),
            'body' => $entryBody->forSlug('reset-password'),
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ])));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/entry', AuthEntryPageData::from([
            'slug' => 'forgot-password',
            'entry' => PageEntryRef::for('forgot-password'),
            'body' => $entryBody->forSlug('forgot-password'),
            'status' => $request->session()->get('status'),
        ])));

        /* @chisel-email-verification */
        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/entry', AuthEntryPageData::from([
            'slug' => 'verify-email',
            'entry' => PageEntryRef::for('verify-email'),
            'body' => $entryBody->forSlug('verify-email'),
            'status' => $request->session()->get('status'),
        ])));
        /* @end-chisel-email-verification */

        /* @chisel-registration */
        Fortify::registerView(fn () => Inertia::render('auth/entry', AuthEntryPageData::from([
            'slug' => 'register',
            'entry' => PageEntryRef::for('register'),
            'body' => $entryBody->forSlug('register'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ])));
        /* @end-chisel-registration */

        /* @chisel-2fa */
        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/entry', AuthEntryPageData::from([
            'slug' => 'two-factor-challenge',
            'entry' => PageEntryRef::for('two-factor-challenge'),
            'body' => $entryBody->forSlug('two-factor-challenge'),
        ])));
        /* @end-chisel-2fa */

        /* @chisel-password-confirmation */
        Fortify::confirmPasswordView(fn () => Inertia::render('auth/entry', AuthEntryPageData::from([
            'slug' => 'confirm-password',
            'entry' => PageEntryRef::for('confirm-password'),
            'body' => $entryBody->forSlug('confirm-password'),
        ])));
        /* @end-chisel-password-confirmation */
    }

    /**
     * The demo sign-in buttons for the login page — the OOTB beam-accounts login-as affordance,
     * delegated whole to the package.
     *
     * ## Why this is one call and not a body (api-surface-coherence 99)
     *
     * It used to be a hand-rolled roster mint here, and three sibling hosts carried the same copy.
     * Every copy called `URL::signedRoute()`, which mints a signature with **no `expires`** — and
     * `hasValidSignature()` enforces an expiry only when the URL carries one. The operation
     * (`Splicewire\Beam\Accounts\Ops\LogInAsUser`) declares `signed: true` and is admitted on a
     * valid signature BEFORE its `loginAs` ability check, precisely so an anonymous holder can
     * follow the link. So the link is the whole credential, there is no per-link revocation, and a
     * link minted without an expiry admitted **forever**.
     *
     * `DemoLoginLinks::all()` is the package's single mint. It carries the two controls the copy
     * did not: a TTL (`beam.accounts.demo.login_link_minutes`, default 30), and the demo-mode
     * publish gate `beam.accounts.demo.login_links` — a SECOND key, narrower than
     * `BeamDemo::enabled()`, which ships **false** and fails closed. A published link is a bearer
     * credential rendered into an anonymous page, so it is not something `enabled()`'s
     * "on everywhere but production" default should hand to every preview and shared dev host.
     *
     * Empty ⇒ no buttons, which is the shipped default: turn one host on with
     * `ACCOUNT_DEMO_LOGIN_LINKS=true`. The subjects themselves are provisioned by the package
     * `DemoTeamSeeder` (called from DatabaseSeeder); a key with no seeded user is omitted.
     *
     * @return list<array{key: string, label: string, url: string}>
     */
    private function demoAccounts(): array
    {
        return array_values(app(DemoLoginLinks::class)->all());
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        /* @chisel-2fa */
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
        /* @end-chisel-2fa */

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        /* @chisel-passkeys */
        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
        /* @end-chisel-passkeys */
    }
}
