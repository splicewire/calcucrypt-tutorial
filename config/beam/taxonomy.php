<?php

use Splicewire\Beam\Taxonomy\Data\SiloData;
use Splicewire\Beam\Taxonomy\Data\SiloInputData;
use Splicewire\Beam\Taxonomy\Data\TagData;
use Splicewire\Beam\Taxonomy\Models\Silo;
use Splicewire\Beam\Taxonomy\Models\Tag;

return [
    /*
    |--------------------------------------------------------------------------
    | Host-bound taxonomy models
    |--------------------------------------------------------------------------
    |
    | The taxonomy ParticleResource declarations name their concrete model +
    | read Data class by config, so a host binds its own Tag/Silo models
    | without this package depending UP on the host's model tier. A beam site
    | that has no taxonomy model simply leaves these null and the resources
    | are not registered (the provider guards on class_exists).
    |
    | The BeamTag/BeamSilo model cone now lives IN this package (tower-api-dissolution
    | issue 17 P2), so these defaults point at the package's own reusable
    | `Splicewire\Beam\Taxonomy\*` models/DTOs — a fresh beam site gets a working,
    | schema-typed taxonomy CRUD surface with zero host code. A host that layers extra
    | relations/behaviour onto Tag/Silo (as splicewire-app does — tower relations +
    | tenant-sync on `App\Models\{Tag,Silo}` subclasses) rebinds these in its own
    | published `config/beam/taxonomy.php`.
    */

    'models' => [
        'tag' => Tag::class,
        'silo' => Silo::class,
    ],

    'data' => [
        'tag' => TagData::class,
        'silo' => SiloData::class,
    ],

    'input' => [
        'silo' => SiloInputData::class,
    ],

];
