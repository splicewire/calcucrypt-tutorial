<?php

// Issue only private deterministic provider configuration in the fresh tutorial host.
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$port = filter_var($argv[1] ?? '', FILTER_VALIDATE_INT);
$database = config('database.connections.sqlite.database');
$url = parse_url((string) config('app.url'));
$private = storage_path('app/private');
$actorsFile = $private.'/approval-actors.json';
$targets = array_map(fn ($name) => $private.'/'.$name, ['external-requests.json', 'external-provider-mode.json', 'external-provider-receipts.jsonl']);
if (count($argv) !== 2 || ! is_int($port) || $port < 1024 || $port > 65535
    || ! app()->environment('local') || config('database.default') !== 'sqlite'
    || ! is_string($database) || ! is_file($database) || basename($database) !== 'service-desk.sqlite'
    || realpath(dirname($database)) !== realpath(database_path())
    || ($url['scheme'] ?? '') !== 'http' || ($url['host'] ?? '') !== '127.0.0.1' || ! isset($url['port'])
    || config('session.driver') !== 'file' || config('queue.default') !== 'sync' || config('mail.default') !== 'array'
    || ! is_file($actorsFile) || is_link($actorsFile)
    || array_filter($targets, fn ($file) => file_exists($file) || is_link($file)) !== []) {
    fwrite(STDERR, "Use a fresh private approval tutorial host and a free explicit provider port.\n");
    exit(1);
}
$actors = json_decode(file_get_contents($actorsFile), true, flags: JSON_THROW_ON_ERROR);
foreach (['owner', 'outsider', 'member'] as $name) {
    $user = User::findOrFail($actors['actors'][$name]['id']);
    if ($user->email !== $name.'@request-approval.example.invalid') {
        throw new RuntimeException('Use the tutorial actor fixture.');
    }
}
// Allow one ordinary Member to demonstrate the supported A/B/A team-selection journey.
app(TeamProvisioner::class)->addMember(User::findOrFail($actors['actors']['member']['id']), Team::findOrFail($actors['teams']['outsider']), Role::Member);
$fixture = ['baseUrl' => 'http://127.0.0.1:'.$port, 'token' => bin2hex(random_bytes(24)), 'sources' => [
    (string) $actors['teams']['owner'] => ['id' => 'fixture-desk-a', 'context' => 'a'],
    (string) $actors['teams']['outsider'] => ['id' => 'fixture-desk-b', 'context' => 'b'],
]];
foreach ([$fixture, ['mode' => 'ready'], null] as $i => $value) {
    $mask = umask(0077);
    $out = fopen($targets[$i], 'x');
    umask($mask);
    if ($out === false) {
        throw new RuntimeException('Could not create private fixture.');
    }
    if ($value !== null) {
        fwrite($out, json_encode($value, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    }
    fclose($out);
}
echo "Created private loopback provider configuration and A/B tutorial source mapping.\n";
