# Read a service-backed external request source

Current authoring compatibility: follow [Theme draft/Release adoption](service-desk-releases.md)
for the selected package pair and fresh/upgrade setup. Theme **Save draft** keeps published tokens
unchanged; explicit **Publish** releases the saved version (or requests review). Earlier slice proof
and candidate identities remain historical. This chapter's resource/access behavior is unchanged.

This chapter adds **External requests** beside the local service desk. It demonstrates the actual
Laravel HTTP transport against a deterministic local GET-only service. It is a teaching fixture, not
an integration with a production helpdesk provider. No local external-request model, migration, shadow,
create/edit/delete or approval operation is added.

Use the supplied current starter checkpoint with the unchanged paired PHP Frame
`b9adc1ac13562f3e32d0c84926a1bd97c64e7f85`, Schemastud JS
`67a8c811b9026987ef4354dad280619932e70c64`, and Beam JS
`614f463cb1daa65388b213b648bbf3ee35833e26`. All other dependencies remain at the committed locks.
Follow [the read-schema setup](service-desk-read-schema.md#install-the-paired-candidates) in a **new
private host and new source clones**: committed installs, alternate one-package PHP Frame overlay,
pinned build tools, included UI preparation helper, Frame/Beam builds, fresh localhost environment
and service-desk.sqlite, key, registry, normal installer and explicit ServiceRequestApprovalSeeder.
Then generate/types/build/nav and issue the private approval tutorial actors as in that guide.
Keep APP_ENV=local, file sessions, sync queue, array mail and demo-user seeding off. Use a free explicit
host port (46590 here). Do not repeat the historical guide's old UI overlay or use an owner host/database.

## Issue and run the local provider

From that fresh host after the approval actors exist:

```bash
XDEBUG_MODE=off herd php scripts/external-request-fixture.php 46591
node scripts/external-request-provider.mjs "$PWD/storage/app/private/external-requests.json"
```

The private issuer refuses an existing output/config/control/receipt, nonlocal host, wrong database or
non-tutorial actors. It generates a mode600 local token and server-owned source mapping from selected
team IDs to `fixture-desk-a/b` and provider contexts. It adds the tutorial Member to B as well as A so
an explicit fixture selection control can exercise A/B/A; it does not add a product team-switch UI.
No credentials appear in browser props, schemas, returned rows or safe receipts. Do not attach any
private configuration/actor/environment file to evidence.

The provider binds only127.0.0.1 and the exact requested port; an occupied port fails. It accepts GET
`/requests?source=a|b` only with its private header. A has five rows and B two, deliberately reusing
upstream R1/R2. Every projected ID includes its stable source: `fixture-desk-a:R1` and
`fixture-desk-b:R1` are different records. Rows also show Source, External ID, Title, Status and Updated.
The extra provider diagnostic is not projected. Provider receipts contain only method/source/status.

In another terminal in the same fresh host:

```bash
XDEBUG_MODE=off herd php artisan splicewire:beam:generate:assets --json
pnpm types:check
pnpm exec tsc --project tsconfig.service-request.json
pnpm build
XDEBUG_MODE=off herd php artisan serve --host=127.0.0.1 --port=46590 --tries=1 --no-reload
```

## Browser and actual generated-route consumer

Log in through the ordinary form using the private Member or Owner tutorial actor. Open Service desk →
External requests. All five A rows appear in descending `(updatedAt,id)` order. There is no New,
Save/Delete or Approve. For a small visible page, open `/external-requests?per_page=2`: Next, Previous
and First navigate cursor pages; no total is invented. Use the stock status filter for open/closed;
closed has R4/R2. Open a row, then reload: the stock detail has disabled declared fields including the
source-qualified ID. This is the accepted explicit read schema, not a write form with fake input.

Sign in as Outsider in a separate context: B has its own two rows/qualified identities. Direct A detail
returns404 and no A values. A missing qualified ID also404. Teamless/removed membership fails403 before
provider access; guests receive JSON401 or the normal login recovery. A valid selected member with no
configured source is403 on a data read. The explicit resource ability gates metadata/nav; it is distinct
from the older local request resource's global metadata policy.

Run the separate Node consumer with no session cookie:

```bash
EXTERNAL_REQUEST_BASE_URL=http://127.0.0.1:46590 \
EXTERNAL_REQUEST_ACTORS="$PWD/storage/app/private/approval-actors.json" \
pnpm example:external-requests
```

It imports the actual generated index route and ExternalRequestData, traverses all A pages, filters
closed and checks B isolation. It prints safe route/status/ID results, never tokens. The mounted generic
REST index uses **perPage** and `{data,limit,nextCursor}`; Frame uses **per_page** and
`{data,perPage,nextCursor}`. No REST show is mounted: a streams-only backing does not supply the Builder
that generic REST subject lookup requires. Generated index hooks expose only row data and no paging
arguments; this example does not execute them or claim generated typed cursor metadata. The handwritten
consumer checks the existing envelope and row fields at runtime; its type witness compiles actual exports.

## Fail, retry and recover

These are private local fixture controls, never product UI. In the owned fresh host, choose a mode:

```bash
EXTERNAL_REQUEST_MODE=unavailable node --input-type=module <<'JS'
import { readFileSync, writeFileSync, lstatSync } from 'node:fs';
const mode = process.env.EXTERNAL_REQUEST_MODE;
if (!['ready', 'empty', 'unavailable', 'malformed', 'timeout', 'oversized'].includes(mode)) throw Error('Unknown fixture mode');
const file = 'storage/app/private/external-provider-mode.json';
if (lstatSync(file).isSymbolicLink() || (lstatSync(file).mode & 0o077)) throw Error('Use the private fixture');
JSON.parse(readFileSync(file, 'utf8'));
writeFileSync(file, JSON.stringify({ mode }) + '\n');
JS
```

Reload list/detail to issue a real read. Unavailable503, malformed and more-than100 rows become a safe
host502; connection/response timeout becomes504. The stock list/detail displays an error and Retry,
not a successful empty queue. Set ready and press Retry to recover actual rows. Empty mode is a genuine
successful empty result with the stock empty state. Unknown/programming faults remain500. Never relay
upstream body/headers/token in the error. Private GET receipts distinguish refusal before provider
access from an upstream failure; mode and fixture file changes are proof controls, not business writes.

For same-actor selection proof, use a guarded private fixture script to invoke the existing User::switchTeam
on the tutorial Member A→B→A and reload between reads. Verify membership again each time. This is an
explicit fixture state change, not a shipped selection endpoint or a claim that stale browser caches
are reset by an unselected account feature. Focused tests also reuse the same backing instance across
these selections. Snapshot business state after setup and around reads/refusals/failure/recovery;
separate deliberate membership/selection controls and login/session/token timestamps from those reads.

## What owns the contract

ExternalRequestData declares the resource once; the backing is container-resolved at request time.
It extends CollectionBacking and implements ResolvesRecord/DeclaresFilterVocabulary. No QueriesRecords
or WritesRecords is claimed; opening write affordances would violate the capability ceiling. The status
vocabulary supplies existing filter controls and execution validates it. Filter source/team/URL/token
keys are refused; unrelated top-level query inputs cannot change the server mapping. Detail's legacy
optional source discriminator is ignored because qualified IDs are authoritative.

A subject-free Gate checks live selected-team membership using ServiceRequestTeam::forUser. Frame/nav
uses the model-less policy string. The installed REST guard additionally needs an existing
ResourceReadPolicy implementation, which delegates to the same ability. No fake Builder scope/global
read boundary or package change is used. The source client independently rechecks selection and narrows
both list/detail, with no sticky team cache.

The source row/response Data classes validate the provider payload and a whitelist projection produces
the declared read DTO. Every read fully materializes at most100 provider rows, then existing
CollectionBacking applies fixed descending keyset pagination/filtering. Oversize, malformed identities,
duplicate IDs or invalid status/time data fail; this is not provider-side pagination, a scalability
recipe, or a change to composite merging. The client has one-second connect/response timeouts and
follows no redirects. Configuration is server-owned, local-loopback-only and private; adapting a real
provider's authentication, rate limits, grants and pagination is separate work.

Owning references: installed PHP Beam `docs/agents/particle-doctrine.md` backing/capability sections and
ADR-0212/0219/0220/0221, plus installed PHP Frame `docs/resource-schemas.md` and JS Frame
`docs/schema-purpose.md`. A remote read source is distinct from remote UI, sourced-particle shadow
materialization/SourceGrant/lineage, local workflow transitions or composite writes.

```bash
XDEBUG_MODE=off herd php artisan test --filter='ExternalRequestTest|ServiceRequestSchemaPurposeTest|ServiceRequestApprovalTest|ServiceRequestTeamTest|ServiceRequestJourneyTest|ServiceRequestFrameTest|OperatorInspectionTest|ThemeSharedPropTest'
```

Full configured PHPStan retains the two known unrelated migration errors35/47; do not call it green.
Stop only the owned host/provider/browser/proxy, release capacity and use guarded scratch retention.
No owner host/state, retained PostgreSQL, external provider/key/signup/mail/jobs/spend, source release,
push or deployment is needed.

For a local source-of-truth projection using the same read-only backing/UI seams, continue with
[team approval usage](service-desk-approval-usage.md). It reads committed workflow facts without a
commerce dependency or a second usage ledger.
