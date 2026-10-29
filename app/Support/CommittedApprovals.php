<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Retained requests supply current team attribution, not a historical pricing ledger. */
final class CommittedApprovals
{
    public function forTeam(string $teamId): int
    {
        // `facts.subject_id` is a string and `requests.id` an integer: compare them as text on every driver (Postgres
        // has no varchar = bigint operator; sqlite's affinity would match '05' to 5). The cast is on the request side,
        // so a non-numeric subject id is never cast.
        $requestId = DB::raw(match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => 'cast(requests.id as char)',
            'sqlsrv' => 'cast(requests.id as nvarchar(255))',
            default => 'cast(requests.id as text)',
        });

        return DB::table('workflow_transition_facts as facts')
            ->join('service_requests as requests', 'facts.subject_id', '=', $requestId)
            ->where('facts.subject_type', 'service-request')
            ->where('facts.transition', 'approve')
            ->whereJsonContains('facts.from', 'pending')->whereJsonLength('facts.from', 1)
            ->whereJsonContains('facts.to', 'approved')->whereJsonLength('facts.to', 1)
            ->where('requests.team_id', $teamId)
            ->count();
    }
}
