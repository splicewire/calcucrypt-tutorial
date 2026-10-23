# Inspect one declared package dependency graph

This standalone PHP example reads two illustrative package manifests, builds a real
Graphine snapshot and evaluates their declared topology. It does not boot Laravel,
query a database or install the illustrative packages. Use a developer installation
including `rushing/php-package-topology`; `--no-dev` need not expose these APIs.

Start with a fresh, audited developer host at `$R/host` in your exclusively owned,
registered run room. Use the reviewed scratch tool to allocate two **new** empty
fixture rooms directly under R. Do not point this example at an owner checkout or
installed vendor directory. Resolve PHP from your Herd installation explicitly:

```sh
PHP=$(XDEBUG_MODE=off herd php -r 'echo PHP_BINARY;')
CLEAN=$(node "$TOOL/rigs/launch/bin/scratch.mjs" create --parent "$R" --prefix topology-clean- --row "$ROW")
CYCLIC=$(node "$TOOL/rigs/launch/bin/scratch.mjs" create --parent "$R" --prefix topology-cyclic- --row "$ROW")
cd "$R/host"
XDEBUG_MODE=off "$PHP" examples/declared-topology/prepare.php --root="$R" --fixture="$CLEAN"
XDEBUG_MODE=off "$PHP" examples/declared-topology/prepare.php --root="$R" --fixture="$CYCLIC" --cyclic
```

`prepare.php` writes only these new metadata rooms. It refuses an existing nonempty
room, invalid ownership, symlinks or a fixture outside the selected root. These are
example preflights; the native filesystem readers do not authorize a root or provide
an atomic snapshot against other editors. Keep the fixture exclusive and unchanged
while reading. Preparation adds an unrelated canary; inspection never writes.

The clean vendor-style tree has exactly two manifests:

`fixture/service-desk/composer.json`:

```json
{
  "name":"fixture/service-desk",
  "require":{"fixture/contracts":"*"},
  "extra":{"package-topology":{
    "mustRequire":["fixture/contracts"],
    "mustBeInstalled":["fixture/service-desk","fixture/contracts"]
  }}
}
```

`fixture/contracts/composer.json`:

```json
{"name":"fixture/contracts","require":{}}
```

The cyclic fixture changes **only** contracts' require map to
`{"fixture/service-desk":"*"}`. This is a reverse dependency, not a fabricated
violation message. No package version solver runs on these files.

## Read clean, cyclic, then clean again

```sh
XDEBUG_MODE=off "$PHP" examples/declared-topology/inspect.php --root="$R" --fixture="$CLEAN"
# Exit 0: measured-clean, two installed Package nodes, one REQUIRES edge.
XDEBUG_MODE=off "$PHP" examples/declared-topology/inspect.php --root="$R" --fixture="$CYCLIC"
# Exit 1: actual acyclic violation; both dependency directions are present.
XDEBUG_MODE=off "$PHP" examples/declared-topology/inspect.php --root="$R" --fixture="$CLEAN"
# Exit 0: fresh snapshot, original clean result, unchanged files.
```

The authored CLI prints sorted actual nodes and edges, native rule kinds/count,
`didNotLook`, unresolved rules and native violation kind/message. With these
manifests the declared reader produces four rules: one required edge, two installed
checks and its appended acyclic rule. Cycle rotation/order can differ; both package
identities must appear, not one hardcoded start node.

The example owns the exit convention: **0 measured clean, 1 actual violations,
2 invalid/incomplete input, capability or execution failure**. The evaluator itself
returns violations and does not set process status. Invalid JSON, missing package
endpoints, empty/duplicate/mismatched identities, extra files or unsupported scope
refuse rather than turning parser skips into a clean graph. No arbitrary root globs,
source-import rules or unbounded vendor scan are accepted. An error produces fixed
safe diagnostic copy and no clean result.

## Actual native wiring

`Example.php` validates input, then creates these objects anew for each invocation:

```php
use Rushing\Graphine\Contracts\ComputeStore;
use Rushing\Graphine\Contracts\EnumerableStore;
use Rushing\Graphine\Drivers\RelationalDriverFactory;
use Rushing\PackageTopology\Evaluator\TopologyEvaluator;
use Rushing\PackageTopology\Sources\ComposerManifestGraphSource;
use Rushing\PackageTopology\Sources\DeclaredContractSource;

$contract = (new DeclaredContractSource($fixture, ['fixture/*']))->contract('service-desk-example');
$source = new ComposerManifestGraphSource($fixture, ['fixture/*'], named: $contract->packagesNamed());
$store = RelationalDriverFactory::make($source, 'service-desk-example');
// The example requires ComputeStore AND EnumerableStore and their advertised capabilities.
$evaluator = new TopologyEvaluator;
$violations = $evaluator->evaluate($contract, $store, $fixture, $source);
// Inspect actual nodes/edges, rules, didNotLook() and unresolved() before crediting clean.
```

The example rejects a non-enumerable/non-computing driver instead of allowing a
silently skipped cycle check. It also refuses a native unexamined scope, even when
violations happen to be empty. Passing the real source as scope is essential; zero
without a scope would not prove that the evaluator looked. Missing installed nodes
or empty declarations are not a successful measurement.

The relational driver here hydrates PHP arrays. `putNode`/`putEdge` would mutate that
snapshot, not repair composer.json or persist database state. This example binds no
global GraphStore and calls no mutations or external graph backend. It is an authored
`inspect.php`, not an invented `artisan topology:*` command.

## Tests and boundaries

Run only the focused standalone `ExampleTest.php` with your host's installed PHPUnit
and `vendor/autoload.php`, using newly registered test rooms supplied in
`TOPOLOGY_RUN_ROOT` and `TOPOLOGY_TEST_FIXTURES` (a JSON list of eleven empty owned room
paths). These tests never use Laravel's application test case or refresh a database:

```sh
XDEBUG_MODE=off "$PHP" vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php examples/declared-topology/ExampleTest.php
```

The clean/cycle/clean test uses real manifests and evaluator. Other cases cover
malformed/empty/missing/duplicate input, outside/symlink paths, capability refusal,
actual native unresolved scope and nonempty preparation. No skipped test is evidence
of execution. For a wider consumer test, see the owning package's
`src/Testing/AssertsDeclaredTopology.php` and `AssertsPackageTopology.php`: override
its vendorPath/includeGlobs deliberately and assert declarations through that kit.

The root project's composer.json is outside this vendor-style scan unless explicitly
provided as package content. A clean fixture is not a whole host architecture pass,
Composer constraint-resolution result or source-import proof. Popcorn's registry
membership/cache/authority is separate; Graphine registry `PARENT_OF` key hierarchy
is not these package `REQUIRES` edges. No registry adapter, GraphQL endpoint or
external graph service is delivered. Surgeon's relocation topology seam records a
skip; it does not automatically enforce this declared graph. Shipping advisories,
six-API/full-generated-registry prerequisites and publication remain separate.
