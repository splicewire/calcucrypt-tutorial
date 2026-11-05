<?php

use Examples\MdxPreview\Preview;
use Examples\MdxPreview\PreviewFailure;

if ($argc !== 3 || $argv[1] !== '--root') {
    fwrite(STDERR, "ERROR usage: prepare.php --root <new-empty-fixture-path>\n");
    exit(2);
}
try {
    // Preparation needs no framework, environment, database or dependency execution.
    require __DIR__.'/PreviewExample.php';
    set_error_handler(static function (int $severity, string $message): never {
        throw new RuntimeException('fixture-create-failed');
    });
    Preview::prepare($argv[2]);
    fwrite(STDOUT, "Two sample files prepared in a new owned fixture root.\n");
} catch (Throwable $e) {
    $reason = $e instanceof PreviewFailure ? $e->getMessage() : 'fixture-create-failed';
    fwrite(STDERR, 'ERROR '.$reason."\n");
    exit(2);
}
