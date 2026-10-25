<?php

return [
    // The starter renders Beam UX entries (ADR-0209), so no MDX file population is expected.
    // Any files later added under beam.mdx.content_path still receive every file-plane guard.
    'file_content_expected' => false,

    // The starter renders no <Artifact> (that is the flagship's highlighter plugin), so where its MDX pages hold blocks
    // their kit leaves it out; app.tsx registers the rest of the kit.
    'kit' => [
        'artifact' => false,
    ],
];
