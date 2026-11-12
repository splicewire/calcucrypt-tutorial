<?php

namespace App\SavedInputs;

use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

/** Preserve the declared JSON map, including an empty map after an array-cast database read. */
class InputsAsMap implements Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): mixed
    {
        return (object) $value;
    }
}
