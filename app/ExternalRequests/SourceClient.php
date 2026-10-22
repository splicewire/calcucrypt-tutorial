<?php

namespace App\ExternalRequests;

use App\Data\ExternalRequestData;
use App\Data\ExternalRequestSourceResponseData;
use App\Models\User;
use App\Support\ServiceRequestTeam;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use JsonException;

final class SourceClient
{
    /** @return list<ExternalRequestData> */
    public function records(): array
    {
        $actor = request()->user();
        $team = $actor instanceof User ? ServiceRequestTeam::forUser($actor) : null;
        abort_if($team === null, 403, 'Select a team with an active membership.');
        $fixture = $this->fixture();
        $source = $fixture['sources'][(string) $team->getKey()] ?? null;
        abort_if(! is_array($source), 403, 'No external request source for this team.');
        if (! is_string($source['id'] ?? null) || ! preg_match('/^[a-z0-9-]{1,64}$/', $source['id'])
            || ! is_string($source['context'] ?? null) || ! preg_match('/^[a-z0-9-]{1,64}$/', $source['context'])) {
            throw new SourceFailure(502, 'External request source configuration is invalid.');
        }
        try {
            $response = Http::acceptJson()->withHeaders(['X-Fixture-Token' => $fixture['token']])
                ->connectTimeout(1)->timeout(1)->withoutRedirecting()
                ->get($fixture['baseUrl'].'/requests', ['source' => $source['context']]);
        } catch (ConnectionException) {
            // Never propagate the transport exception: its message can carry request credentials.
            throw new SourceFailure(504, 'External request source timed out. Retry when it is available.');
        }
        if ($response->status() !== 200) {
            throw new SourceFailure(502, 'External request source is unavailable. Retry when it is available.');
        }
        try {
            $json = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($json) || ! isset($json['data']) || ! is_array($json['data']) || ! array_is_list($json['data'])) {
                throw new SourceFailure(502, 'External request source returned invalid data.');
            }
            $payload = ExternalRequestSourceResponseData::validateAndCreate($json);
        } catch (JsonException|ValidationException) {
            throw new SourceFailure(502, 'External request source returned invalid data.');
        }
        $rows = [];
        $ids = [];
        foreach ($payload->data as $row) {
            if (isset($ids[$row->externalId])) {
                throw new SourceFailure(502, 'External request source returned duplicate identities.');
            }
            $ids[$row->externalId] = true;
            $rows[] = new ExternalRequestData($source['id'].':'.$row->externalId, $source['id'], $row->externalId, $row->title, $row->status, $row->updatedAt);
        }

        return $rows;
    }

    /** @return array{baseUrl:string,token:string,sources:array<array-key,mixed>} */
    private function fixture(): array
    {
        $file = config('external-requests.fixture_file');
        if (! is_string($file) || is_link($file) || ! is_file($file)
            || realpath(dirname($file)) !== realpath(storage_path('app/private'))
            || (fileperms($file) & 0077) !== 0) {
            throw new SourceFailure(502, 'Configure a private local external request fixture.');
        }
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new SourceFailure(502, 'External request source configuration is unavailable.');
        }
        try {
            $fixture = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new SourceFailure(502, 'External request source configuration is invalid.');
        }
        if (! is_array($fixture) || ! is_string($fixture['baseUrl'] ?? null)
            || ! preg_match('#^http://127\.0\.0\.1:[0-9]{2,5}$#', $fixture['baseUrl'])
            || ! is_string($fixture['token'] ?? null) || ! preg_match('/^[a-f0-9]{48}$/', $fixture['token'])
            || ! is_array($fixture['sources'] ?? null)) {
            throw new SourceFailure(502, 'External request source configuration is invalid.');
        }

        return $fixture;
    }
}
