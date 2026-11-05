<?php

// Authored example CLI; no native topology:* command or Laravel bootstrap is implied.
require __DIR__.'/Example.php';

use Examples\DeclaredTopology\Example;
use Examples\DeclaredTopology\IncompleteMeasurement;

try {
    $options = getopt('', ['root:', 'fixture:']);
    if (! isset($options['root'], $options['fixture']) || ! is_string($options['root']) || ! is_string($options['fixture']) || count($options) !== 2) {
        throw new InvalidArgumentException('Supply root and fixture');
    }
    $autoload = dirname(__DIR__, 2).'/vendor/autoload.php';
    if (! is_file($autoload)) {
        throw new RuntimeException('Developer dependencies unavailable');
    }
    require $autoload;
    $result = Example::inspect($options['root'], $options['fixture']);
    echo json_encode(['status' => $result['violations'] === [] ? 'measured-clean' : 'violations', ...$result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    exit($result['violations'] === [] ? 0 : 1);
} catch (Throwable $error) {
    // No stack, arbitrary manifest metadata, credentials or host paths in the public diagnostic.
    $output = ['status' => 'invalid-or-incomplete', 'message' => 'Fixture input or graph measurement is invalid; inspect the bounded example inputs.'];
    if ($error instanceof IncompleteMeasurement) {
        $output['didNotLook'] = $error->measurement['didNotLook'];
        $output['unresolved'] = $error->measurement['unresolved'];
    }
    fwrite(STDERR, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    exit(2);
}
