<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Retained requests supply current team attribution, not a historical pricing ledger.
 *
 * A fact's `subject_id` is a string column holding `(string) $model->getKey()` (beam-workflows' LifecycleService and
 * WorkflowReactionService), while `service_requests.id` is a bigint. The join therefore compares the request key in
 * its written text form: exact subject identity on every driver. A string-to-bigint `=` is rejected by Postgres
 * (SQLSTATE 42883), and casting `subject_id` to a number would throw on a non-numeric subject instead of not matching.
 */
final class CommittedApprovals
{
    public function forTeam(string $teamId): int
    {
        $requestKeyAsText = DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb'
            ? 'CAST(requests.id AS CHAR)'
            : 'CAST(requests.id AS TEXT)';

        return DB::table('workflow_transition_facts as facts')
            ->join('service_requests as requests', 'facts.subject_id', '=', DB::raw($requestKeyAsText))
            ->where('facts.subject_type', 'service-request')
            ->where('facts.transition', 'approve')
            ->whereJsonContains('facts.from', 'pending')->whereJsonLength('facts.from', 1)
            ->whereJsonContains('facts.to', 'approved')->whereJsonLength('facts.to', 1)
            ->where('requests.team_id', $teamId)
            ->count();
    }
}
