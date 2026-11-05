<?php

declare(strict_types=1);

namespace App\Content\Calculators;

use App\Enums\CalculatorStatus;

/**
 * A calculator authored as versioned source, not a database row. Each concrete definition
 * declares a calculator's identity and its computation `graph` (the same camelCase node
 * schema the frontend types and the PHP graph engine both read). `content:sync` discovers
 * every definition and projects it into the `calculators` table — the database is a derived
 * projection, never the source of truth.
 */
abstract class CalculatorDefinition
{
    abstract public function slug(): string;

    abstract public function name(): string;

    abstract public function summary(): string;

    /**
     * The computation kind (CONTEXT.md) — the distinguisher a card matches on. Distinct per
     * calculator across the catalog.
     */
    abstract public function computation(): string;

    /** The category key (== its slug): profit, staking, dca, converter, allocation, tax. */
    abstract public function category(): string;

    /**
     * The full DAG: nodes + outputs (+ optional chart/table). Composed only of existing Step
     * kinds — no engine change.
     *
     * @return array<string, mixed>
     */
    abstract public function graph(): array;

    public function status(): CalculatorStatus
    {
        return CalculatorStatus::Published;
    }

    /**
     * The entitlement key that unlocks this calculator, or null when it is free for everyone.
     * A paid calculator returns `calculators.{slug}`; the plans that include it name that key in
     * `config/beam/accounts.php` (`entitlements.bundles`).
     */
    public function entitlement(): ?string
    {
        return null;
    }

    /**
     * The slug of this calculator's canonical explainer Guide, if any. Applied by
     * `content:sync` after all Posts are upserted, so the Guide may be authored later.
     */
    public function primaryGuide(): ?string
    {
        return null;
    }
}
