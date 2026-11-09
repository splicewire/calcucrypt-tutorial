<?php

namespace App\Data;

use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The declared `output:` shape of the `purchase` operation on `plans`: whether the payment went
 * through, and the plan the team is on afterwards. A declined card is an answer, not an error, so
 * it comes back here with `paid: false` and the rail's decline code, and the team keeps its plan.
 */
#[Title('Plan purchase result')]
#[TypeScript]
class PurchasePlanOutputData extends Data
{
    public function __construct(
        public string $plan,
        public bool $paid,
        public ?string $declineCode,
        public string $teamPlan,
    ) {}
}
