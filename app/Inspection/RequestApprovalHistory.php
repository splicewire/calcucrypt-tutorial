<?php

namespace App\Inspection;

use App\Data\ReadRequestApprovalHistoryOutputData;
use App\Data\RequestApprovalFactData;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\ServiceRequestTeam;
use Illuminate\Support\Facades\Gate;
use Splicewire\Beam\Workflows\Control\WorkflowHistory;
use Splicewire\Beam\Workflows\Definition\DefinitionStore;
use Splicewire\Beam\Workflows\Definition\WorkflowDefinitionLineage;

/** A projection of committed facts for one freshly authorized request. */
final class RequestApprovalHistory
{
    public function subject(int $id, User $actor): ServiceRequest
    {
        $team = ServiceRequestTeam::forUser($actor);
        abort_unless($team !== null, 403);
        $subject = ServiceRequest::query()->where('team_id', $team->getKey())->findOrFail($id);
        Gate::forUser($actor)->authorize('view', $subject);

        return $subject;
    }

    public function read(ServiceRequest $subject): ReadRequestApprovalHistoryOutputData
    {
        $facts = [];
        // This native reader derives the morph type, ID and connection from this model.
        foreach (app(WorkflowHistory::class)->forSubject($subject) as $fact) {
            $versionId = $fact->getAttribute('version_id');
            if ($versionId !== null && ! is_string($versionId)) {
                throw new \UnexpectedValueException('Invalid committed version reference.');
            }
            $version = $versionId === null ? null : app(DefinitionStore::class)
                ->onConnection($subject->getConnectionName())->version($versionId);
            $lineage = $version?->lineage()->first();
            if ($lineage !== null && ! $lineage instanceof WorkflowDefinitionLineage) {
                throw new \UnexpectedValueException('Invalid definition lineage.');
            }
            $status = $versionId === null ? 'not-recorded' : ($lineage === null ? 'unavailable' : 'available');
            $actor = $fact->getAttribute('actor');
            $time = $fact->getAttribute('occurred_at');
            if (! $time instanceof \Carbon\CarbonImmutable) {
                throw new \UnexpectedValueException('Invalid committed occurrence time.');
            }
            $canonicalActor = is_string($actor) && preg_match('/\Auser:(?:[1-9][0-9]*|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\z/D', $actor) === 1 ? $actor : null;
            $facts[] = new RequestApprovalFactData(
                id: (string) $fact->getKey(),
                transition: $this->text($fact->getAttribute('transition')),
                from: $this->places($fact->getAttribute('from')),
                to: $this->places($fact->getAttribute('to')),
                actorReference: $canonicalActor,
                occurredAt: $time->utc()->format('Y-m-d\TH:i:s.u\Z'),
                versionId: $versionId,
                definitionStatus: $status,
                definitionKey: $lineage?->key,
                definitionName: $lineage?->name,
                definitionVersion: $lineage === null ? null : $version->version,
            );
        }

        return new ReadRequestApprovalHistoryOutputData(
            requestId: $subject->id,
            title: $subject->title,
            currentApproval: $subject->approval_state,
            currentPin: $subject->workflow_version,
            facts: $facts,
        );
    }

    private function text(mixed $value): string
    {
        if (! is_string($value)) {
            throw new \UnexpectedValueException('Invalid committed label.');
        }

        return $value;
    }

    /** @return list<string> */
    private function places(mixed $places): array
    {
        if (! is_array($places) || ! array_is_list($places)) {
            throw new \UnexpectedValueException('Committed places must be a string list.');
        }
        foreach ($places as $place) {
            if (! is_string($place)) {
                throw new \UnexpectedValueException('Committed places must be a string list.');
            }
        }

        return $places;
    }
}
