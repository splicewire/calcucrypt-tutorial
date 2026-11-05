<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * docs-walkthrough DOC-11(a), DOCS-09: the starter's identity is declared ONCE, in `composer.json`: its composer `name`,
 * its repository (`extra.splicewire.starter.repository`) and whether it is `published` on packagist. The docs read
 * these through the doc host's fixture, and render `composer create-project` only when `published` is true, else
 * `git clone` + `composer setup`.
 *
 * The name was `laravel/react-starter-kit`, the upstream kit's, so the published docs told a reader to clone or
 * create-project names that do not exist. OQ-D4 decided the identity: `splicewire/laravel-beam-starter`, matching the
 * remote and the other four `splicewire/*` starters, unpublished until launch.
 */
class StarterIdentityTest extends TestCase
{
    public function test_the_name_is_splicewire_slash_the_repository_basename(): void
    {
        $starter = $this->starter();

        $this->assertMatchesRegularExpression('#^https://github\.com/splicewire/[a-z0-9-]+$#', $starter['repository']);
        $this->assertSame('splicewire/'.basename($starter['repository']), $this->composer()['name']);
        $this->assertIsBool($starter['published']);
    }

    public function test_the_declared_repository_is_this_checkout_s_remote_when_git_can_say(): void
    {
        $origin = trim((string) @shell_exec('git -C '.escapeshellarg(dirname(__DIR__, 2)).' remote get-url origin 2>/dev/null'));
        if ($origin === '') {
            $this->markTestSkipped('No origin remote here (an export or a fresh `git init`), so only the declaration is checked.');
        }

        $this->assertSame($this->starter()['repository'], preg_replace('#\.git$#', '', $origin));
    }

    public function test_setup_dev_and_test_are_the_scripts_the_docs_name(): void
    {
        $this->assertSame([], array_values(array_diff(['setup', 'dev', 'test'], array_keys($this->composer()['scripts'] ?? []))));
    }

    /** @return array<string, mixed> */
    private function composer(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array{repository: string, published: bool} */
    private function starter(): array
    {
        $starter = $this->composer()['extra']['splicewire']['starter'] ?? null;
        $this->assertIsArray($starter, 'composer.json declares no extra.splicewire.starter {repository, published}.');

        return $starter;
    }
}
