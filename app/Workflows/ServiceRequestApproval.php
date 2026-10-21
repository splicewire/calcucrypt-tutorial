<?php

namespace App\Workflows;

use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Log;
use Splicewire\Beam\Workflows\Binding\WorkflowBindingRegistry;
use Splicewire\Beam\Workflows\Blueprint\WorkflowBlueprint;
use Splicewire\Beam\Workflows\Control\Events\WorkflowTransitioned;
use Splicewire\Beam\Workflows\Control\TransitionEffectRegistry;

final class ServiceRequestApproval
{
    public const TYPE = 'service-request';

    public const LINEAGE = 'service-request.approval';

    public const EFFECT = 'service-request.approval-receipt';

    public static function register(): void
    {
        Relation::morphMap([self::TYPE => ServiceRequest::class]);
        app(WorkflowBindingRegistry::class)->bind(self::TYPE, self::LINEAGE);
        app(TransitionEffectRegistry::class)->register(
            self::EFFECT,
            static function (WorkflowTransitioned $event, array $params): void {
                // A local best-effort teaching receipt, not the authoritative history or an outbox.
                Log::channel('single')->info('Service request approval committed', [
                    'transitionId' => $event->transitionId,
                    'subjectId' => (string) $event->subject->getKey(),
                    'from' => $event->from,
                    'to' => $event->to,
                    'versionId' => $event->versionId,
                    'actor' => $event->actor,
                ]);
            },
            label: 'Record the approval in the local application log',
        );
    }

    public static function blueprint(): WorkflowBlueprint
    {
        return WorkflowBlueprint::fromArray([
            'name' => self::LINEAGE,
            'places' => ['pending', 'approved'],
            'initial' => ['pending'],
            'transitions' => [[
                'name' => 'approve', 'from' => 'pending', 'to' => 'approved',
                'effects' => [self::EFFECT],
            ]],
        ]);
    }
}
