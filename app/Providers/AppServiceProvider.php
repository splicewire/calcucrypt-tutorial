<?php

namespace App\Providers;

use App\Data\SitemapData;
use App\Models\SitemapRecord;
use App\Models\User;
use App\Sitemap\RecordLeaf;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Rushing\PermissionCascade\Support\CascadePolicyRegistrar;
use Splicewire\Beam\Authorization\SeatGate;
use Splicewire\Beam\Doctor\BeamDoctorManifest;
use Splicewire\Beam\Ux\Doctor\OrphanedNavItemAudit;
use Splicewire\Beam\Ux\Frame\RouteContextPlan;
use Splicewire\Beam\Ux\Nav\Contracts\NavItemSource;

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

        // No account-shell binding: beam-accounts' identity-only default fills the profile block (handle, initials, email)
        // and hides the plan chip. A host with real plans binds its own provider (make-surface 01, owner ruling 2026-11-05).

        // The record leaf is this host's persisted nav: the packaged orphaned-nav doctor check validates its links.
        $this->app->bind(NavItemSource::class, RecordLeaf::class);
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
     * Register the packaged orphaned-nav audit (beam-ux, over the {@see RecordLeaf} source bound above) into beam-core's aggregation manifest — the same seam every
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

    /** Host-specific documentation publication follows the package's operator authoring gate. */
    protected function registerAuthoringGates(): void
    {
        Gate::define('beam-docs.publish', fn (User $user): bool => $user->can('ux.operator.author'));
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
