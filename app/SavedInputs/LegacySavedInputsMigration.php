<?php

namespace App\SavedInputs;

use App\Data\MigrateLegacySavedInputsOutputData;
use App\Data\SavedInputInputData;
use App\Models\Calculator;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LogicException;
use ReflectionClass;
use Schemastud\DataSchemas\Generators\Generator;
use Schemastud\DataSchemas\Migration\AcceptanceGate;
use Splicewire\Beam\Models\BeamParticle;
use Splicewire\Beam\Particle\Backing\ActorOwnedParticleAccess;
use Splicewire\Beam\Particle\Backing\ActorOwnedParticleBacking;
use Splicewire\Beam\Particle\ParticleResourceRegistry;
use Splicewire\Beam\Schema\JsonDocumentShape;
use Splicewire\Beam\Schema\SchemaId;
use Splicewire\Beam\Source\SourceWriteTier;
use Splicewire\Beam\Write\PolicyWriteGate;

/** Explicit, pre-cutover conversion; the legacy source is always retained unchanged. */
class LegacySavedInputsMigration
{
    public function run(bool $apply): MigrateLegacySavedInputsOutputData
    {
        if ($apply && ! app()->isDownForMaintenance()) {
            throw ValidationException::withMessages(['apply' => 'Apply requires a pre-cutover maintenance window.']);
        }
        $connection = (new BeamParticle)->getConnection();
        foreach ([new User, new Calculator] as $model) {
            if ($model->getConnection()->getName() !== $connection->getName()) {
                throw ValidationException::withMessages(['connection' => 'Legacy owners, calculators and particles must share one connection.']);
            }
        }
        if (! $connection->getSchemaBuilder()->hasTable('saved_inputs')) {
            return new MigrateLegacySavedInputsOutputData(! $apply, false, 0, 0, 0, 0);
        }

        return $apply
            ? $connection->transaction(fn () => $this->convert($connection, true))
            : $this->convert($connection, false);
    }

    private function convert(Connection $connection, bool $apply): MigrateLegacySavedInputsOutputData
    {
        $resource = app(ParticleResourceRegistry::class)->get('saved-inputs');
        $schema = app(Generator::class)->forResponse()->generate(new ReflectionClass(SavedInputInputData::class));
        $schema['additionalProperties'] = false;
        $plan = [];
        $skipped = 0;
        $rows = $connection->table('saved_inputs')->orderBy('id')->get();
        foreach ($rows as $source) {
            try {
                $inputs = json_decode($source->inputs, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw ValidationException::withMessages(['legacy.'.$source->id => 'Legacy inputs are not valid JSON.']);
            }
            try {
                $row = LegacySavedInputRowData::validateAndCreate([
                    'id' => $source->id, 'userId' => $source->user_id, 'calculatorId' => $source->calculator_id,
                    'name' => $source->name, 'inputs' => $inputs, 'createdAt' => $source->created_at, 'updatedAt' => $source->updated_at,
                ]);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(['legacy.'.$source->id => array_merge(...array_values($exception->errors()))]);
            }
            $owner = User::query()->find($row->userId);
            $calculator = Calculator::query()->find($row->calculatorId);
            if ($owner === null || $calculator === null) {
                throw ValidationException::withMessages(['legacy.'.$row->id => 'The legacy owner or calculator no longer exists.']);
            }
            if (($row->inputs !== [] && array_is_list($row->inputs)) || array_filter($row->inputs, fn ($value) => (! is_int($value) && ! is_float($value) && ! is_string($value)) || (is_float($value) && ! is_finite($value))) !== []) {
                throw ValidationException::withMessages(['legacy.'.$row->id => 'Legacy inputs must be a named set of finite numbers or strings.']);
            }
            $payload = ['calculator' => $row->calculatorId, 'name' => $row->name, 'inputs' => $row->inputs];
            $document = (new JsonDocumentShape)->restore($payload, $schema);
            if (! app(AcceptanceGate::class)->accepts($document, $schema)) {
                throw ValidationException::withMessages(['legacy.'.$row->id => 'The legacy payload does not conform to the declared storage schema.']);
            }
            $existing = BeamParticle::query()->find($row->id);
            if ($existing !== null) {
                if ($existing->getAttribute('schema_ref') !== SchemaId::from($resource->schemaRef)->recordType()
                    || $existing->getAttribute('source_tier') !== SourceWriteTier::Local
                    || ! ActorOwnedParticleAccess::owns($owner, $existing)
                    || $this->canonicalPayload($existing->getAttribute('payload')) !== $this->canonicalPayload($payload)
                    || ! $existing->getAttribute('created_at')?->equalTo(CarbonImmutable::parse($row->createdAt))
                    || ! $existing->getAttribute('updated_at')?->equalTo(CarbonImmutable::parse($row->updatedAt))) {
                    throw ValidationException::withMessages(['legacy.'.$row->id => 'An existing particle conflicts with this legacy row; nothing was overwritten.']);
                }
                $skipped++;

                continue;
            }
            $plan[] = [$row, $owner, $payload];
        }
        if ($apply) {
            foreach ($plan as [$row, $owner, $payload]) {
                $request = Request::create('/legacy-saved-inputs', 'POST');
                $request->setUserResolver(fn () => $owner);
                $backing = $resource->backing($request);
                if (! $backing instanceof ActorOwnedParticleBacking) {
                    throw new LogicException('Saved Inputs must use the package-owned particle backing.');
                }
                $record = $backing->newRecord()->model();
                $record->setAttribute('id', $row->id);
                $record->setAttribute('created_at', $row->createdAt);
                $record->setAttribute('updated_at', $row->updatedAt);
                $backing->writer(new PolicyWriteGate(app(Gate::class), $resource->policy))
                    ->write($record, $payload, $owner, emit: false);
            }
        }

        return new MigrateLegacySavedInputsOutputData(! $apply, true, $rows->count(), $apply ? count($plan) : 0, $apply ? 0 : count($plan), $skipped);
    }

    /** JSON objects may be reordered by the database; scalar types remain significant.
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function canonicalPayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->canonicalPayload($value);
            }
        }
        ksort($payload);

        return $payload;
    }
}
