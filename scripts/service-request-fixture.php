<?php

// Local tutorial actors/tokens, written privately rather than printed.

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$output = $argv[1] ?? null;
$database = config('database.connections.sqlite.database');
if (! is_string($output) || file_exists($output)
    || ! app()->environment('local', 'testing')
    || config('database.default') !== 'sqlite'
    || ! is_string($database) || ! is_file($database)
    || basename($database) !== 'service-desk.sqlite'
    || realpath(dirname($database)) !== realpath(database_path())
    || realpath(dirname($output)) !== realpath(storage_path('app/private'))) {
    fwrite(STDERR, "Use a fresh local SQLite fixture and a new private output path.\n");
    exit(1);
}

$shared = in_array('--shared', array_slice($argv, 2), true);
$team = $shared || in_array('--team', array_slice($argv, 2), true);
$browser = $team || in_array('--browser', array_slice($argv, 2), true);
$actors = [];
foreach (($team ? ['owner', 'other', 'outsider', 'teamless'] : ['owner', 'other']) as $name) {
    $password = bin2hex(random_bytes(20));
    $user = User::factory()->create([
        'name' => 'Service desk '.$name,
        ...($team ? ['email' => $name.'@service-desk.example.test'] : []),
        ...($browser ? ['password' => $password, 'email_verified_at' => now()] : []),
    ]);
    $actors[$name] = ['id' => $user->id, 'token' => $user->createToken('service-request-example')->plainTextToken];
    if ($browser) {
        $actors[$name] += ['email' => $user->email, 'password' => $password];
    }
}

if ($shared) {
    $provisioner = app(TeamProvisioner::class);
    $owner = User::findOrFail($actors['owner']['id']);
    $member = User::findOrFail($actors['other']['id']);
    $teamA = $provisioner->createTeamFor($owner, 'Service desk A');
    $provisioner->addMember($member, $teamA, Role::Member);
    $member->switchTeam($teamA);
    $outsider = User::findOrFail($actors['outsider']['id']);
    $teamB = $provisioner->createTeamFor($outsider, 'Service desk B');
    $actors['owner']['teamId'] = $teamA->id;
    $actors['other']['teamId'] = $teamA->id;
    $actors['outsider']['teamId'] = $teamB->id;
}

// Create exclusively with private permissions; never expose credentials in the public directory.
$oldMask = umask(0077);
$file = fopen($output, 'x');
umask($oldMask);
if ($file === false) {
    throw new RuntimeException('Could not create the private fixture file.');
}
fwrite($file, json_encode($actors, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n");
fclose($file);
echo "Created tutorial actors; credentials written to the requested private file.\n";
