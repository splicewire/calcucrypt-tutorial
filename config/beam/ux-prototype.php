<?php

declare(strict_types=1);

return [
    'package_root' => '.',
    'src_root' => 'resources/js',
    'package_json' => 'package.json',
    'router' => 'resources/js/pages/_prototype.tsx',
    'tokens_css' => 'resources/css/app.css',
    'prototype_dir' => 'resources/js/_prototype',
    'register_route' => true,
    'brand_import' => '@/components/app-logo',
    'boundary_command' => 'npm run beam:verify-prototype-boundary',
];
