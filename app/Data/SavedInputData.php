<?php

namespace App\Data;

use App\Models\Calculator;
use App\Models\SavedInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Beam\Particle\Attributes\ParticleResource;

/**
 * The read projection for the `saved-inputs` particle resource, and its declaration site: a signed-in reader's
 * named sets of inputs, one calculator each. Scaffolded with the generator's defaults, so it is writable: the
 * write shape is SavedInputInputData, and a bare mount takes all five verbs.
 *
 * Each reader sees and changes only their own: `scope()` keeps other readers' rows out (theirs are a 404), and
 * SavedInputPolicy says who may create. There is no Public Read, so a guest is refused.
 */
#[ParticleResource(
    key: 'saved-inputs',
    backing: SavedInput::class,
    input: SavedInputInputData::class,
    includes: ['calculator'],
    readOnly: false,
    // Not public: saved inputs belong to the reader who saved them.
    publicRead: null,
)]
#[Title('Saved inputs')]
#[TypeScript]
class SavedInputData extends Data
{
    /**
     * @param  array<string, int|float|string>  $inputs
     */
    public function __construct(
        public string $id,
        public string $calculator,
        public string $name,
        public array $inputs,
        public ?string $savedAt,
    ) {}

    /**
     * Only the signed-in reader's own rows exist on this resource.
     *
     * @param  Builder<SavedInput>  $query
     * @return Builder<SavedInput>
     */
    public static function scope(Builder $query): Builder
    {
        return $query->where('user_id', Auth::id());
    }

    public static function project(SavedInput $saved): self
    {
        return new self(
            id: $saved->id,
            calculator: $saved->calculator->slug,
            name: $saved->name,
            inputs: $saved->inputs,
            savedAt: $saved->updated_at?->toIso8601String(),
        );
    }

    /**
     * Before a write: a new row belongs to the reader who saves it, for the calculator its input names. This runs
     * before the policy is asked, so a guest's save arrives here with no actor; SavedInputPolicy then refuses it.
     */
    public static function prepare(SavedInput $saved, mixed $input, mixed $actor): void
    {
        if (! $saved->exists && $actor !== null) {
            $saved->user_id = $actor->getAuthIdentifier();
        }

        if ($input instanceof SavedInputInputData && $input->calculator !== null) {
            $saved->calculator_id = Calculator::query()->published()->where('slug', $input->calculator)->value('id');
        }
    }
}
