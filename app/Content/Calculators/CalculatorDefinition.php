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
     * The Calculator Key (CONTEXT.md; ADR 0005) — the permanent public name, two kebab segments
     * `<domain>.<computation>`, relative to the `calculators` registry root. The whole key is the
     * identity; it is never renamed (a wrong key is retired and a new one minted). Its input ids and
     * listed `outputs` are its ports: a refactor that keeps the key keeps every port id, and renaming
     * one is a new key. One-to-one with {@see slug()}, which stays the route key.
     */
    abstract public function key(): string;

    /**
     * The primary Topic's key (== its slug), one of {@see \App\Content\CategoryCatalog}'s: profit,
     * staking, dca, converter, allocation, tax, trader.
     */
    abstract public function category(): string;

    /**
     * Free Tags (CONTEXT.md): lowercase kebab labels for search and cross-cutting browse. Cheap to
     * add, rename or drop, and never part of the key. Projected by `content:sync`.
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return [];
    }

    /**
     * Admitted purpose distinctions (math-library-revenue 01 A2): the other Calculator Keys this one
     * gives the same answers as for the same inputs, each mapped to the distinct question a reader
     * brings here. Only a recorded review admits one; a definition never claims one for itself.
     *
     * @return array<string, string> other key => the distinct purpose
     */
    public function purposeDistinctions(): array
    {
        return [];
    }

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
