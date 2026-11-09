<?php

namespace App\Data;

use App\Billing\PlanCatalog;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Beam\Particle\Attributes\ParticleResource;

/**
 * A plan a team can buy: its name, its price and the calculator keys it unlocks.
 *
 * It is also where the `plans` particle resource is declared. The rows come from
 * {@see PlanCatalog}, which reads `config/plans.php`, so the resource is read-only and has no
 * write DTO. Anyone may list the plans, signed in or not. `routes/api.php` mounts the list and the
 * `purchase` operation ({@see \App\Particle\Operations\PurchasePlan}).
 *
 * `unlocks` lets the plan picker tell which plans a team already has: a plan is held when the
 * reader's can-map allows every key it lists.
 */
#[ParticleResource(
    key: 'plans',
    backing: PlanCatalog::class,
    input: false,
    // Everyone reads the same catalogue: a guest through this Public Read, and a signed-in reader
    // too, since nothing else grants their read (ADR-0230). No policy to write.
    publicRead: PlanOfferData::class,
    readOnly: true,
    showable: false,
)]
#[Title('Plan')]
#[TypeScript]
class PlanOfferData extends Data
{
    /**
     * @param  int  $price  in minor units (cents) of `currency`
     * @param  list<string>  $unlocks  the entitlement keys the plan grants
     */
    public function __construct(
        public string $slug,
        public string $name,
        public string $summary,
        public int $price,
        public string $currency,
        public array $unlocks,
    ) {}
}
