<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Retained requests supply current team attribution, not a historical pricing ledger. */
final class CommittedApprovals
{
    public function forTeam(string $teamId): int
    {
        return DB::table('workflow_transition_facts as facts')
            ->join('service_requests as requests', 'facts.subject_id', '=', 'requests.id')
            ->where('facts.subject_type', 'service-request')
            ->where('facts.transition', 'approve')
            ->whereJsonContains('facts.from', 'pending')->whereJsonLength('facts.from', 1)
            ->whereJsonContains('facts.to', 'approved')->whereJsonLength('facts.to', 1)
            ->where('requests.team_id', $teamId)
            ->count();
    }
}
