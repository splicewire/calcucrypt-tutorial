<?php

return [
    // This local transport tutorial uses only a new, private fixture issued inside this host.
    'fixture_file' => env('EXTERNAL_REQUESTS_FIXTURE', storage_path('app/private/external-requests.json')),
];
