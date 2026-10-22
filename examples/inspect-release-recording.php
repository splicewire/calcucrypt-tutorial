<?php

// Read-only inspector for the ordinary guide journey.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$id = $argv[1] ?? '';
if (! Illuminate\Support\Str::isUuid($id)) {
    throw new InvalidArgumentException('Usage: php examples/inspect-release-recording.php <Release UUID>');
}
$release = Splicewire\Beam\Ux\Models\BeamUxRelease::findOrFail($id);
$row = app(Splicewire\Beam\Ux\Recording\ReleaseRecording::class)->receipt($release);
$members = [];
foreach ($row->manifest ?? [] as $file) {
    $entry = Splicewire\Beam\Ux\Models\BeamUxEntry::whereKey($file['entryId'])->firstOrFail();
    $members[] = ['entryId' => $file['entryId'], 'versionId' => $file['versionId'],
        'publishedPin' => $entry->getAttribute('published_version'), 'path' => $file['path'],
        'expectedSha256' => $file['sha256'],
        'mirrorMatches' => is_file($file['absolute']) && hash_file('sha256', $file['absolute']) === $file['sha256']];
}
echo json_encode(['releaseId' => $release->id, 'publication' => $release->status,
    'recordingId' => $row?->id, 'recording' => $row->state ?? 'unavailable',
    'sha' => $row?->sha, 'noChange' => $row->no_change ?? false,
    'installationStarted' => $row->installation_started ?? false, 'members' => $members,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
