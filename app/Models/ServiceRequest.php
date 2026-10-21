<?php

namespace App\Models;

use App\Workflows\ServiceRequestApproval;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Splicewire\Beam\Workflows\Type\Concerns\WorkflowManaged as WorkflowManagedAttributes;
use Splicewire\Beam\Workflows\Type\Contracts\WorkflowManaged;

/**
 * @property int $id
 * @property int|null $team_id
 * @property string $user_id
 * @property string $title
 * @property string $approval_state
 * @property string|null $workflow_version
 * @property string $status
 */
#[Fillable(['title', 'status'])]
class ServiceRequest extends Model implements WorkflowManaged
{
    use WorkflowManagedAttributes;

    protected $attributes = ['approval_state' => 'pending'];

    public function workflowType(): string
    {
        return ServiceRequestApproval::TYPE;
    }

    public function workflowStatusAttribute(): string
    {
        return 'approval_state';
    }
}
