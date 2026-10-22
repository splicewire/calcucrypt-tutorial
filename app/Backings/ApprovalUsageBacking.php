<?php

namespace App\Backings;

use App\Data\ApprovalUsageData;
use App\Models\User;
use App\Support\ServiceRequestTeam;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Splicewire\Beam\Particle\Backing\CollectionBacking;
use Splicewire\Beam\Particle\Backing\ResolvedRecord;
use Splicewire\Beam\Particle\Backing\ResolvesRecord;

final class ApprovalUsageBacking extends CollectionBacking implements ResolvesRecord
{
    protected function rows(array $filters): iterable
    {
        $actor = request()->user();
        $team = $actor instanceof User ? ServiceRequestTeam::forUser($actor) : null;
        abort_unless($team !== null, 403);
        if ($filters !== []) {
            throw ValidationException::withMessages(['filter' => 'Approval usage has no filters.']);
        }

        // Facts and markings commit together. The retained request's CURRENT team supplies
        // attribution; this is not a historical billing ledger or a separate wallet debit.
        $quantity = DB::table('workflow_transition_facts as facts')
            ->join('service_requests as requests', 'facts.subject_id', '=', 'requests.id')
            ->where('facts.subject_type', 'service-request')
            ->where('facts.transition', 'approve')
            ->whereJsonContains('facts.from', 'pending')->whereJsonLength('facts.from', 1)
            ->whereJsonContains('facts.to', 'approved')->whereJsonLength('facts.to', 1)
            ->where('requests.team_id', $team->getKey())
            ->count();

        yield new ApprovalUsageData('approvals', 'service-request.approval', $quantity, 'approvals', 'lifetime');
    }

    public function resolve(string $id, array $filters): ?ResolvedRecord
    {
        // Frame includes its optional discriminator; this summary has one fixed identity.
        unset($filters['source']);
        foreach ($this->rows($filters) as $row) {
            if ($row->id === $id) {
                return new ResolvedRecord($row);
            }
        }

        return null;
    }
}
