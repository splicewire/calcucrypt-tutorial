<?php

// Explicit local policy fixtures; no HTTP endpoint, global ActorPort or Laravel bootstrap.

use Examples\KeyedRegistry\Example;

try {
    if ($argc !== 2 || ! in_array($argv[1], ['reader', 'operator', 'trusted-tooling-reader'], true)) {
        throw new InvalidArgumentException('Unknown local fixture');
    }
    $autoload = dirname(__DIR__, 2).'/vendor/autoload.php';
    if (! is_file($autoload)) {
        throw new RuntimeException('Developer dependencies unavailable');
    }
    require $autoload;
    require __DIR__.'/Example.php';
    echo json_encode(Example::view($argv[1]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable) {
    fwrite(STDERR, "Local registry example is invalid or incomplete; no caller view was produced.\n");
    exit(2);
}
