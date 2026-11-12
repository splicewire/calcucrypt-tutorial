<?php

namespace App\SavedInputs;

use Schemastud\DataSchemas\Attributes\MapValues;
use Spatie\LaravelData\Data;

/** The known legacy SQL row, validated before any destination record is written. */
class LegacySavedInputRowData extends Data
{
    /** @param array<array-key, mixed> $inputs */
    public function __construct(
        public string $id,
        public string $userId,
        public string $calculatorId,
        public string $name,
        #[MapValues(['number', 'string'])]
        public array $inputs,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'id' => ['required', 'uuid'],
            'userId' => ['required', 'uuid'],
            'calculatorId' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:80'],
            'inputs' => ['present', 'array'],
            'createdAt' => ['required', 'date'],
            'updatedAt' => ['required', 'date'],
        ];
    }
}
