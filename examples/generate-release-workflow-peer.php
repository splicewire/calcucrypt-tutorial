<?php

// Build-only projection of the existing workflow scheduling form's PHP wire types.
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function ($error) {
    fwrite(STDERR, (string) $error);
    exit(1);
});
$out = $argv[1] ?? '';
if (count($argv) !== 2 || ! app()->environment('local') || $out === '' || file_exists($out)
    || is_link($out) || ! is_dir(dirname($out))) {
    fwrite(STDERR, "Use a local disposable host and a NEW output directory under an existing scratch parent.\n");
    exit(1);
}
mkdir($out);
$out = realpath($out);
$factory = (new Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory)->outputDirectory($out)
    ->extension(new Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension)
    ->transformer(Spatie\TypeScriptTransformer\Transformers\EnumTransformer::class)
    ->transformDirectories(base_path('vendor/splicewire/laravel-beam-workflows/src/Data'))
    ->writer(new Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter('generated.d.ts'));
$app->instance(Spatie\TypeScriptTransformer\TypeScriptTransformerConfig::class, $factory->get());
Illuminate\Support\Facades\Artisan::call('typescript:transform');
echo Illuminate\Support\Facades\Artisan::output();
$types = ['WorkflowProjectionData', 'WorkflowTransitionData'];
$context = new Rushing\PipelineRegistry\PipelineContext;
$stage = new Schemastud\DataSchemas\Pipelines\TransformTypesStage(['scope' => 'splicewire', 'source' => $out.'/generated.d.ts', 'types' => $types, 'emit' => 'types/workflows.d.ts']);
$stage->handle($context, fn ($c) => $c);
foreach ($context->log as $line) {
    echo $line."\n";
    if (str_contains($line, 'MISSING') || str_contains($line, 'DANGLING') || str_contains($line, 'AMBIGUOUS')) {
        throw new RuntimeException($line);
    }
}
if (! is_dir($out.'/types')) {
    mkdir($out.'/types');
}file_put_contents($out.'/types/workflows.d.ts', $context->files['types/workflows.d.ts']);
file_put_contents($out.'/package.json', json_encode(['name' => '@splicewire/_resources', 'private' => true, 'type' => 'module', 'exports' => ['./types/*' => ['types' => './types/*.d.ts']]], JSON_PRETTY_PRINT)."\n");
