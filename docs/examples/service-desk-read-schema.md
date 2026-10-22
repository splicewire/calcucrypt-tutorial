# Create with a write schema and inspect the declared read projection

Current authoring compatibility: follow [Theme draft/Release adoption](service-desk-releases.md)
for the selected package pair and fresh/upgrade setup. Theme **Save draft** keeps published tokens
unchanged; explicit **Publish** releases the saved version (or requests review). Earlier slice proof
and candidate identities remain historical. This chapter's resource/access behavior is unchanged.

Continue the [approval journey](service-desk-approval.md) with the paired PHP Frame, JS Frame and
Beam UI candidates supplied with this starter. The input remains Title/Status. Noneditable detail
now displays disabled ID/Title/Status/Approval fields, using the read DTO and response projection.
Approval still uses the existing declared action and workflow; no new write field or page is needed.

The local package checkpoints are development candidates, not published releases. Keep this starter's
committed Composer/pnpm locks and all other peers pinned. Use fresh source clones and a fresh host,
never an owner checkout/environment/database. Obtain the exact four candidate identities from the
handoff. The owning contracts are installed PHP Frame [resource-schemas.md](../../vendor/schemastud/laravel-frame/docs/resource-schemas.md)
and `@schemastud/frame/docs/schema-purpose.md` in the paired JS package.

## Install the paired candidates

Start with the approval guide's new clone and committed Composer/pnpm installs, stopping before
creating its environment. Clone/check out the supplied PHP Frame, JS Schemastud and Beam candidates
into separate new development directories. Set these explicit roots while in the fresh host:

```bash
export PHP_FRAME_ROOT=/absolute/path/to/new-php-frame-candidate
export FRAME_JS_ROOT=/absolute/path/to/new-schemastud-candidate
export BEAM_UI_ROOT=/absolute/path/to/new-beam-candidate
export UI_BUILD_TOOLS=/absolute/path/to/new-build-tools
```

Use an isolated alternate Composer manifest/lock for the one PHP path overlay:

```bash
node --input-type=module <<'JS'
import { readFileSync, writeFileSync, copyFileSync, existsSync } from 'node:fs';
if (!process.env.PHP_FRAME_ROOT || existsSync('composer.proof.json')) throw Error('Set fresh explicit roots');
const manifest = JSON.parse(readFileSync('composer.json', 'utf8'));
manifest.repositories.unshift({ type: 'path', url: process.env.PHP_FRAME_ROOT,
    options: { symlink: true, versions: { 'schemastud/laravel-frame': 'dev-main' } } });
writeFileSync('composer.proof.json', JSON.stringify(manifest, null, 4) + '\n', { flag: 'wx' });
copyFileSync('composer.lock', 'composer.proof.lock');
JS
COMPOSER=composer.proof.json XDEBUG_MODE=off herd composer update schemastud/laravel-frame --no-scripts --no-interaction
```

Inspect that only Frame changes in the alternate lock; the canonical locks stay unchanged. The path
package's reference may be Composer's content-derived identity: also record the actual Git checkpoint
and source hashes. No PHP Beam/DataSchemas/Spatie update is part of this example.

Prepare new pinned build tools, then run the included local UI overlay helper. It refuses existing
source node_modules, resolves peers from this host's committed install and gives both source clones
an owned namespace so Frame points to the candidate. It changes only installed links in this host.

```bash
mkdir "$UI_BUILD_TOOLS"
(cd "$UI_BUILD_TOOLS" && printf '{"private":true,"packageManager":"pnpm@9.15.3","dependencies":{"tsup":"8.5.1","typescript":"5.9.3","zustand":"5.0.14"}}\n' > package.json && pnpm install)
node examples/prepare-read-schema-ui.mjs
(cd "$FRAME_JS_ROOT" && npm run build --workspace frame)
(cd "$BEAM_UI_ROOT" && npm run build --workspace beam-inertia)
```

Now run the approval guide's fresh environment/SQLite creation, key generation, registry cache,
normal installer and explicit ServiceRequestApprovalSeeder. Pick one available localhost port
explicitly (46550 in the proof). Then run its generation, host build, generated-consumer typecheck,
nav seeding, private actor issuer and localhost server. The private helper still requires a new
`database/service-desk.sqlite`, local environment, file sessions, sync queue and array mail.
Do not reuse the historical guide's old Beam candidate/build overlay after the paired preparation.

```bash
XDEBUG_MODE=off herd php artisan splicewire:beam:generate:assets --json
pnpm types:check
pnpm exec tsc --project tsconfig.service-request.json
pnpm build
XDEBUG_MODE=off herd php artisan test --filter='ServiceRequestSchemaPurposeTest|ServiceRequestApprovalTest|ServiceRequestTeamTest|ServiceRequestJourneyTest|ServiceRequestFrameTest|OperatorInspectionTest|ThemeSharedPropTest'
```

## Follow create, approval and read detail

Use the private approval actors for ordinary form logins, as in the approval guide:

1. As Member, create a request. Only Title and Status appear; open is the default and closed is valid.
   ID and Approval are absent; forged approval/actor/version values cannot replace server defaults.
2. As Owner, open that shared request. ID, Title, Status and Approval are disabled; Approval is pending.
   Save/Delete are absent. Confirm the existing Approve action once.
3. Approval changes to approved after the normal action invalidation/refetch, in the same session.
   Reload detail, then inspect/reload the list: both agree. Approve a closed request as Admin;
   its status stays closed. Run the actual generated browser consumer on another pending record.
4. Compare actual GET, workflow pin/history facts, local after-commit receipts and separate Display
   entries using the approval guide. Repeated approval409 and authority/input failures must preserve
   state/history/effects. The detail fields are presentation, not write authorization.

The browser adapter requests `/frame/resources/service-requests/schema?purpose=read` for this static
noneditable detail, and the old unqualified URL for create/write. Authorized invalid purpose returns422.
The schema socket addresses a resource, not a record: a valid outside-team actor receives global DTO
metadata200, while foreign record GET is404 with no row values. A teamless actor also receives this
ungated DTO metadata200 but record GET is403. Guest JSON schema access is401; protected operator Teams
schema is403 for a nonoperator even with an invalid purpose. There is no newly invented outsider
metadata policy or record-value disclosure in a schema. Preserve the owning reach and record gates.

## Contract and limits

Omitted/write uses editData??data through configured Generator.forRequest; read uses data through
configured Generator.forResponse. The existing resolver can supply an authorized custom schema or
return null for normal generation. Hidden/computed/output-name behavior follows that generator and
owning declarations. Public/custom narrowed record producers must supply a matching authorized schema;
this authenticated `publicRead:null` example does not prove anonymous or actor-specific read DTOs.

Frame's optional trailing purpose and query key distinguish write/read independently of bare/enriched
FormMode, transport authority and readOnly interaction. Old two-argument adapters keep default-write
compatibility; explicit read requires a paired capable adapter/server. A permission-disabled view of
an editable resource intentionally retains its legacy write schema/edit-shaped record. Generic heavy
widget dispatch is unchanged. Remote UI can reuse the host purpose-aware transport, but loader,
sandbox and negotiation are unproved here.

The independent JS fixture lives at `examples/frame-schema-purpose` in the paired Schemastud source.
Its own frozen install/types/build/browser demonstrate exported Frame/Seam/facets consumption without
Beam imports; its in-memory approval is not HTTP or database proof. The filtered catalogue proof is a
separate scratch setup, not standard refresh filtering or a full monorepo/catalogue preparation claim.
Full configured host PHPStan retains the two known migration errors35/47; no full host-suite,
PostgreSQL concurrency or remote runtime claim is made. Stop owned listeners/browser/proxy, release
capacity and use guarded scratch retention after proof.

Continue with the separate [read-only external request source](service-desk-external-requests.md) to
replace the backing through existing capabilities without adding write access.

Continue with [team approval usage](service-desk-approval-usage.md) to project committed workflow
facts into a read-only resource with live-team authority and the same paired stock UI.
