<?php

// Explicit private tutorial setup. This is not an approval or grant-management API.

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Splicewire\Beam\Ux\Models\BeamUxEntry;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$file = $argv[1] ?? '';
$database = config('database.connections.sqlite.database');
$url = parse_url((string) config('app.url'));
if (count($argv) !== 2 || file_exists($file) || is_link($file)
    || ! app()->environment('local') || config('database.default') !== 'sqlite'
    || ! is_string($database) || ! is_file($database) || basename($database) !== 'service-desk.sqlite'
    || realpath(dirname($database)) !== realpath(database_path())
    || realpath(dirname($file)) !== realpath(storage_path('app/private'))
    || ($url['scheme'] ?? '') !== 'http' || ($url['host'] ?? '') !== '127.0.0.1' || ! isset($url['port'])
    || config('session.driver') !== 'file' || config('queue.default') !== 'sync' || config('mail.default') !== 'array'
    || User::query()->exists() || Team::query()->exists()) {
    fwrite(STDERR, "Use a fresh local service-desk.sqlite host and a new private output path.\n");
    exit(1);
}
$actors = [];
foreach (['owner', 'admin', 'member', 'outsider', 'teamless', 'operator', 'theme-owner'] as $name) {
    $password = bin2hex(random_bytes(20));
    $user = User::factory()->create([
        'name' => 'Approval '.$name, 'email' => $name.'@request-approval.example.invalid',
        'password' => $password, 'email_verified_at' => now(),
    ]);
    $actors[$name] = ['id' => $user->id, 'email' => $user->email, 'password' => $password,
        'token' => $user->createToken('request-approval')->plainTextToken];
}
$provisioner = app(TeamProvisioner::class);
$teams = [];
foreach (['owner', 'outsider', 'operator', 'theme-owner'] as $name) {
    $team = $provisioner->createTeamFor(User::findOrFail($actors[$name]['id']), 'Approval '.$name.' team');
    $teams[$name] = $team->id;
}
foreach (['admin' => Role::Admin, 'member' => Role::Member] as $name => $role) {
    $user = User::findOrFail($actors[$name]['id']);
    $provisioner->addMember($user, Team::findOrFail($teams['owner']), $role);
    $user->switchTeam(Team::findOrFail($teams['owner']));
}
$grants = app(AccessGrants::class);
$grants->share(BeamUxEntry::rootFor('operator'), Team::findOrFail($teams['operator']), AccessGrant::ABILITY_MANAGE);
$grants->share(BeamUxEntry::rootFor('site'), Team::findOrFail($teams['theme-owner']), AccessGrant::ABILITY_MANAGE);
$mask = umask(0077);
$output = fopen($file, 'x');
umask($mask);
if ($output === false) {
    throw new RuntimeException('Could not create the private actor file.');
}
fwrite($output, json_encode(['actors' => $actors, 'teams' => $teams], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
fclose($output);
echo "Created private approval tutorial actors. Credentials stay in the requested private file.\n";
