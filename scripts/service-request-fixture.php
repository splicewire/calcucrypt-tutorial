<?php

// Local tutorial fixture only: two new actors/tokens, written privately rather than printed.

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

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

$actors = [];
foreach (['owner', 'other'] as $name) {
    $user = User::factory()->create(['name' => 'Service desk '.$name]);
    $actors[$name] = ['id' => $user->id, 'token' => $user->createToken('service-request-example')->plainTextToken];
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
echo "Created two tutorial actors; credentials written to the requested private file.\n";
