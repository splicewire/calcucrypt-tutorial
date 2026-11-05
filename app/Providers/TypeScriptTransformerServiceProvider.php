<?php

declare(strict_types=1);

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\ModuleWriter;
use Splicewire\Beam\Codegen\DeclaredParticleTransformedProvider;
use Splicewire\Beam\Codegen\DeclaredParticleTypes;

final class TypeScriptTransformerServiceProvider extends TypeScriptTransformerApplicationServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            ->provider(new DeclaredParticleTransformedProvider(app(DeclaredParticleTypes::class)))
            ->extension(new LaravelDataTypeScriptTransformerExtension)
            ->transformer(EnumTransformer::class)
            ->transformDirectories(
                app_path(),
                base_path('vendor/splicewire/laravel-beam-accounts/src/Data/Pages'),
            )
            ->writer(new ModuleWriter(path: null));

        // The optional review queue is a bespoke declared response, outside particle discovery.
        if (config('local-marketplace.enabled') && is_dir(base_path('vendor/splicewire/laravel-beam-market/src/Data'))) {
            $config->transformDirectories(base_path('vendor/splicewire/laravel-beam-market/src/Data'));
        }

    }
}
