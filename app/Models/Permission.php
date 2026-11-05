<?php

namespace App\Models;

use Splicewire\Beam\Accounts\Models\Permission as BeamAccountsPermission;

/**
 * This host's Permission, extending `laravel-beam-accounts`' uuid-keyed pair — see {@see Role} for
 * why the class stays in `App\Models` and {@see BeamAccountsPermission} for the uuid rationale.
 */
class Permission extends BeamAccountsPermission {}
