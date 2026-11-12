<?php

namespace App\Providers;

use App\Content\Calculators\CalculatorDefinition;
use App\Content\Calculators\CalculatorRegistry;
use App\Models\Calculator;
use App\Models\User;
use App\SavedInputs\SavedInputAssociations;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Splicewire\Beam\Authorization\SeatGate;
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

        // Beam Accounts supplies the signed-in identity; plan/catalog rules stay in the calculator feature.
    }

    /** Bootstrap the calculator host. */
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
        $this->declareCalculatorEntitlements();
        Calculator::deleting(fn (Calculator $calculator) => app(SavedInputAssociations::class)->deletingCalculator($calculator));
        User::deleting(fn (User $user) => app(SavedInputAssociations::class)->deletingUser($user));
    }

    /**
     * Declare every paid calculator's entitlement key, so Beam defines an `entitlement:calculators.{slug}`
     * gate for each. Declaring a key grants it to no one: until a plan holds it, the calculator is locked
     * for every reader. Beam reads its keys when it checks one, so declaring them in boot() is in time.
     */
    protected function declareCalculatorEntitlements(): void
    {
        $keys = array_filter(array_map(
            fn (CalculatorDefinition $definition): ?string => $definition->entitlement(),
            (new CalculatorRegistry)->all(),
        ));

        config(['beam.core.entitlements.keys' => array_values(array_unique([
            ...(array) config('beam.core.entitlements.keys', []),
            ...$keys,
        ]))]);
    }

    /** Documentation publication follows the package's operator authoring gate. */
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
