<?php

namespace App\Particle\Operations;

use App\Data\MigrateLegacySavedInputsFailureData;
use App\Data\MigrateLegacySavedInputsInputData;
use App\Data\MigrateLegacySavedInputsOutputData;
use App\SavedInputs\LegacySavedInputsMigration;
use Illuminate\Http\Request;
use Splicewire\Beam\Particle\Attributes\ParticleOp;
use Splicewire\Beam\Particle\OperationKind;
use Splicewire\Beam\Particle\Subject\NoSubject;

/** Local conversion adapter only: this declared operation is deliberately not mounted on HTTP. */
#[ParticleOp(
    resource: 'saved-inputs',
    name: 'migrate-legacy',
    summary: 'Convert legacy Saved Inputs during a maintenance window',
    description: 'Read-only preflight by default; an explicit apply preserves source records.',
    kind: OperationKind::Write,
    ability: 'saved-inputs.migrate-legacy',
    affordance: false,
    input: MigrateLegacySavedInputsInputData::class,
    output: MigrateLegacySavedInputsOutputData::class,
    subject: NoSubject::class,
    abilityModel: false,
    errors: [422 => MigrateLegacySavedInputsFailureData::class],
)]
class MigrateLegacySavedInputs
{
    public static function handle(mixed $subject, Request $request, mixed $actor): MigrateLegacySavedInputsOutputData
    {
        $input = MigrateLegacySavedInputsInputData::validateAndCreate($request->all());

        return app(LegacySavedInputsMigration::class)->run($input->apply);
    }
}
