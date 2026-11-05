# Project a populated registry through local policy fixtures

This standalone example uses real Popcorn registration and authorization, then
Graphine's native registry projection. `reader` and `operator` are fixed teaching
policy fixtures, **not authenticated users, production roles or permissions**. The
example does not install authorization on the starter or bind any global service.

Use a fresh audited developer host, including the locked Popcorn/Graphine packages,
and explicitly resolve your Herd PHP executable. From that own host:

```sh
PHP=$(XDEBUG_MODE=off herd php -r 'echo PHP_BINARY;')
XDEBUG_MODE=off "$PHP" examples/keyed-registry/inspect.php reader
XDEBUG_MODE=off "$PHP" examples/keyed-registry/inspect.php operator
XDEBUG_MODE=off "$PHP" examples/keyed-registry/inspect.php reader
```

Every invocation constructs a new registry, immutable local policy, graph source and
snapshot store. Output is sorted actual visible keys, node identities/types and
`PARENT_OF` edge endpoints. Reader has the request leaf and its ancestors; the gated
SLA leaf **and its orphan branch disappear**. Operator also sees the SLA leaf and
branch. For these two entries the native reader graph has four nodes/three edges,
operator six/five. Counts follow actual enumeration, not a fixed returned graph.

The CLI exits0 only after a populated valid projection. Unknown fixture choice,
incomplete population, invalid key/projection or unsupported enumeration fails2 with
fixed safe copy and no healthy/empty result. No payloads, registrants, ability lists,
exception candidates or internal miss reasons are rendered. These fixed local labels
must never become a browser-supplied actor switch or production authorization input.

## The actual declaration, entries and policy

`Example.php` wires these native seams (imports shown here are real installed APIs):

```php
use Rushing\Popcorn\Registries\Authorizer;
use Rushing\Popcorn\Registries\BasicRegistry;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\OnKeyDuplicate;
use Rushing\Popcorn\Registries\PopulationRequirement;
use Rushing\Popcorn\Registries\RegistryKey;
use Rushing\Graphine\Laravel\Sources\RegistryGraphSource;
use Rushing\Graphine\Drivers\RelationalDriverFactory;

$registry = new BasicRegistry(new IsRegistry(
    root: 'desk.automations', entryType: 'string',
    onKeyDuplicate: OnKeyDuplicate::Reject,
    populationRequirement: PopulationRequirement::Required,
));
$registry->register('requests.list', 'Request inspection', by: 'example/service-desk');
$registry->register('sla.inspect', 'SLA inspection', by: 'example/service-desk', ability: 'view-sla');
$registry->authorizeWith(LocalPolicy::fixture('reader')); // real Authorizer, allowed=[]
$source = new RegistryGraphSource($registry);
$store = RelationalDriverFactory::make($source, 'keyed-registry-example');
```

`LocalPolicy` implements the actual `Authorizer::allows(string, RegistryKey): bool`.
Its private immutable allowlist is fixed to reader=[] or operator=[view-sla]. `by` is
registration provenance, not authorization. Registration itself is not gated; an
ungated entry short-circuits and never calls the Authorizer. **An ability label alone
is not enforcement:** native BasicRegistry with no Authorizer returns all records.
The tests deliberately remove it in memory and expose the gated leaf, so a developer
can see why real policy installation matters. No production policy is disabled by
that counterfactual.

The private setup check uses trusted native unfiltered membership to verify the two
expected canonical registrations and explicit Reject/Required/type declaration. It
does not return a hidden-entry count or setup inventory to the caller. The renderer
requires EnumerableStore and its advertised capability, and validates the native
projection's population/edges against visible key prefixes. It emits only actual
native graph data; errors never become a successful empty graph.

## Trusted tooling is a separate invocation

```sh
XDEBUG_MODE=off "$PHP" examples/keyed-registry/inspect.php trusted-tooling-reader
XDEBUG_MODE=off "$PHP" examples/keyed-registry/inspect.php reader
```

The first is explicitly labelled **TRUSTED SHELL TOOLING ONLY**. It constructs a
new source with `unfiltered: true` over a new reader registry and returns the complete
bounded teaching graph. The native unfiltered accessor clones the view; it does not
turn the original reader into an unfiltered registry. The subsequent reader remains
four/three. Focused tests also keep one actual reader registry alive across the clone
and verify its original policy/declaration/keyspace/projection remain unchanged.

Never expose that option through HTTP, transfer a tooling graph as user data or cache
an operator graph for a reader. Both native source and snapshot store memoize. A new
driver around an old source can still reuse stale data: use new registry/policy,
source and store for every actor/read phase. This example demonstrates neither a
revocation stream nor automatic cache invalidation or concurrent request safety.

## Lookup and graph branch are different

The private focused tests exercise native calls; public CLI graph JSON omits lookup
payloads and internal exception details:

- `requests.list` resolves its exact entry; no parent-value inheritance is involved.
- Reader `has('sla.inspect')` is false and `tryResolve` returns null. Same-key denied
  and absent lookups have the same safe miss message/no candidates. Internal reasons
  differ for private tooling; do not expose `Filtered` versus `Absent` to a caller.
- `requests` is a derived graph Branch, not an entry. `has` is false, and both resolve
  and tryResolve throw `AmbiguousRegistryMatch` even with one descendant.
- Duplicate registration under explicit Reject throws and preserves the original
  value/keyspace/graph. Supersede is the native default; this example does not use it.
- Required but unpopulated native registry can enumerate keys/graph as empty, while
  resolve reports Unpopulated. The example rejects that incomplete setup instead of
  calling it a successfully filtered empty view. Invalid canonical key is an error.

Canonical dot keys are used throughout. Foreign separator identity collisions are a
different native bridge refusal; they are described in its source, not claimed as a
new runtime feature or complete foreign-key validator here.

## Real host adoption is separate

Popcorn does not install an Authorizer for the host. The checked starter50eaa
app/bootstrap/config paths represent no opt-in; that bounded source observation is
not a global security census. Beam ships `EntitlementRegistryAuthorizer`, but does
not install it automatically. A real selected host could configure actual trusted
ActorPort/AbilityResolver/entitlement policy, then explicitly call
`Popcorn::authorizeWith(app(EntitlementRegistryAuthorizer::class))`. Its key is not an
instance/team authorization subject. This example performs none of that adoption or
Laravel boot and makes no logged-in actor or production permission claim.

Laravel's baked registry map describes declaration/class membership, not entries or
authorization. Existing `popcorn:registries`, `popcorn:keys` and
`popcorn:registries:cache` are reference seams only, not commands this standalone
example runs. Projecting RegistryIndex roots is not recursively projecting every
registry entry. Locked deep `unfiltered()` behavior supersedes the bridge's stale
one-level comment; this example projects the populated BasicRegistry itself.

Run the focused standalone tests with installed dev PHPUnit and no app configuration:

```sh
XDEBUG_MODE=off "$PHP" vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php examples/keyed-registry/ExampleTest.php
```

They verify native filtering, safe rendering, lookup/miss/branch/duplicate/empty
semantics, ungated authorizer short-circuit, unknown fixtures, capability and
incomplete projection refusal. Missing Authorizer and hidden-leaf assertions provide
meaningful expected counterfactual failures in the proof, not mocked graph success.
See installed `vendor/rushing/php-popcorn/src/Registries/` and
`vendor/rushing/laravel-graphine/src/Sources/RegistryGraphSource.php` for ownership.

No GraphQL endpoint, graph persistence, governance weights, package REQUIRES edges,
registry catalogue, source-import or whole-host topology proof follows. No global
binding, app route/provider/autoload or runtime dependency is changed. Shipping
advisories, six-API/full-generated-registry and publication boundaries remain separate.
