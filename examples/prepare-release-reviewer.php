<?php

// Explicit disposable tutorial setup, not a membership or authorization API.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = config('database.connections.sqlite.database');
$url = parse_url((string) config('app.url'));
$file = storage_path('app/private/approval-actors.json');
if (count($argv) !== 1 || ! app()->environment('local') || config('database.default') !== 'sqlite'
    || ! is_string($database) || ! is_file($database) || basename($database) !== 'service-desk.sqlite'
    || realpath(dirname($database)) !== realpath(database_path())
    || ($url['scheme'] ?? '') !== 'http' || ($url['host'] ?? '') !== '127.0.0.1' || ! isset($url['port'])
    || config('session.driver') !== 'file' || config('queue.default') !== 'sync' || config('mail.default') !== 'array'
    || ! is_file($file) || is_link($file) || (fileperms($file) & 0077) !== 0) {
    fwrite(STDERR, "Use the disposable local approval-actors guide fixture.\n");
    exit(1);
}
$json = file_get_contents($file);
if ($json === false) {
    throw new RuntimeException('The private fixture could not be read.');
}
$fixture = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
$names = ['owner', 'admin', 'member', 'outsider', 'teamless', 'operator', 'theme-owner'];
$users = [];
foreach ($names as $name) {
    $user = App\Models\User::whereKey($fixture['actors'][$name]['id'] ?? null)->first();
    if ($user === null || $user->email !== $name.'@request-approval.example.invalid') {
        throw new RuntimeException('The exact disposable actor set is required.');
    }
    $users[$name] = $user;
}
$team = Splicewire\Beam\Accounts\Models\Team::whereKey($fixture['teams']['theme-owner'] ?? null)->first();
if (App\Models\User::count() !== count($names) || $team === null || $team->getAttribute('name') !== 'Approval theme-owner team'
    || Splicewire\Beam\Accounts\Models\Team::count() !== 4) {
    throw new RuntimeException('The exact disposable team set is required.');
}
Illuminate\Support\Facades\DB::transaction(fn () => app(Splicewire\Beam\Accounts\Teams\TeamProvisioner::class)->addMember(
    $users['admin'], $team, Splicewire\Beam\Accounts\Enums\Role::Admin));
echo "The existing disposable reviewer joined the Theme team as Admin.\n";
