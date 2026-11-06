<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The role of a node in a Calculator's graph (CONTEXT.md "Step"):
 *  - input:      a user-entered leaf (scalar)
 *  - ref:        a grounded leaf resolved from a Live Fact (server-resolved). With `over`
 *                set it resolves one Fact PER ROW of a collection, yielding a vector.
 *  - computed:   a declared scalar expression over its dependencies
 *  - output:     a computed/aggregate node surfaced as a headline result
 *  - collection: a user-entered ARRAY of rows (the array primitive)
 *  - each:       maps a scalar expression over a collection's rows → a vector (the "map")
 *  - aggregate:  folds a vector to a scalar via sum/avg/min/max/count (the "reduce")
 *  - fold:       a temporal recurrence — seed `init`, then iterate `step` (reading `acc`, the
 *                previous value, and `i`, the 1-based period) `count` times. Yields a series
 *                VECTOR and its final value as a SCALAR (compounding, loan amortization).
 *
 * The scalar expression grammar (ExpressionEvaluator) is unchanged; `each`/`aggregate`/`fold`
 * orchestrate it over collections/periods at the GRAPH layer, so the parity fixture stays scalar.
 *
 * Locus (client vs server) is DERIVED from deps, never stored — only `ref` nodes force
 * the server; everything else evaluates on the client once refs are resolved into numbers.
 */
#[TypeScript]
enum NodeKind: string
{
    case Input = 'input';
    case Ref = 'ref';
    case Computed = 'computed';
    case Output = 'output';
    case Collection = 'collection';
    case Each = 'each';
    case Aggregate = 'aggregate';
    case Fold = 'fold';
}
