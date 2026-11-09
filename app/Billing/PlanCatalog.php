<?php

namespace App\Billing;

use App\Data\PlanOfferData;
use Splicewire\Beam\Accounts\Entitlements\BundleRegistry;
use Splicewire\Beam\Particle\Backing\CollectionBacking;

/**
 * The plans on sale, read from `config/plans.php`, with the calculator keys each one unlocks read
 * from its bundle in `config/beam/accounts.php`.
 *
 * It is what backs the `plans` resource. The rows are config, not database records, so this is a
 * {@see CollectionBacking}: Beam lists and pages them, and nothing can write them. Beam orders a
 * collection descending by its sort key, so the dearest plan comes first.
 */
class PlanCatalog extends CollectionBacking
{
    public function __construct(private BundleRegistry $bundles) {}

    /** @return list<PlanOfferData> */
    public function all(): array
    {
        $plans = [];

        foreach ((array) config('plans', []) as $slug => $plan) {
            $plans[] = new PlanOfferData(
                slug: $slug,
                name: $plan['name'],
                summary: $plan['summary'],
                price: $plan['price'],
                currency: (string) config('commerce.currency', 'USD'),
                unlocks: $this->bundles->keysFor($slug),
            );
        }

        return $plans;
    }

    public function find(string $slug): ?PlanOfferData
    {
        foreach ($this->all() as $plan) {
            if ($plan->slug === $slug) {
                return $plan;
            }
        }

        return null;
    }

    /** @return list<PlanOfferData> */
    protected function rows(array $filters): iterable
    {
        return $this->all();
    }

    protected function sortKey(): string
    {
        return 'price';
    }

    protected function idKey(): string
    {
        return 'slug';
    }
}
