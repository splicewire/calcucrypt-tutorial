<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$output = $argv[1] ?? '';
$mode = $argv[2] ?? 'setup';
$db = config('database.connections.sqlite.database');
$url = parse_url((string) config('app.url'));
if (! app()->environment('local') || config('database.default') !== 'sqlite'
    || ! is_string($db) || ! is_file($db) || basename($db) !== 'wallet.sqlite'
    || realpath(dirname($db)) !== realpath(database_path())
    || realpath(dirname($output)) !== realpath(storage_path('app/private')) || is_link($output)
    || ($url['host'] ?? '') !== '127.0.0.1' || ! isset($url['port'])
    || ! config('team-wallet.enabled') || ! config('team-wallet.sandbox') || config('commerce.driver') !== 'fake') {
    fwrite(STDERR, "Use a fresh isolated local wallet fixture and private output.\n");
    exit(1);
}
if ($mode === 'setup') {
    if (file_exists($output) || User::query()->exists() || ! Schema::hasTable('workflow_transition_facts')
        || ! Schema::hasTable('commerce_credit_entries')) {
        fwrite(STDERR, "Empty actors and native workflow/credit migrations are prerequisites.\n");
        exit(1);
    }
    $actors = [];
    foreach (['owner', 'other', 'admin', 'member', 'teamless', 'root'] as $name) {
        $password = bin2hex(random_bytes(20));
        $user = User::factory()->create(['name' => 'Wallet '.$name, 'email' => $name.'@wallet.example.invalid',
            'password' => $password, 'email_verified_at' => now()]);
        $actors[$name] = ['id' => $user->id, 'email' => $user->email, 'password' => $password,
            'token' => $user->createToken('wallet-fixture')->plainTextToken];
    }
    $provisioner = app(TeamProvisioner::class);
    $a = $provisioner->createTeamFor(User::findOrFail($actors['owner']['id']), 'Support A');
    $b = $provisioner->createTeamFor(User::findOrFail($actors['other']['id']), 'Support B');
    foreach (['admin' => Role::Admin, 'member' => Role::Member] as $name => $role) {
        $user = User::findOrFail($actors[$name]['id']);
        $provisioner->addMember($user, $a, $role);
        $user->switchTeam($a);
    }
    $provisioner->addMember(User::findOrFail($actors['owner']['id']), $b, Role::Member);
    $registrar = app(PermissionRegistrar::class);
    $previous = $registrar->getPermissionsTeamId();
    try {
        $registrar->setPermissionsTeamId(null);
        $model = config('permission.models.role');
        User::findOrFail($actors['root']['id'])->assignRole($model::findOrCreate('Root', 'web'));
    } finally {
        $registrar->setPermissionsTeamId($previous);
    }
    $state = ['schema' => 'team-wallet-fixture/v1', 'root' => realpath(base_path()), 'actors' => $actors,
        'teams' => ['a' => $a->id, 'b' => $b->id]];
    $mask = umask(0077);
    $stream = fopen($output, 'x');
    umask($mask);
    if ($stream === false) {
        exit(1);
    }
    fwrite($stream, json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    fclose($stream);
    echo "Prepared six private actors; no credentials printed.\n";
} else {
    if (! is_file($output) || (fileperms($output) & 0077) !== 0) {
        exit(1);
    }
    $state = json_decode(file_get_contents($output), true, flags: JSON_THROW_ON_ERROR);
    if (($state['schema'] ?? '') !== 'team-wallet-fixture/v1' || $state['root'] !== realpath(base_path())) {
        exit(1);
    }
    if (in_array($mode, ['switch-a', 'switch-b'], true)) {
        $team = Splicewire\Beam\Accounts\Models\Team::findOrFail($state['teams'][$mode === 'switch-a' ? 'a' : 'b']);
        $actor = User::findOrFail($state['actors']['owner']['id']);
        $actor->switchTeam($team);
        if ((string) $actor->fresh()->current_team_id !== (string) $team->id) {
            exit(1);
        }
    } elseif ($mode === 'remove-member') {
        Splicewire\Beam\Accounts\Models\Team::findOrFail($state['teams']['a'])
            ->removeMember(User::findOrFail($state['actors']['member']['id']));
    } elseif (in_array($mode, ['credits-off', 'credits-on', 'facts-off', 'facts-on'], true)) {
        $table = str_starts_with($mode, 'credits') ? 'commerce_credit_entries' : 'workflow_transition_facts';
        $off = str_ends_with($mode, '-off');
        Schema::rename($off ? $table : $table.'_wallet_unavailable', $off ? $table.'_wallet_unavailable' : $table);
    } else {
        fwrite(STDERR, "Unknown fixture control.\n");
        exit(1);
    }
    echo "Applied bounded private fixture control.\n";
}
