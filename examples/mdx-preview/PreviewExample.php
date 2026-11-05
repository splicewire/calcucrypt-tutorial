<?php

namespace Examples\MdxPreview;

use Closure;
use Illuminate\Container\Container;
use RuntimeException;
use Splicewire\Beam\Mdx\MdxBody;

final class PreviewFailure extends RuntimeException {}

class NativeCodec
{
    public const IDENTITY = 'splicewire/laravel-beam-mdx@c168813b8bae73d98e7b2b6a5053fd6871f565c2 MdxBody::encode/decode';

    /**
     * @template T
     *
     * @param  Closure(): T  $operation
     * @return T
     */
    public static function within(Closure $operation): mixed
    {
        $prior = Container::getInstance();
        Container::setInstance(new Container);
        try {
            return $operation();
        } finally {
            Container::setInstance($prior);
        }
    }

    /** @return array{output: string, content: string, outputContent: string, twice: string} */
    public function roundtrip(string $raw): array
    {
        return self::within(function () use ($raw): array {
            $body = MdxBody::encode($raw);
            $output = MdxBody::decode($body);
            $again = MdxBody::encode($output);

            return ['output' => $output, 'content' => (string) $body[MdxBody::CONTENT_KEY],
                'outputContent' => (string) $again[MdxBody::CONTENT_KEY], 'twice' => MdxBody::decode($again)];
        });
    }
}

final class Preview
{
    public const LIMIT = 1048576;

    public const MARKER = '.mdx-preview-fixture.json';

    /** Refuse symbolic components before reading the selected file. */
    public static function path(string $path): string
    {
        if (! str_starts_with($path, '/') || preg_match('/[\x00-\x1f\x7f]/', $path)) {
            throw new PreviewFailure('invalid-path');
        }
        $walk = '';
        foreach (explode('/', substr($path, 1)) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                throw new PreviewFailure('invalid-path');
            }
            $walk .= '/'.$part;
            if (is_link($walk)) {
                throw new PreviewFailure('symlink-refused');
            }
        }
        if (realpath($path) !== $path) {
            throw new PreviewFailure('missing-or-noncanonical-path');
        }

        return $path;
    }

    /** @return array{dev: int, ino: int, uid: int} */
    private static function owner(string $path): array
    {
        $s = lstat($path);
        if ($s === false || ! function_exists('posix_geteuid') || $s['uid'] !== posix_geteuid()) {
            throw new PreviewFailure('ownership-refused');
        }

        return ['dev' => $s['dev'], 'ino' => $s['ino'], 'uid' => $s['uid']];
    }

    public static function ownedRoot(string $root): string
    {
        self::path($root);
        if (! is_dir($root)) {
            throw new PreviewFailure('root-not-directory');
        }
        $identity = self::owner($root);
        $marker = $root.'/'.self::MARKER;
        self::path($marker);
        if (! is_file($marker) || filesize($marker) > 4096) {
            throw new PreviewFailure('fixture-marker-refused');
        }
        self::owner($marker);
        $raw = file_get_contents($marker);
        $m = $raw === false ? null : json_decode($raw, true);
        if (! is_array($m) || ($m['schema'] ?? null) !== 'mdx-preview-fixture/1' || ($m['root'] ?? null) !== $root || ($m['identity'] ?? null) !== $identity) {
            throw new PreviewFailure('fixture-marker-refused');
        }

        return $root;
    }

    /** Creates only a previously nonexistent fixture directory. */
    public static function prepare(string $root): void
    {
        $parent = dirname($root);
        self::path($parent);
        self::owner($parent);
        if (! preg_match('/^[A-Za-z0-9_-]+$/D', basename($root)) || file_exists($root) || is_link($root)) {
            throw new PreviewFailure('new-fixture-root-required');
        }
        if (! mkdir($root, 0700)) {
            throw new PreviewFailure('fixture-create-failed');
        }
        $marker = json_encode(['schema' => 'mdx-preview-fixture/1', 'root' => $root, 'identity' => self::owner($root)], JSON_THROW_ON_ERROR);
        if (file_put_contents($root.'/'.self::MARKER, $marker."\n") === false) {
            throw new PreviewFailure('fixture-create-failed');
        }
        foreach (['canonical.mdx', 'normalizing.mdx'] as $name) {
            if (! copy(__DIR__.'/'.$name, $root.'/'.$name)) {
                throw new PreviewFailure('fixture-copy-failed');
            }
        }
    }

    /** @return array{bytes: string, identity: array<string, int|string>} */
    private static function read(string $file): array
    {
        clearstatcache(true, $file);
        self::path($file);
        if (! is_file($file)) {
            throw new PreviewFailure('regular-file-required');
        }
        self::owner($file);
        $s = lstat($file);
        if ($s === false || $s['size'] > self::LIMIT) {
            throw new PreviewFailure('input-too-large');
        }
        $f = fopen($file, 'rb');
        if ($f === false) {
            throw new PreviewFailure('read-failed');
        }
        try {
            $bytes = stream_get_contents($f, self::LIMIT + 1);
            $opened = fstat($f);
        } finally {
            fclose($f);
        }
        if ($bytes === false || $opened === false || $opened['dev'] !== $s['dev'] || $opened['ino'] !== $s['ino']) {
            throw new PreviewFailure('read-identity-changed');
        }
        if (strlen($bytes) > self::LIMIT) {
            throw new PreviewFailure('input-too-large');
        }
        if (preg_match('//u', $bytes) !== 1) {
            throw new PreviewFailure('invalid-utf8');
        }

        return ['bytes' => $bytes, 'identity' => ['dev' => $s['dev'], 'ino' => $s['ino'], 'mode' => $s['mode'],
            'uid' => $s['uid'], 'size' => $s['size'], 'mtime' => $s['mtime'], 'ctime' => $s['ctime'], 'sha256' => hash('sha256', $bytes)]];
    }

    /**
     * Optional codec subclass/observer are failure-control test seams; CLI uses native defaults.
     *
     * @param  null|Closure(): void  $afterRoundtrip
     * @return array<string, bool|int|string>
     */
    public static function inspect(string $root, string $relative, ?NativeCodec $codec = null, ?Closure $afterRoundtrip = null): array
    {
        self::ownedRoot($root);
        if (! preg_match('~^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*\.mdx$~D', $relative)) {
            throw new PreviewFailure('relative-mdx-file-required');
        }
        $file = $root.'/'.$relative;
        $before = self::read($file);
        $result = ($codec ?? new NativeCodec)->roundtrip($before['bytes']);
        if ($result['content'] !== $result['outputContent']) {
            throw new PreviewFailure('content-changed');
        }
        if ($result['output'] !== $result['twice']) {
            throw new PreviewFailure('second-roundtrip-unstable');
        }
        if ($afterRoundtrip !== null) {
            $afterRoundtrip();
        }
        self::ownedRoot($root);
        $after = self::read($file);
        if ($after !== $before) {
            throw new PreviewFailure('input-changed');
        }
        $equal = $before['bytes'] === $result['output'];

        return ['file' => $relative, 'codec' => NativeCodec::IDENTITY,
            'result' => $equal ? 'byte-equal (not validation)' : 'frontmatter normalizes (not an import)',
            'exit' => $equal ? 0 : 1, 'equal' => $equal, 'contentPreserved' => true,
            'secondRoundtripStable' => true, 'inputUnchangedAtEndCheck' => true,
            'inputSha256' => hash('sha256', $before['bytes']), 'outputSha256' => hash('sha256', $result['output']),
            'inputBytes' => strlen($before['bytes']), 'outputBytes' => strlen($result['output']),
            'normalizedPreview' => $result['output'], 'notice' => 'No file or draft was saved.'];
    }
}
