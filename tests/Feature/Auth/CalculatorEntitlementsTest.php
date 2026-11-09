<?php

use App\Content\Calculators\CalculatorDefinition;
use App\Content\Calculators\CalculatorRegistry;
use App\Models\Calculator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Rushing\PermissionCascade\Contracts\EntitlementResolver;
use Splicewire\Beam\Accounts\Entitlements\DefaultEntitlementResolver;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;

uses(Tests\TestCase::class, RefreshDatabase::class);

// The tutorial's subset: one trader calculator and one tax calculator (Calcucrypt has three each).
const TRADER_KEYS = [
    'calculators.crypto-position-size',
];

const TAX_KEYS = [
    'calculators.crypto-capital-gains',
];

/** A team on the given plan, owned by a fresh user and set as that owner's current team. */
function teamOnPlan(string $plan, ?User $owner = null): Team
{
    $team = app(TeamProvisioner::class)->createTeamFor($owner ?? User::factory()->create(), ucfirst($plan).' team');
    $team->forceFill(['plan' => $plan])->save();

    return $team;
}

/** A fresh user who is a Member of the team and working in it. */
function memberOf(Team $team): User
{
    $member = User::factory()->create();
    app(TeamProvisioner::class)->addMember($member, $team, Role::Member);
    $member->switchTeam($team);

    return $member;
}

/** @return list<string> */
function entitlementsOf(?User $user): array
{
    return app(EntitlementResolver::class)->entitlementsFor($user);
}

/** @return list<string> */
function calculatorKeysOf(?User $user): array
{
    $keys = array_values(array_filter(entitlementsOf($user), fn (string $key): bool => str_starts_with($key, 'calculators.')));
    sort($keys);

    return $keys;
}

it('makes the trader and tax calculators paid, and every other calculator free', function (): void {
    $keys = [];
    foreach (app(CalculatorRegistry::class)->all() as $definition) {
        $keys[$definition->slug()] = $definition->entitlement();
    }

    expect($keys)->toHaveCount(4)
        ->and(array_filter($keys))->toHaveCount(2)
        ->and(array_values(array_filter($keys)))->toEqualCanonicalizing([...TRADER_KEYS, ...TAX_KEYS]);

    foreach (app(CalculatorRegistry::class)->all() as $definition) {
        $paid = in_array($definition->category(), ['trader', 'tax'], true);

        expect($definition->entitlement())->toBe($paid ? 'calculators.'.$definition->slug() : null);
    }
});

it('declares the trader and pro bundles over exactly the calculators that need them', function (): void {
    $declared = array_values(array_filter(array_map(
        fn (CalculatorDefinition $definition): ?string => $definition->entitlement(),
        app(CalculatorRegistry::class)->all(),
    )));

    expect(config('beam.accounts.entitlements.bundles.trader'))->toEqualCanonicalizing(TRADER_KEYS)
        ->and(config('beam.accounts.entitlements.bundles.pro'))->toEqualCanonicalizing($declared);
});

it('defines an entitlement gate for every paid calculator', function (): void {
    foreach ([...TRADER_KEYS, ...TAX_KEYS] as $key) {
        expect(Gate::has('entitlement:'.$key))->toBeTrue();
    }

    expect(Gate::has('entitlement:os.operate'))->toBeTrue();
});

it('gives a team the free plan by default', function (): void {
    $team = teamOnPlan('free');

    expect(Team::query()->find($team->getKey())->plan)->toBe('free')
        ->and(app(TeamProvisioner::class)->createTeamFor(User::factory()->create(), 'New')->refresh()->plan)->toBe('free');
});

it('projects each definition\'s entitlement onto its calculator row', function (): void {
    $this->artisan('content:sync')->assertSuccessful();

    expect(Calculator::query()->where('slug', 'crypto-position-size')->value('entitlement'))->toBe('calculators.crypto-position-size')
        ->and(Calculator::query()->where('slug', 'crypto-capital-gains')->value('entitlement'))->toBe('calculators.crypto-capital-gains')
        ->and(Calculator::query()->where('slug', 'crypto-profit')->value('entitlement'))->toBeNull()
        ->and(Calculator::query()->whereNotNull('entitlement')->count())->toBe(2);
});

describe('who holds which calculator keys', function (): void {
    it('gives a guest nothing', function (): void {
        expect(entitlementsOf(null))->toBe([])
            ->and(Gate::allows('entitlement:calculators.crypto-position-size'))->toBeFalse();
    });

    it('gives a Member of a free team no calculator keys', function (): void {
        $member = memberOf(teamOnPlan('free'));

        expect(calculatorKeysOf($member))->toBe([])
            ->and(Gate::forUser($member)->allows('entitlement:calculators.crypto-position-size'))->toBeFalse();
    });

    it('gives a Member of a trader team exactly the three trader keys', function (): void {
        $member = memberOf(teamOnPlan('trader'));

        expect(entitlementsOf($member))->toEqualCanonicalizing(TRADER_KEYS)
            ->and(Gate::forUser($member)->allows('entitlement:calculators.crypto-position-size'))->toBeTrue()
            ->and(Gate::forUser($member)->allows('entitlement:calculators.crypto-capital-gains'))->toBeFalse();
    });

    it('gives the Owner of a pro team all six paid keys', function (): void {
        $owner = User::factory()->create();
        teamOnPlan('pro', $owner);

        expect(calculatorKeysOf($owner->refresh()))->toHaveCount(2)
            ->and(calculatorKeysOf($owner))->toEqualCanonicalizing([...TRADER_KEYS, ...TAX_KEYS])
            ->and(Gate::forUser($owner)->allows('entitlement:calculators.crypto-position-size'))->toBeTrue()
            ->and(Gate::forUser($owner)->allows('entitlement:calculators.crypto-capital-gains'))->toBeTrue();
    });

    it('reads the current team only, so a pro member working in a free team gets free', function (): void {
        $pro = teamOnPlan('pro');
        $free = teamOnPlan('free');
        $user = User::factory()->create();
        app(TeamProvisioner::class)->addMember($user, $pro, Role::Member);
        app(TeamProvisioner::class)->addMember($user, $free, Role::Member);
        $user->switchTeam($free);

        expect($user->belongsToTeam($pro))->toBeTrue()
            ->and(calculatorKeysOf($user->refresh()))->toBe([])
            ->and(Gate::forUser($user)->allows('entitlement:calculators.crypto-position-size'))->toBeFalse();

        $user->switchTeam($pro);

        expect(calculatorKeysOf($user->refresh()))->toHaveCount(2);
    });
});

describe('operator access is unchanged', function (): void {
    it('still gives an Owner with realm grants os.operate, on a free plan', function (): void {
        $owner = User::factory()->create();
        app(TeamProvisioner::class)->personalTeamWithFullReachFor($owner);

        expect(entitlementsOf($owner->refresh()))->toContain('os.enter', 'os.operate')
            ->and(entitlementsOf($owner))->toEqualCanonicalizing([
                ...app(DefaultEntitlementResolver::class)->entitlementsFor($owner),
                'plans.purchase',
            ])
            ->and(Gate::forUser($owner)->allows('entitlement:os.operate'))->toBeTrue();
    });

    it('still withholds os.operate from a Member of that team', function (): void {
        $owner = User::factory()->create();
        $team = app(TeamProvisioner::class)->personalTeamWithFullReachFor($owner);
        $member = memberOf($team);

        expect(entitlementsOf($member))->not->toContain('os.operate')
            ->and(Gate::forUser($member)->allows('entitlement:os.operate'))->toBeFalse();
    });

    it('adds the plan to the operator keys rather than replacing them', function (): void {
        $owner = User::factory()->create();
        $team = app(TeamProvisioner::class)->personalTeamWithFullReachFor($owner);
        $team->forceFill(['plan' => 'trader'])->save();

        expect(entitlementsOf($owner->refresh()))->toContain('os.operate', ...TRADER_KEYS)
            ->and(Gate::forUser($owner)->allows('entitlement:os.operate'))->toBeTrue();
    });
});
