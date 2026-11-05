<?php

use Splicewire\Beam\Models\BeamParticle;
use Splicewire\Beam\Models\BeamSubmission;

return [

    /*
    |--------------------------------------------------------------------------
    | beam substrate
    |--------------------------------------------------------------------------
    | The app-substrate rung. This config is intentionally near-empty at mint:
    | beam boots headless and the leaf-extraction tickets (07-10) populate it as
    | the generic schema-record / media traits and host-hook registries land.
    |
    | beam depends on nothing above it — frame (the editor rung) depends on beam,
    | never the reverse (ADR-0082). Keep host/editor concerns out of this file.
    */

    /*
    | Swappable models (Spatie swappable-model pattern). A host that composes the beam
    | traits on its own record/reference models points these at its subclasses.
    */
    'models' => [
        'particle' => BeamParticle::class,
        'submission' => BeamSubmission::class,
    ],

    // Table names are NOT here — they live in the beam-core config the package publishes,
    // config/beam/core.php. A host copy in this file is a stale duplicate.

    // 'media'         => [ ... ]   // (ticket 08)
    // 'hooks'         => [ ... ]   // (webhook / sitemap / doctor registries)

];
