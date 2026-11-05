<?php

namespace Examples\MdxPreview;

use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Splicewire\Beam\Mdx\Frontmatter\FrontmatterParser;
use Splicewire\Beam\Mdx\MdxBody;

require_once __DIR__.'/PreviewExample.php';

final class PreviewTest extends TestCase
{
    // Independently authored expected bytes: not produced by the codec under test.
    private const BODY = "\n# Help\n\n<Notice>Read only.</Notice>\n\n";

    private const CANONICAL = "---\ntitle: Help\nnavOrder: 2\n---\n".self::BODY;

    private string $root;

    protected function setUp(): void
    {
        $parent = getenv('MDX_PREVIEW_TEST_ROOT');
        if ($parent === false || ! is_dir($parent)) {
            throw new RuntimeException('Explicit owned MDX_PREVIEW_TEST_ROOT required');
        }
        $this->root = realpath($parent).'/case-'.bin2hex(random_bytes(8));
        Preview::prepare($this->root);
    }

    public function test_real_native_canonical_and_normalizing_bytes_body_and_stability(): void
    {
        $a = Preview::inspect($this->root, 'canonical.mdx');
        $b = Preview::inspect($this->root, 'normalizing.mdx');
        self::assertSame(0, $a['exit']);
        self::assertSame(1, $b['exit']);
        self::assertSame(self::CANONICAL, $a['normalizedPreview']);
        self::assertSame(self::CANONICAL, $b['normalizedPreview']);
        self::assertNotSame($b['inputSha256'], $b['outputSha256']);
        foreach (['canonical.mdx', 'normalizing.mdx'] as $name) {
            $raw = file_get_contents($this->root.'/'.$name);
            self::assertIsString($raw);
            $result = (new NativeCodec)->roundtrip($raw);
            self::assertSame(self::BODY, $result['content']);
            self::assertSame(self::BODY, $result['outputContent']);
            self::assertSame($result['output'], $result['twice']);
        }
        self::assertSame($a, Preview::inspect($this->root, 'canonical.mdx'));
        self::assertTrue($b['contentPreserved']);
        self::assertTrue($b['secondRoundtripStable']);
    }

    public function test_real_container_and_exact_prior_instance_restored_on_success_and_exception(): void
    {
        $original = Container::getInstance();
        $prior = new Container;
        Container::setInstance($prior);
        try {
            NativeCodec::within(function () use ($prior): void {
                self::assertNotSame($prior, Container::getInstance());
                self::assertInstanceOf(FrontmatterParser::class, app(FrontmatterParser::class));
                self::assertSame('Help', MdxBody::encode(self::CANONICAL)['frontmatter']['title']);
            });
            self::assertSame($prior, Container::getInstance());
            try {
                NativeCodec::within(static function (): never {
                    throw new RuntimeException('controlled-codec-exception');
                });
            } catch (RuntimeException $e) {
                self::assertSame('controlled-codec-exception', $e->getMessage());
            }
            self::assertSame($prior, Container::getInstance());
        } finally {
            Container::setInstance($original);
        }
    }

    public function test_native_flat_parser_is_not_validation_and_keys_have_two_forms(): void
    {
        NativeCodec::within(function (): void {
            $raw = "---\ntitle: A\ntitle: B\nunsupported line\nnavOrder: 2\n---\nbody\n";
            $body = MdxBody::encode($raw);
            self::assertSame(['title' => 'B', 'navOrder' => '2'], $body['frontmatter']);
            self::assertSame("---\ntitle: B\nnavOrder: 2\n---\nbody\n", MdxBody::decode($body));
            $parsed = app(FrontmatterParser::class)->parse($raw);
            self::assertSame('2', $parsed->fields['nav_order']);
            self::assertArrayNotHasKey('nav_order', $parsed->raw);
            $incomplete = "---\ntitle: \"A\"\n<NotValidated />\n";
            self::assertSame($incomplete, MdxBody::encode($incomplete)['content']);
            self::assertSame($incomplete, MdxBody::decode(MdxBody::encode($incomplete)));
        });
    }

    public function test_files_mode_identity_and_bytes_unchanged_by_preview(): void
    {
        $file = $this->root.'/normalizing.mdx';
        $before = [file_get_contents($file), lstat($file)];
        Preview::inspect($this->root, 'normalizing.mdx');
        clearstatcache(true, $file);
        $after = lstat($file);
        self::assertSame($before[0], file_get_contents($file));
        self::assertIsArray($before[1]);
        self::assertIsArray($after);
        foreach (['dev', 'ino', 'mode', 'uid', 'size', 'mtime', 'ctime'] as $key) {
            self::assertSame($before[1][$key], $after[$key]);
        }
    }

    public function test_changed_input_control_refuses_after_actual_native_roundtrip(): void
    {
        $this->expectExceptionMessage('input-changed');
        Preview::inspect($this->root, 'canonical.mdx', afterRoundtrip: function (): void {
            file_put_contents($this->root.'/canonical.mdx', 'changed after native roundtrip');
        });
    }

    public function test_codec_exception_propagates_instead_of_empty_success(): void
    {
        $codec = new class extends NativeCodec
        {
            public function roundtrip(string $raw): array
            {
                throw new RuntimeException('controlled-codec-exception');
            }
        };
        $this->expectExceptionMessage('controlled-codec-exception');
        Preview::inspect($this->root, 'canonical.mdx', $codec);
    }

    public function test_content_change_is_not_reported_as_normalization(): void
    {
        $codec = new class extends NativeCodec
        {
            public function roundtrip(string $raw): array
            {
                $result = parent::roundtrip($raw);
                $result['outputContent'] = 'lost body';

                return $result;
            }
        };
        $this->expectExceptionMessage('content-changed');
        Preview::inspect($this->root, 'canonical.mdx', $codec);
    }

    public function test_unstable_second_roundtrip_refuses(): void
    {
        $codec = new class extends NativeCodec
        {
            public function roundtrip(string $raw): array
            {
                $result = parent::roundtrip($raw);
                $result['twice'] .= 'unstable';

                return $result;
            }
        };
        $this->expectExceptionMessage('second-roundtrip-unstable');
        Preview::inspect($this->root, 'canonical.mdx', $codec);
    }

    public function test_symbolic_file_refused_before_target_read(): void
    {
        symlink('/nonexistent-private-target', $this->root.'/link.mdx');
        $this->expectExceptionMessage('symlink-refused');
        Preview::inspect($this->root, 'link.mdx');
    }

    public function test_symbolic_directory_component_refused(): void
    {
        symlink($this->root, $this->root.'/nested');
        $this->expectExceptionMessage('symlink-refused');
        Preview::inspect($this->root, 'nested/canonical.mdx');
    }

    public function test_oversize_input_refused(): void
    {
        file_put_contents($this->root.'/big.mdx', str_repeat('x', Preview::LIMIT + 1));
        $this->expectExceptionMessage('input-too-large');
        Preview::inspect($this->root, 'big.mdx');
    }

    public function test_invalid_utf8_refused(): void
    {
        file_put_contents($this->root.'/bad.mdx', "\xff");
        $this->expectExceptionMessage('invalid-utf8');
        Preview::inspect($this->root, 'bad.mdx');
    }

    public function test_missing_input_refused(): void
    {
        $this->expectExceptionMessage('missing-or-noncanonical-path');
        Preview::inspect($this->root, 'missing.mdx');
    }

    public function test_escape_and_non_mdx_refused_before_read(): void
    {
        foreach (['../outside.mdx', '/etc/example.mdx', 'canonical.txt'] as $file) {
            try {
                Preview::inspect($this->root, $file);
                self::fail('invalid relative path accepted');
            } catch (PreviewFailure $e) {
                self::assertSame('relative-mdx-file-required', $e->getMessage());
            }
        }
    }

    public function test_directory_input_and_unmarked_root_refused(): void
    {
        mkdir($this->root.'/directory.mdx');
        try {
            Preview::inspect($this->root, 'directory.mdx');
            self::fail('directory accepted');
        } catch (PreviewFailure $e) {
            self::assertSame('regular-file-required', $e->getMessage());
        }
        mkdir($this->root.'/unmarked');
        $this->expectExceptionMessage('missing-or-noncanonical-path');
        Preview::inspect($this->root.'/unmarked', 'canonical.mdx');
    }

    public function test_preparation_never_blesses_existing_root_and_marker_is_bound(): void
    {
        try {
            Preview::prepare($this->root);
            self::fail('existing root was accepted');
        } catch (PreviewFailure $e) {
            self::assertSame('new-fixture-root-required', $e->getMessage());
        }
        file_put_contents($this->root.'/'.Preview::MARKER, '{"schema":"mdx-preview-fixture/1","root":"wrong"}');
        $this->expectExceptionMessage('fixture-marker-refused');
        Preview::inspect($this->root, 'canonical.mdx');
    }
}
