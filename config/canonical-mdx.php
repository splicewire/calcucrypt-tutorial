<?php

return [
    // Teaching surface only. Ordinary release authorization remains authoritative.
    'enabled' => (bool) env('BEAM_CANONICAL_MDX_REVIEW_ENABLED', false),
];
