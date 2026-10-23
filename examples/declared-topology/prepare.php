<?php

require __DIR__.'/Example.php';

use Examples\DeclaredTopology\Example;

try {
    $options = getopt('', ['root:', 'fixture:', 'cyclic']);
    if (! isset($options['root'], $options['fixture']) || ! is_string($options['root']) || ! is_string($options['fixture']) || array_diff(array_keys($options), ['root', 'fixture', 'cyclic']) !== []) {
        throw new InvalidArgumentException('Supply root and fixture');
    }
    Example::prepare($options['root'], $options['fixture'], isset($options['cyclic']));
    echo "Disposable metadata fixture prepared; illustrative packages were not installed.\n";
} catch (Throwable) {
    fwrite(STDERR, "Fixture preparation refused; use an empty registered owned fixture.\n");
    exit(2);
}
