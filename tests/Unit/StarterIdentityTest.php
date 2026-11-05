<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
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

        $this->assertSame($this->starter()['repository'], $this->normaliseOrigin($origin));
    }

    /**
     * CI's "Configure git to use the installation token" step rewrites origin to embed the minted
     * GitHub App token as userinfo, e.g. https://x-access-token:TOKEN@github.com/splicewire/....
     * The identity check must see through that: the declared repository is still the real remote.
     *
     * @return array<string, array{0: string}>
     */
    public static function credentialBearingOrigins(): array
    {
        return [
            'token userinfo' => ['https://x-access-token:ghs_EXAMPLETOKEN@github.com/splicewire/laravel-beam-starter'],
            'token userinfo with .git' => ['https://x-access-token:ghs_EXAMPLETOKEN@github.com/splicewire/laravel-beam-starter.git'],
            'user:pass userinfo' => ['https://user:pass@github.com/splicewire/laravel-beam-starter.git'],
            'plain with .git' => ['https://github.com/splicewire/laravel-beam-starter.git'],
            'plain already clean' => ['https://github.com/splicewire/laravel-beam-starter'],
        ];
    }

    #[DataProvider('credentialBearingOrigins')]
    public function test_origin_normalisation_strips_credentials_and_dot_git(string $origin): void
    {
        $this->assertSame($this->starter()['repository'], $this->normaliseOrigin($origin));
    }

    public function test_setup_dev_and_test_are_the_scripts_the_docs_name(): void
    {
        $this->assertSame([], array_values(array_diff(['setup', 'dev', 'test'], array_keys($this->composer()['scripts'] ?? []))));
    }

    /**
     * Normalise a `git remote get-url origin` value to a comparable canonical repository URL:
     * strip any `userinfo@` credentials (CI embeds the installation token as
     * `x-access-token:TOKEN@`) and any trailing `.git`.
     */
    private function normaliseOrigin(string $origin): string
    {
        $origin = (string) preg_replace('#^(https?://)[^/@]*@#', '$1', $origin);

        return (string) preg_replace('#\.git$#', '', $origin);
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
