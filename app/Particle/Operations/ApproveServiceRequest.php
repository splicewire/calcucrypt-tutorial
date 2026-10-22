<?php

namespace App\Particle\Operations;

use App\Models\ServiceRequest;
use App\Workflows\ServiceRequestApproval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Splicewire\Beam\Data\ResponseBody;
use Splicewire\Beam\Particle\ActionAffordance;
use Splicewire\Beam\Particle\Attributes\ParticleOp;
use Splicewire\Beam\Particle\OperationKind;
use Splicewire\Beam\Workflows\Control\TransitionContext;
use Splicewire\Beam\Workflows\Control\TransitionResult;
use Splicewire\Beam\Workflows\Control\WorkflowActuator;
use Splicewire\Beam\Workflows\Data\WorkflowTransitionAttemptData;
use Symfony\Component\Workflow\Exception\LogicException as WorkflowLogicException;

#[ParticleOp(
    resource: 'service-requests',
    name: 'approve',
    kind: OperationKind::Write,
    ability: 'approve',
    input: false,
    output: WorkflowTransitionAttemptData::class,
    affordance: new ActionAffordance(label: 'Approve'),
)]
class ApproveServiceRequest
{
    public static function handle(ServiceRequest $model, Request $request, mixed $actor): TransitionResult
    {
        try {
            return app(WorkflowActuator::class)->transition($model, 'approve', new TransitionContext(
                actor: $actor instanceof Model ? 'user:'.$actor->getKey() : null,
            ));
        } catch (WorkflowLogicException $error) {
            if (! self::invalidStoredMarking($error, $model)) {
                throw $error;
            }

            return new TransitionResult([$model->approval_state], 'approve', false, ['The stored approval marking is invalid for this workflow.']);
        }
    }

    public static function respond(TransitionResult $result, ServiceRequest $model): ResponseBody
    {
        try {
            $projection = app(WorkflowActuator::class)->projection($model->fresh());
        } catch (WorkflowLogicException $error) {
            if (! self::invalidStoredMarking($error, $model)) {
                throw $error;
            }
            $projection = null;
        }
        $response = ResponseBody::success(
            WorkflowTransitionAttemptData::fromResult($result, $projection),
            $result->applied ? 'Request approved.' : implode(' ', $result->blockers),
        );

        // Generic Frame actions announce 2xx as success. Preserve the lifecycle's blocked outcome.
        return $result->applied ? $response : $response->conflict();
    }

    private static function invalidStoredMarking(WorkflowLogicException $error, ServiceRequest $model): bool
    {
        // Symfony Workflow::getMarking's specific domain diagnostic in the locked runtime.
        // Other LogicExceptions remain failures; this is not a catch-all workflow fallback.
        return ! in_array($model->approval_state, ['pending', 'approved'], true)
            && $error->getMessage() === sprintf('Place "%s" is not valid for workflow "%s".',
                $model->approval_state, ServiceRequestApproval::LINEAGE);
    }
}
