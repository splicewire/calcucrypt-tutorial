<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfig;

uses(Tests\TestCase::class);

it('generates host page modules and resolves their package-owned references', function () {
    $configured = app(TypeScriptTransformerConfig::class);
    expect($configured->outputDirectory)->toBe(resource_path('js/generated'));
    $directory = sys_get_temp_dir().'/starter-types-'.bin2hex(random_bytes(8));
    // Exercise the real configured providers/writer, with only the output isolated per run.
    app()->instance(TypeScriptTransformerConfig::class, new TypeScriptTransformerConfig(...array_replace(
        get_object_vars($configured),
        ['outputDirectory' => $directory],
    )));

    try {
        expect(Artisan::call('typescript:transform'))->toBe(0);
        expect(Artisan::output())->not->toContain('not found in the transformed types');
        $pages = File::get($directory.'/App/Data/Pages/index.ts');
        expect($pages)->toContain('export type FrameConsolePageData', 'manifestUrl: string');
        expect($pages)->not->toContain('SitemapResourcePageData');
        expect($pages)->not->toContain('export type ProfilePageData', 'export type SecurityPageData');
        expect(File::get($directory.'/Splicewire/Beam/Accounts/Data/Pages/index.ts'))
            ->toContain('export type ProfilePageData', 'export type SecurityPageData');

        $first = hash('sha256', $pages);
        expect(Artisan::call('typescript:transform'))->toBe(0);
        expect(hash('sha256', File::get($directory.'/App/Data/Pages/index.ts')))->toBe($first);
    } finally {
        File::deleteDirectory($directory);
    }
});
