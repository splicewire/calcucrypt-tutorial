<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Splicewire\Beam\Ux\Models\BeamUxEntry;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            /* @chisel-2fa */
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            /* @end-chisel-2fa */
        ];
    }

    /**
     * Indicate that the user is STAFF. There is no staff FLAG to set — the `is_staff` column is
     * retired estate-wide (particle-identity-resources ticket 04); staff is `entitlement:os.operate`,
     * which this host's Gates/routes resolve through the realm-grant cascade (ACC-01). So the whole
     * state is `afterCreating`: a personal Team holding `manage` on every provisioned realm's root
     * ({@see TeamProvisioner::personalTeamWithFullReachFor()}), the same grant-cascade data any other
     * grantee reaches those abilities through.
     * `BeamUxEntry::rootFor('operator')` auto-vivifies the operator realm's root FIRST:
     * `personalTeamWithFullReachFor()` only grants realms already provisioned — an isolated test
     * creating a staff user with no prior nav-seed would otherwise find zero provisioned realms and
     * grant nothing.
     */
    public function staff(): static
    {
        return $this->afterCreating(function (User $user): void {
            BeamUxEntry::rootFor('operator');
            app(TeamProvisioner::class)->personalTeamWithFullReachFor($user);
        });
    }

    /**
     * Indicate that the user is an ordinary team MEMBER, as an accepted invitation makes one:
     * `Role::Member` on another user's team, with that team current. beam-accounts' `RolePermissions`
     * gives the member role `view` on every cascade-policed model, so this is the least-privileged
     * principal the tenant realm is written for. A bare `create()` belongs to no team and holds no
     * role, so it is refused every cascade-policed read; that is the posture of a user nobody has
     * admitted, not of a member.
     */
    public function teamMember(): static
    {
        return $this->afterCreating(function (User $user): void {
            $team = app(TeamProvisioner::class)->personalTeamFor(User::factory()->create());
            app(TeamProvisioner::class)->addMember($user, $team, Role::Member);
            $user->switchTeam($team);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        /* @chisel-2fa */
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
        /* @end-chisel-2fa */
    }
}
