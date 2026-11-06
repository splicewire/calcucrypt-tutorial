<?php

namespace App\Providers;

use App\Account\StarterAccountShell;
use App\Beam\RealmRegistry;
use App\Data\SitemapData;
use App\Doctor\OrphanedNavItemAudit;
use App\Models\SitemapRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Rushing\PermissionCascade\Contracts\EntitlementResolver;
use Rushing\PermissionCascade\Support\CascadePolicyRegistrar;
use Splicewire\Beam\Accounts\Contracts\AccountShellProvider;
use Splicewire\Beam\Authorization\SeatGate;
use Splicewire\Beam\Doctor\BeamDoctorManifest;
use Splicewire\Beam\Ux\Frame\RouteContextPlan;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The public /schemas door serves schema documents at their identities. The catalog UI
        // gets its own path through the same plan that projects console routes and navigation.
        $this->app->bind(RouteContextPlan::class, fn (): RouteContextPlan => new RouteContextPlan(
            resourcePaths: ['schemas' => 'schema-catalog', 'beam-ux-release-files' => 'release-files'],
        ));

        // Bind the host account-shell provider over beam-accounts' NullAccountShellProvider default,
        // so the packaged <AccountShell> renders real (neutral demo) plan/profile data OOTB.
        $this->app->bind(
            AccountShellProvider::class,
            StarterAccountShell::class,
        );
    }

    /**
     * Bootstrap any application services.
     *
     * NOTE: the sitemap resource is NOT registered here. It is declared entirely by the single
     * `#[ParticleResource]` on {@see SitemapData}, which beam's attributed discovery
     * reflects into the admin manifest at boot (config `frame.discover_paths` points the scan at
     * `app/Data`). Dropping one annotated Data class IS the wiring — no provider line, no second
     * declaration, no handler-map entry.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        if (config('canonical-mdx.enabled', false)) {
            config(['beam.ux.releases.review_required' => true, 'beam.ux.releases.allow_self_approval' => false]);
        }
        // Creating a team is open to authenticated users, including users without a team.
        $this->app->booted(function (): void {
            $this->app->make(Router::class)->getRoutes()->getByName('teams.create')
                ?->defaults(SeatGate::OPEN_TO_MEMBERS, true);
        });
        $this->registerAuthoringGates();
        $this->registerResourcePolicies();
        $this->registerDoctorAudits();
    }

    /**
     * Bind this HOST's own models onto the Gate — the registration half `#[UseCascadePolicy]` cannot
     * do for itself, exactly as beam-core does for `Hook`/`BeamSchema` and beam-accounts for
     * `Invitation`. Packages bind the models they own; a host binds its own, and
     * {@see SitemapRecord} is the starter's only one.
     *
     * This is load-bearing, not decorative. `Schemastud\Frame\Authorization\ResourceAuthorizer`
     * refuses a write against a model with no policy — for every actor, the team owner included — so
     * without this line the `frame/resources/sitemap` editor is a 403 for everyone and the starter
     * ships a resource nobody can use.
     */
    protected function registerResourcePolicies(): void
    {
        CascadePolicyRegistrar::register(SitemapRecord::class);
    }

    /**
     * Register this HOST's doctor audit into beam-core's aggregation manifest — the same seam every
     * beam-* package uses (the manifest is a singleton bound in beam's register phase and read fresh
     * when `splicewire:beam:doctor` runs, so an app registering in boot always lands). Advisory: an
     * orphaned nav link renders yellow but never turns the run red. Guarded on the manifest being
     * bound so the starter still boots against a beam-core that predates it.
     */
    protected function registerDoctorAudits(): void
    {
        if ($this->app->bound(BeamDoctorManifest::class)) {
            $this->app->make(BeamDoctorManifest::class)->register(
                package: 'app',
                audit: OrphanedNavItemAudit::class,
                gate: false,
            );
        }
    }

    /**
     * Register the authoring gates (theme-entries-and-authoring ticket 02, porting
     * `rushing/audiostud`'s `AppServiceProvider::registerRealmAuthoringGates()`, AUD-02's retired
     * form): the flat global `ux.author` ability resolves through `entitlement:ux.author` — the Gate
     * `beam`'s `registerEntitlementAbilities()` already registers off `config('app.entitlements')`,
     * fed by `laravel-beam-accounts`' `DefaultEntitlementResolver` (ACC-01's realm-grant cascade: an
     * Owner/Admin-held Team's `manage` grant on a realm's root). No `is_staff` — this host never bound
     * its own resolver, so it already rides the package default; only the Gate SURFACE needed fixing.
     *
     * `ux.{realm}.author` reads the resolver's raw key list directly — there is no
     * `entitlement:ux.{realm}.author` Gate (beam only registers abilities for the flat keys in
     * `config('app.entitlements')`, and `ux.{realm}.author` is realm-parameterized, not flat).
     *
     * **Deliberately does NOT fall back to `ux.author` for the per-realm check.**
     * `DefaultEntitlementResolver` composes the coarse `ux.author` key as soon as ANY single realm is
     * granted (mirroring old blanket-staff semantics for a Team holding EVERY realm's grant) — an
     * `ux.{realm}.author = ux.author || ...` shortcut would let a grant on just ONE realm leak
     * authoring into every OTHER realm too, defeating the per-realm grain this Gate exists for. A
     * grantee of every realm still authors every realm (each `ux.{realm}.author` key composes
     * independently); a narrowly-granted principal now correctly stays narrow.
     */
    protected function registerAuthoringGates(): void
    {
        Gate::define('ux.author', fn (User $user): bool => $user->can('entitlement:ux.author'));
        Gate::define('beam-docs.publish', fn (User $user): bool => $user->can('ux.operator.author'));

        foreach (RealmRegistry::realms() as $realm) {
            Gate::define(
                RealmRegistry::authorAbility($realm),
                fn (User $user): bool => in_array(
                    RealmRegistry::authorAbility($realm),
                    app(EntitlementResolver::class)->entitlementsFor($user),
                    true,
                ),
            );
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
