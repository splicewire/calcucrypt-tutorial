<?php

/*
|--------------------------------------------------------------------------
| beam-ux host overrides (merged over the package defaults)
|--------------------------------------------------------------------------
|
| The package ships every beam.ux.* default (config/beam/ux.php in
| splicewire/laravel-beam-ux); mergeConfigFrom unions those UNDER these keys,
| so anything omitted here keeps its package value.
*/

return [

    // Disk-grouping namespace every beam-ux content page shares (ADR-0165 —
    // disk grouping only, NOT the URL). The authoring commands + beam:seed-demo
    // read it from here instead of hard-coding a host slug.
    'namespace' => env('BEAM_UX_NAMESPACE', 'starter'),

    // Point the beam-ux disk-mirror at the `beam-ux` filesystem disk (config/filesystems.php), whose
    // root is resources/beam-ux/. This is what makes NavSource discover resources/beam-ux/nav.yml
    // (its disk lookup is relative to this disk's root, else base_path). Required for ux:seed-nav to
    // read the authored nav file rather than deriving from entry frontmatter.
    'storage' => [
        'mirror_disk' => env('BEAM_UX_STORAGE_MIRROR_DISK', 'beam-ux'),
    ],

    // Path → realm fallback for register-from-disk when a page file declares no
    // `realm:` in its frontmatter. Ordered glob => realm (fnmatch, first match
    // wins; no match ⇒ the model `site` default). The VISIBLE nav stays
    // authoritative in resources/beam-ux/nav.yml (nav-source priority 2); this
    // only affects a page's realm CONTAINMENT for a nav.yml-free future host.
    'realm_conventions' => [
        '*/page/dashboard*' => 'account',
        '*/page/settings-*' => 'account',
        '*/page/operator-*' => 'operator',
        '*/page/login*' => 'auth',
        '*/page/register*' => 'auth',
    ],

];
