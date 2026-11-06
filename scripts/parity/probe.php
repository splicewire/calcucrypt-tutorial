<?php

declare(strict_types=1);

/*
 * The parity probe. Run inside ONE app (`php probe.php <app-root> <golden.json>`), it prints a
 * canonical JSON fingerprint of how that app behaves for the tutorial's four calculators.
 * scripts/parity-check runs it in the tutorial and in a Calcucrypt checkout and diffs the two.
 *
 * It touches nothing outside the process: sqlite :memory:, array cache and session, the null log,
 * compiled views in a temp dir, the commerce `fake` driver, and a fixed-price market source. No key,
 * no network.
 */

[$self, $root, $golden] = $argv + [null, null, null];
if ($root === null || $golden === null || ! is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "usage: php probe.php <app-root with vendor/> <expression-golden.json>\n");
    exit(2);
}
$root = realpath($root);
$views = sys_get_temp_dir().'/calcucrypt-parity-views-'.getmypid();
@mkdir($views, 0700, true);

$env = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('p', 32)),
    'APP_URL' => 'http://localhost',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'BROADCAST_CONNECTION' => 'null',
    'LOG_CHANNEL' => 'null',
    'VIEW_COMPILED_PATH' => $views,
    'BCRYPT_ROUNDS' => '4',
    'COMMERCE_DRIVER' => 'fake',
    'FACTS_DRIVER' => 'fixture',
    'CMC_API_KEY' => '',
    'FINNHUB_API_KEY' => '',
    'APP_BASE_PATH' => $root,
];
foreach ($env as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

require $root.'/vendor/autoload.php';
require __DIR__.'/ProbeConnector.php';
require __DIR__.'/ParityProbe.php';

CalcucryptParity\ParityProbe::$root = $root;
CalcucryptParity\ParityProbe::$golden = (string) realpath($golden);

$fingerprint = (new CalcucryptParity\ParityProbe('parity'))->fingerprint();

array_map('unlink', glob($views.'/*') ?: []);
@rmdir($views);

echo json_encode($fingerprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR), "\n";
