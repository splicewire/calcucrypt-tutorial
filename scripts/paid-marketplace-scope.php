<?php

// Setup-only scope selection for the same tutorial buyer; not a claimed marketplace team selector UI.
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Splicewire\Beam\Accounts\Models\Team;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, 'Sandbox scope setup failed ('.$error::class.").\n");
    exit(1);
});
$choice = $argv[1] ?? null;
$path = storage_path('app/private/paid-marketplace.json');
$database = config('database.connections.sqlite.database');
if (! app()->environment('local') || ! config('local-marketplace.payment_sandbox')
    || config('database.default') !== 'sqlite' || ! is_string($database)
    || realpath(dirname($database)) !== realpath(database_path()) || basename($database) !== 'service-desk.sqlite'
    || ! in_array($choice, ['consumer', 'alternate'], true) || ! is_file($path)) {
    fwrite(STDERR, "Use the fresh private paid sandbox and exactly consumer or alternate.\n");
    exit(1);
}
$fixture = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
if ($fixture['root'] !== realpath(base_path()) || $fixture['database'] !== realpath($database)) {
    throw new RuntimeException('Fixture identity mismatch.');
}
$buyer = User::findOrFail($fixture['actors']['buyer']['id']);
$team = Team::findOrFail($fixture['teams'][$choice]);
if (! $team->memberships()->where('user_id', $buyer->id)->exists()) {
    throw new RuntimeException('Buyer is not a current member of that scope.');
}
$buyer->switchTeam($team);
if ((string) $buyer->fresh()->current_team_id !== (string) $team->id) {
    throw new RuntimeException('Selected scope was not persisted.');
}
echo json_encode(['buyerRef' => (string) $buyer->id, 'scopeRef' => 'team:'.$buyer->fresh()->current_team_id, 'setupOnly' => true], JSON_THROW_ON_ERROR)."\n";
