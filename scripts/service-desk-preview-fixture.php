<?php

// Fail before provisioning actors when the cumulative preview is not a fresh private fixture.

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Splicewire\Beam\Accounts\Models\Team;

$output = $argv[1] ?? null;
$provisioningStarted = false;
$refuse = static function () use (&$provisioningStarted): never {
    fwrite(STDERR, $provisioningStarted
        ? "Preparation did not complete. Actors may already exist and the database may no longer be fresh; inspect this private fixture before any further command. Do not rerun actor creation.\n"
        : "Use a fresh local service-desk.sqlite and a new private output path after native installation and ApprovalSeeder.\n");
    exit(1);
};

if (! is_string($output) || count($argv) !== 2 || file_exists($output) || is_link($output)) {
    $refuse();
}

require __DIR__.'/../vendor/autoload.php';
try {
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $parent = realpath(dirname($output));
    $private = realpath(storage_path('app/private'));
    $database = config('database.connections.sqlite.database');
    if (! app()->environment('local', 'testing') || config('database.default') !== 'sqlite'
        || ! is_string($database) || ! is_file($database) || is_link($database)
        || basename($database) !== 'service-desk.sqlite'
        || realpath(dirname($database)) !== realpath(database_path())
        || $parent === false || $private === false || $parent !== $private
        || ! is_writable($parent) || (fileperms($parent) & 0077) !== 0
        || config('canonical-mdx.enabled') !== false
        || User::query()->exists() || Team::query()->exists()
        || ! Illuminate\Support\Facades\Schema::hasTable('service_requests')
        || Illuminate\Support\Facades\DB::table('service_requests')->exists()) {
        $refuse();
    }

    if (app(Splicewire\Beam\Workflows\Definition\DefinitionStore::class)
        ->activeVersion(App\Workflows\ServiceRequestApproval::LINEAGE) === null) {
        $refuse();
    }

    // The existing native helper owns users, teams, membership, selected teams and private credentials.
    // A fresh PHP process avoids bootstrapping Laravel twice in this process.
    $provisioningStarted = true;
    $command = [PHP_BINARY, __DIR__.'/service-request-fixture.php', $output, '--shared'];
    $process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, base_path());
    if (! is_resource($process)) {
        $refuse();
    }
    $status = proc_close($process);
    if ($status !== 0) {
        $refuse();
    }
    if (! is_file($output) || is_link($output) || (fileperms($output) & 0077) !== 0) {
        $refuse();
    }
    $actors = json_decode(file_get_contents($output), true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($actors) || array_keys($actors) !== ['owner', 'other', 'outsider', 'teamless']) {
        $refuse();
    }
    exit(0);
} catch (Throwable) {
    // Framework exception rendering is not evidence of a successful fixture command.
    $refuse();
}
