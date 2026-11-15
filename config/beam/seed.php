<?php

/*
|--------------------------------------------------------------------------
| beam seed (seeding-scenarios 01, 02)
|--------------------------------------------------------------------------
| Which seed steps `splicewire:beam:seed` runs here. Every scenario runs the `baseline` steps (this starter's realm
| roots and nav, auth pages and theme among them). `demo` steps fabricate sample people: this starter's two users and
| beam-accounts' demo team.
|
| The run's scenario is the first of: `--scenario=`, `scenario` below, the scenario named for the environment, then
| `baseline`. So `local` and `preview` get demo data, and production and any other environment do not.
*/

return [

    'scenario' => env('BEAM_SEED_SCENARIO'),

    'scenarios' => [
        'local' => ['include' => ['demo']],
        'preview' => ['include' => ['demo']],
    ],

];
