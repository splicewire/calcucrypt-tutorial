<?php

// Fresh private local tutorial only. All listing/review/acquisition effects happen through the UI/API.

use App\Models\Permission;
use App\Models\Role as PermissionRole;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Language;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Facades\BeamAccounts;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$output = $argv[1] ?? null;
$database = config('database.connections.sqlite.database');
$url = parse_url((string) config('app.url'));
if (! is_string($output) || file_exists($output) || is_link($output)
    || ! app()->environment('local') || ! config('local-marketplace.enabled')
    || config('database.default') !== 'sqlite' || ! is_string($database) || ! is_file($database)
    || basename($database) !== 'service-desk.sqlite'
    || realpath(dirname($database)) !== realpath(database_path())
    || realpath(dirname($output)) !== realpath(storage_path('app/private'))
    || ($url['scheme'] ?? null) !== 'http' || ($url['host'] ?? null) !== '127.0.0.1' || ! isset($url['port'])
    || config('queue.default') !== 'sync' || config('session.driver') !== 'file' || config('mail.default') !== 'array'
    || User::query()->exists() || Team::query()->exists()) {
    fwrite(STDERR, "Use a fresh enabled local marketplace SQLite fixture and a new private output path.\n");
    exit(1);
}

$actors = [];
foreach (['creator', 'admin', 'member', 'outside', 'operator', 'reviewer'] as $name) {
    $password = bin2hex(random_bytes(20));
    $user = User::factory()->create([
        'name' => 'Marketplace '.ucfirst($name), 'email' => $name.'@local-marketplace.example.invalid',
        'password' => $password, 'email_verified_at' => now(),
    ]);
    $actors[$name] = ['id' => $user->id, 'email' => $user->email, 'password' => $password,
        'token' => $user->createToken('local-marketplace-example')->plainTextToken];
}
$provisioner = app(TeamProvisioner::class);
$teamA = $provisioner->createTeamFor(User::findOrFail($actors['creator']['id']), 'SLA creator team');
$teamB = $provisioner->createTeamFor(User::findOrFail($actors['outside']['id']), 'SLA consumer team');
$reviewTeam = $provisioner->createTeamFor(User::findOrFail($actors['reviewer']['id']), 'Reviewer team');
foreach (['admin' => Role::Admin, 'member' => Role::Member] as $name => $role) {
    $user = User::findOrFail($actors[$name]['id']);
    $provisioner->addMember($user, $teamA, $role);
    $user->switchTeam($teamA);
}
$operator = User::findOrFail($actors['operator']['id']);
$provisioner->addMember($operator, $teamB, Role::Admin);
$operator->switchTeam($teamB);
setPermissionsTeamId($teamB->id);
$operator->givePermissionTo(Permission::findOrCreate('marketplace.host-operator', 'web'));

// This is the package's real central Root role, not a host Gate override or Owner-as-Root alias.
setPermissionsTeamId(null);
$reviewer = User::findOrFail($actors['reviewer']['id']);
$reviewer->assignRole(PermissionRole::findOrCreate('Root', 'web'));
if (! BeamAccounts::isRoot($reviewer)) {
    throw new RuntimeException('The configured Accounts root mechanism did not admit the reviewer.');
}
setPermissionsTeamId(null);

Language::factory()->create(['code' => 'en', 'default' => true]);
Currency::factory()->create(['code' => 'USD', 'default' => true]);
app(Database\Seeders\ServiceRequestApprovalSeeder::class)->run();

foreach ($actors as &$actor) {
    $actor['teamId'] = User::findOrFail($actor['id'])->current_team_id;
}
unset($actor);
$payload = ['schema' => 'local-marketplace-fixture/v1', 'root' => realpath(base_path()),
    'database' => realpath($database), 'actors' => $actors,
    'teams' => ['creator' => $teamA->id, 'consumer' => $teamB->id, 'reviewer' => $reviewTeam->id],
    'simulatedCallback' => (bool) config('beam.market.repo_authorization.simulated_callback')];
$mask = umask(0077);
$file = fopen($output, 'x');
umask($mask);
if ($file === false) {
    throw new RuntimeException('Could not create private fixture file.');
}
fwrite($file, json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n");
fclose($file);
echo "Created local marketplace tutorial actors; credentials are in the requested private file.\n";
