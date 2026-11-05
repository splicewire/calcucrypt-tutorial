<?php

// Disposable local tutorial only. This is not an account/grant-management API.

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Rushing\PermissionCascade\Contracts\EntitlementResolver;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Splicewire\Beam\Ux\Theme\ThemeResolver;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function refuse(): never
{
    fwrite(STDERR, "Use setup|inspect|grant|deny-row|revoke with this fresh local tutorial's private file.\n");
    exit(1);
}

$mode = $argv[1] ?? '';
$file = $argv[2] ?? '';
$database = config('database.connections.sqlite.database');
$url = parse_url((string) config('app.url'));
if (! in_array($mode, ['setup', 'inspect', 'grant', 'deny-row', 'revoke'], true)
    || ! app()->environment('local') || config('database.default') !== 'sqlite'
    || ! is_string($database) || ! is_file($database)
    || basename($database) !== 'entitlement-guide.sqlite'
    || realpath(dirname($database)) !== realpath(database_path())
    || realpath(dirname($file)) !== realpath(storage_path('app/private'))
    || ($url['scheme'] ?? '') !== 'http' || ($url['host'] ?? '') !== '127.0.0.1' || ! isset($url['port'])
    || config('session.driver') !== 'file' || config('queue.default') !== 'sync'
    || config('mail.default') !== 'log') {
    refuse();
}

$theme = BeamUxEntry::query()->where('namespace', ThemeResolver::NAMESPACE)->where('slug', ThemeResolver::SLUG)->first();
if ($theme === null) {
    fwrite(STDERR, "Seed the existing ThemeSeeder in the new fixture first.\n");
    exit(1);
}
$grants = app(AccessGrants::class);
if ($mode === 'setup') {
    if (file_exists($file) || is_link($file) || User::query()->exists() || Team::query()->exists()) {
        refuse();
    }
    $actors = [];
    foreach (['owner', 'admin', 'member', 'outsider', 'teamless', 'aggregate'] as $name) {
        $password = bin2hex(random_bytes(20));
        $user = User::factory()->create([
            'name' => 'Entitlement '.$name, 'email' => $name.'@entitlement-guide.example.test',
            'password' => $password, 'email_verified_at' => now(),
        ]);
        $actors[$name] = ['id' => $user->id, 'email' => $user->email, 'password' => $password,
            'token' => $user->createToken('entitlement-guide')->plainTextToken];
    }
    $provisioner = app(TeamProvisioner::class);
    $owner = User::findOrFail($actors['owner']['id']);
    $teamA = $provisioner->createTeamFor($owner, 'Entitlement tutorial A');
    $teamB = $provisioner->createTeamFor(User::findOrFail($actors['outsider']['id']), 'Entitlement tutorial B');
    foreach (['admin' => Role::Admin, 'member' => Role::Member, 'aggregate' => Role::Owner] as $name => $role) {
        $user = User::findOrFail($actors[$name]['id']);
        $provisioner->addMember($user, $teamA, $role);
        $user->switchTeam($teamA);
    }
    $aggregate = User::findOrFail($actors['aggregate']['id']);
    $provisioner->addMember($aggregate, $teamB, Role::Member);
    $aggregate->switchTeam($teamB);
    $root = BeamUxEntry::rootFor('site');
    // A different ability is a deliberate control: tutorial revoke must preserve this row.
    $unrelated = $grants->share($root, $teamA, AccessGrant::ABILITY_VIEW);
    $state = ['schema' => 'entitlement-guide/v1', 'root' => realpath(base_path()),
        'database' => realpath($database), 'siteRootId' => (string) $root->getKey(),
        'teamA' => $teamA->id, 'teamB' => $teamB->id, 'unrelatedGrantId' => (string) $unrelated->getKey(),
        'actors' => $actors];
    $mask = umask(0077);
    $output = fopen($file, 'x');
    umask($mask);
    if ($output === false) {
        refuse();
    }
    fwrite($output, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    fclose($output);
} else {
    if (! is_file($file) || is_link($file)) {
        refuse();
    }
    try {
        $state = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        refuse();
    }
    if (($state['schema'] ?? null) !== 'entitlement-guide/v1'
        || ($state['root'] ?? null) !== realpath(base_path())
        || ($state['database'] ?? null) !== realpath($database)) {
        refuse();
    }
    $root = BeamUxEntry::query()->whereKey($state['siteRootId'])->where('namespace', 'realms')->where('slug', 'site')->where('realm', 'site')->first();
    $teamA = Team::query()->whereKey($state['teamA'])->where('name', 'Entitlement tutorial A')
        ->where('user_id', $state['actors']['owner']['id'])->first();
    if ($root === null || $teamA === null) {
        refuse();
    }
    foreach ($state['actors'] as $name => $actor) {
        if (! User::query()->whereKey($actor['id'])->where('email', $name.'@entitlement-guide.example.test')->exists()) {
            refuse();
        }
    }
    if ($mode === 'grant') {
        $grants->share($root, $teamA, AccessGrant::ABILITY_MANAGE);
    } elseif ($mode === 'deny-row') {
        $grants->deny($root, $teamA, AccessGrant::ABILITY_MANAGE);
    } elseif ($mode === 'revoke') {
        $grants->revoke($root, $teamA, AccessGrant::ABILITY_MANAGE);
    }
}

$grantModel = config('permission-cascade.grant_model');
$scopedGrants = $grantModel::query()->where('grantable_type', $root->getMorphClass())
    ->where('grantable_id', (string) $root->getKey())->where('grantee_type', $teamA->getMorphClass())
    ->where('grantee_id', (string) $teamA->getKey())->orderBy('id')->get(['id', 'ability', 'effect']);

// Safe summary only. Credentials remain exclusively in the private state file.
$result = ['teamA' => $state['teamA'], 'teamB' => $state['teamB'], 'siteRootId' => $state['siteRootId'],
    'themeId' => (string) $theme->getKey(), 'resolver' => get_class(app(EntitlementResolver::class)),
    'grantModel' => $grantModel, 'scopedGrants' => $scopedGrants,
    'unrelatedGrantId' => $state['unrelatedGrantId'], 'actors' => []];
foreach ($state['actors'] as $name => $actor) {
    $user = User::findOrFail($actor['id']);
    $result['actors'][$name] = ['id' => $user->id, 'currentTeamId' => $user->getAttribute('current_team_id'),
        'keys' => app(EntitlementResolver::class)->entitlementsFor($user)];
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
