<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Rushing\PermissionCascade\Facades\PermissionNamer;
use Spatie\Permission\PermissionRegistrar;
use Splicewire\Beam\Accounts\Authorization\RolePermissions;
use Splicewire\Beam\Models\BeamSchema;
use Splicewire\Beam\Models\GitRepo;

// Data migration (ux-walkthrough UX-08c). `GitRepo` and `BeamSchema` now reserve their tokens (GrantedExplicitly), and this
// host grants them to its team owner and admin by name (config/beam/accounts.php `roles.grants`). A role row derived
// before that holds the old uniform tiering, a member's `git-repo.view` and `beam-schema.view` included.
//
// TARGETED, by review-r1's rulings: it moves ONLY the `git-repo.*` and `beam-schema.*` tokens, and ONLY on the tier roles the
// old uniform derivation fed (the keys of RolePermissions::DEFAULT_ABILITIES: owner, admin, member, ...), to what this
// host now grants. Any other token, and any role outside the tiers (an operator's own 'developer' role granted
// git-repo.view, a global staff role), is never touched, either way. The full re-derivation stays with
// RolePermissionsSeeder / `splicewire:beam:seed`.
//
// What it does NOT preserve (build.qa): on a TIER role, an in-family token granted by hand is replaced by what the host
// grants, since up() sets the family to exactly explicitTokensFor(role). down() re-grants the uniform tiers, so it is not
// an exact inverse: it cannot restore such a hand grant. Back up the permission tables before running this on a live
// database; that dump, not down(), is the rollback.
return new class extends Migration
{
    public function up(): void
    {
        $this->move(fn (string $role): array => app(RolePermissions::class)->explicitTokensFor($role));
    }

    /** Back to the uniform tiering for these two families: what every role held before. */
    public function down(): void
    {
        $this->move(fn (string $role): array => [
            ...PermissionNamer::names(GitRepo::class, RolePermissions::DEFAULT_ABILITIES[$role] ?? []),
            ...PermissionNamer::names(BeamSchema::class, RolePermissions::DEFAULT_ABILITIES[$role] ?? []),
        ]);
    }

    /** @param  Closure(string): list<string>  $target  the tokens a role should hold, by role name */
    private function move(Closure $target): void
    {
        $roleModel = config('permission.models.role');
        $permissionModel = config('permission.models.permission');

        if (! Schema::hasTable((new $roleModel)->getTable())) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $teams = config('permission.teams') ? config('permission.column_names.team_foreign_key', 'team_id') : null;

        foreach ($roleModel::query()->get() as $role) {
            if (! array_key_exists((string) $role->name, RolePermissions::DEFAULT_ABILITIES)) {
                continue;
            }
            if ($teams !== null) {
                $registrar->setPermissionsTeamId($role->{$teams});
            }

            $want = array_values(array_filter($target((string) $role->name), $this->inFamily(...)));
            $have = $role->permissions()->pluck('name')->filter($this->inFamily(...))->values()->all();

            foreach (array_diff($have, $want) as $token) {
                $role->revokePermissionTo($token);
            }
            foreach (array_diff($want, $have) as $token) {
                $role->givePermissionTo($permissionModel::findOrCreate($token, $role->guard_name));
            }
        }

        $registrar->forgetCachedPermissions();
    }

    private function inFamily(string $token): bool
    {
        return str_starts_with($token, 'git-repo.') || str_starts_with($token, 'beam-schema.');
    }
};
