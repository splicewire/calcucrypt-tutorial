<?php

// Local tutorial actors/tokens, written privately rather than printed.

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Rushing\PermissionCascade\Contracts\EntitlementResolver;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Facades\BeamAccounts;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Splicewire\Beam\Ux\Models\BeamUxEntry;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$output = $argv[1] ?? null;
$options = array_slice($argv, 2);
$operatorMode = null;
foreach ($options as $option) {
    if ($option === '--operator-inspection' || str_starts_with($option, '--operator-inspection=')) {
        $operatorMode = $option === '--operator-inspection' ? 'setup' : substr($option, strlen('--operator-inspection='));
    }
}
$database = config('database.connections.sqlite.database');
if (! is_string($output) || ($operatorMode === null && file_exists($output))
    || ! app()->environment('local', 'testing')
    || config('database.default') !== 'sqlite'
    || ! is_string($database) || ! is_file($database)
    || basename($database) !== 'service-desk.sqlite'
    || realpath(dirname($database)) !== realpath(database_path())
    || realpath(dirname($output)) !== realpath(storage_path('app/private'))) {
    fwrite(STDERR, "Use a fresh local SQLite fixture and a new private output path.\n");
    exit(1);
}

// This optional mode owns only private tutorial actors and the exact operator manage grant.
// Existing browser/team/shared modes below retain their original output shape.
if ($operatorMode !== null) {
    $url = parse_url((string) config('app.url'));
    $refuse = static function (): never {
        fwrite(STDERR, "Use --operator-inspection[=inspect|revoke|restore] in this fresh private local tutorial.\n");
        exit(1);
    };
    if (count($options) !== 1 || ! in_array($operatorMode, ['setup', 'inspect', 'revoke', 'restore'], true)
        || ! app()->environment('local') || ($url['scheme'] ?? '') !== 'http'
        || ($url['host'] ?? '') !== '127.0.0.1' || ! isset($url['port'])
        || config('session.driver') !== 'file' || config('queue.default') !== 'sync'
        || config('mail.default') !== 'array' || is_link($output)) {
        $refuse();
    }
    $grants = app(AccessGrants::class);
    if ($operatorMode === 'setup') {
        if (file_exists($output) || User::query()->exists() || Team::query()->exists()) {
            $refuse();
        }
        $actors = [];
        foreach (['operator', 'member', 'owner', 'outside', 'site-owner'] as $name) {
            $password = bin2hex(random_bytes(20));
            $user = User::factory()->create([
                'name' => 'Inspection '.$name, 'email' => $name.'@operator-inspection.example.invalid',
                'password' => $password, 'email_verified_at' => now(),
            ]);
            $actors[$name] = ['id' => $user->id, 'email' => $user->email, 'password' => $password,
                'token' => $user->createToken('operator-inspection')->plainTextToken];
        }
        $teams = [];
        $provisioner = app(TeamProvisioner::class);
        foreach (['operator', 'owner', 'outside', 'site-owner'] as $name) {
            $team = $provisioner->createTeamFor(User::findOrFail($actors[$name]['id']), 'Inspection '.$name.' team');
            $teams[$name] = $team->id;
        }
        $operatorTeam = Team::findOrFail($teams['operator']);
        $member = User::findOrFail($actors['member']['id']);
        $provisioner->addMember($member, $operatorTeam, Role::Member);
        $member->switchTeam($operatorTeam);
        $operatorRoot = BeamUxEntry::rootFor('operator');
        $siteRoot = BeamUxEntry::rootFor('site');
        $grants->share($operatorRoot, $operatorTeam, AccessGrant::ABILITY_MANAGE);
        $unrelated = $grants->share($operatorRoot, $operatorTeam, AccessGrant::ABILITY_VIEW);
        $siteGrant = $grants->share($siteRoot, Team::findOrFail($teams['site-owner']), AccessGrant::ABILITY_MANAGE);
        $state = ['schema' => 'operator-inspection/v1', 'root' => realpath(base_path()),
            'database' => realpath($database), 'operatorRootId' => (string) $operatorRoot->getKey(),
            'teams' => $teams, 'actors' => $actors,
            'unrelatedGrantId' => (string) $unrelated->getKey(), 'siteGrantId' => (string) $siteGrant->getKey()];
        $mask = umask(0077);
        $file = fopen($output, 'x');
        umask($mask);
        if ($file === false) {
            $refuse();
        }
        fwrite($file, json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n");
        fclose($file);
    } else {
        if (! is_file($output) || (fileperms($output) & 0077) !== 0) {
            $refuse();
        }
        try {
            $state = json_decode(file_get_contents($output), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $refuse();
        }
        if (! is_array($state) || ($state['schema'] ?? null) !== 'operator-inspection/v1'
            || ($state['root'] ?? null) !== realpath(base_path())
            || ($state['database'] ?? null) !== realpath($database)
            || array_keys($state['actors'] ?? []) !== ['operator', 'member', 'owner', 'outside', 'site-owner']
            || array_keys($state['teams'] ?? []) !== ['operator', 'owner', 'outside', 'site-owner']) {
            $refuse();
        }
        $operatorRoot = BeamUxEntry::query()->whereKey($state['operatorRootId'] ?? null)
            ->where('namespace', 'realms')->where('slug', 'operator')->where('realm', 'operator')->first();
        if ($operatorRoot === null) {
            $refuse();
        }
        foreach ($state['actors'] as $name => $actor) {
            $user = User::query()->whereKey($actor['id'] ?? null)->where('email', $name.'@operator-inspection.example.invalid')->first();
            if ($user === null || BeamAccounts::isRoot($user)) {
                $refuse();
            }
        }
        foreach ($state['teams'] as $name => $id) {
            if (! Team::query()->whereKey($id)->where('name', 'Inspection '.$name.' team')
                ->where('user_id', $state['actors'][$name]['id'])->exists()) {
                $refuse();
            }
        }
        $operatorTeam = Team::findOrFail($state['teams']['operator']);
        if ($operatorMode === 'revoke') {
            $grants->revoke($operatorRoot, $operatorTeam, AccessGrant::ABILITY_MANAGE);
        } elseif ($operatorMode === 'restore') {
            $grants->share($operatorRoot, $operatorTeam, AccessGrant::ABILITY_MANAGE);
        }
    }
    $grantModel = config('permission-cascade.grant_model');
    $summary = ['operatorRootId' => $state['operatorRootId'], 'teams' => $state['teams'],
        'resolver' => get_class(app(EntitlementResolver::class)), 'grantModel' => $grantModel,
        'unrelatedGrantId' => $state['unrelatedGrantId'], 'siteGrantId' => $state['siteGrantId'],
        'scopedGrants' => $grantModel::query()->where('grantable_type', $operatorRoot->getMorphClass())
            ->where('grantable_id', (string) $operatorRoot->getKey())->where('grantee_type', $operatorTeam->getMorphClass())
            ->where('grantee_id', (string) $operatorTeam->getKey())->orderBy('id')->get(['id', 'ability', 'effect']),
        'actors' => []];
    foreach ($state['actors'] as $name => $actor) {
        $user = User::findOrFail($actor['id']);
        $summary['actors'][$name] = ['id' => $user->id, 'root' => BeamAccounts::isRoot($user),
            'currentTeamId' => $user->getAttribute('current_team_id'),
            'keys' => app(EntitlementResolver::class)->entitlementsFor($user)];
    }
    echo json_encode($summary, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n";
    exit(0);
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
