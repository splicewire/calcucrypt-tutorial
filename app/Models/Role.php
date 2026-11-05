<?php

namespace App\Models;

use Splicewire\Beam\Accounts\Models\Role as BeamAccountsRole;

/**
 * This host's Role, extending `laravel-beam-accounts`' uuid-keyed pair.
 *
 * The reason the key is a uuid — and why stock Spatie's Role cannot create one against
 * beam-accounts' `create_permission_tables` — is argued once, in {@see BeamAccountsRole}. It used to
 * be argued here, in three byte-identical copies across the starters (beam-facade ticket 98).
 *
 * The class stays in `App\Models` on purpose rather than being deleted in favour of pointing
 * `config('permission.models.role')` straight at the package: five hosts already bind
 * `App\Models\Role::class` in their own PUBLISHED `config/permission.php`, so deleting it would
 * require editing a published config at every one of them, and this is the seam a host expects to
 * find when it wants to add a trait or a relation. Ticket 59 is refined by that, not overturned —
 * app-namespace identity is still correct; it just stops re-deriving its reason.
 */
class Role extends BeamAccountsRole {}
