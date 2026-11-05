<?php

return [
    // This starter mounts seller resources in its authenticated marketplace group.
    'repo_authorization' => ['register_routes' => false],
    // Optional package: no public/signed storage route is mounted for licensed ZIPs.
    'dist_disk' => 'marketplace-registry',
];
