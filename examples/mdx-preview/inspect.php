<?php

use Examples\MdxPreview\Preview;
use Examples\MdxPreview\PreviewFailure;

// Argument errors never load dependencies or read a selected file.
if ($argc !== 5 || $argv[1] !== '--root' || $argv[3] !== '--file') {
    fwrite(STDERR, "ERROR usage: inspect.php --root <new-owned-fixture-root> --file <relative.mdx>\n");
    exit(2);
}
try {
    $autoload = dirname(__DIR__, 2).'/vendor/autoload.php';
    if (! is_file($autoload)) {
        throw new RuntimeException('bootstrap');
    }
    require $autoload;
    require __DIR__.'/PreviewExample.php';
    set_error_handler(static function (int $severity, string $message): never {
        throw new RuntimeException('read-or-bootstrap-failed');
    });
    $view = Preview::inspect($argv[2], $argv[4]);
    fwrite(STDOUT, json_encode($view, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)."\n");
    exit((int) $view['exit']);
} catch (Throwable $e) {
    $reason = $e instanceof PreviewFailure ? $e->getMessage() : 'read-bootstrap-or-codec-failed';
    fwrite(STDERR, 'ERROR '.$reason."; no preview; no file or draft was saved.\n");
    exit(2);
}
