<?php

namespace App\Data;

use App\Models\Calculator;
use App\SavedInputs\InputsAsMap;
use Illuminate\Validation\ValidationException;
use Schemastud\DataSchemas\Attributes\MapValues;
use Schemastud\DataSchemas\Attributes\Title;
use Schemastud\Frame\Attributes\Column;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Beam\Models\BeamParticle;
use Splicewire\Beam\Particle\Attributes\ParticleResource;
use Splicewire\Beam\Particle\Backing\ActorOwnedParticleAccess;
use Splicewire\Beam\Particle\Backing\ActorOwnedParticleBacking;

/** A reader's named inputs; the package owns storage, schema binding and actor isolation. */
#[ParticleResource(
    key: 'saved-inputs',
    backing: ActorOwnedParticleBacking::class,
    data: SavedInputData::class,
    input: SavedInputInputData::class,
    label: 'Saved inputs',
    policy: ActorOwnedParticleAccess::WRITE,
    readPolicy: ActorOwnedParticleAccess::class,
    schemaRef: SavedInputData::class.'/2',
    publicRead: null,
)]
#[Title('Saved inputs')]
#[TypeScript]
class SavedInputData extends Data
{
    /** @param array<string, int|float|string> $inputs */
    public function __construct(
        #[Column]
        public string $id,
        public string $calculator,
        public string $name,
        #[WithTransformer(InputsAsMap::class)]
        #[MapValues(['number', 'string'])]
        #[LiteralTypeScriptType('Record<string, number | string>')]
        public array $inputs,
        public ?string $savedAt,
    ) {}

    /**
     * Array construction is reserved for stored payload plus the trusted particle envelope, never wire-shaped arrays.
     * Both transports invoke this native Data factory.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromParticlePayload(array $payload): self
    {
        $calculatorId = $payload['calculator'] ?? null;
        if (! is_string($calculatorId) || ! is_string($payload['id'] ?? null)
            || ! is_string($payload['name'] ?? null) || ! is_array($payload['inputs'] ?? null)
            || ! (($payload['updated_at'] ?? null) instanceof \Carbon\CarbonInterface)) {
            throw new \UnexpectedValueException('Stored Saved Inputs must reference a calculator UUID.');
        }
        $request = request();
        $cache = $request->attributes->get(self::class.'.calculatorSlugs', []);
        $slug = $cache[$calculatorId] ??= Calculator::query()->whereKey($calculatorId)->firstOrFail()->slug;
        $request->attributes->set(self::class.'.calculatorSlugs', $cache);

        return new self(
            id: (string) $payload['id'],
            calculator: $slug,
            name: $payload['name'],
            inputs: $payload['inputs'],
            savedAt: $payload['updated_at']->toIso8601String(),
        );
    }

    /** Keep a stable calculator association while the public write names it by slug. */
    public static function prepare(BeamParticle $saved, mixed $input, mixed $actor): void
    {
        if ($input instanceof SavedInputInputData && ! $input->calculator instanceof Optional) {
            $calculator = Calculator::query()->published()->where('slug', $input->calculator)->first();
            if ($calculator === null) {
                throw ValidationException::withMessages(['calculator' => 'Choose a published calculator.']);
            }
            $input->calculator = (string) $calculator->getKey();
        }
    }
}
