# Inspect your team's committed approval usage

For the current **one-host, one-request** trail, start with [the cumulative preview](service-desk-current-preview.md).
The setup/checkpoint commands below retain their own historical inputs; do not concatenate their fresh fixtures.

Current authoring compatibility: follow [Theme draft/Release adoption](service-desk-releases.md)
for the selected package pair and fresh/upgrade setup. Theme **Save draft** keeps published tokens
unchanged; explicit **Publish** releases the saved version (or requests review). Earlier slice proof
and candidate identities remain historical. This chapter's resource/access behavior is unchanged.

Continue the [paired read-schema guide](service-desk-read-schema.md) on a new private host at the
supplied current starter checkpoint. Approval usage is a read-only projection of existing committed
workflow facts. No commerce package, extra ledger, migration or payment provider is needed.

Use the unchanged paired candidates: PHP Frame `b9adc1ac13562f3e32d0c84926a1bd97c64e7f85`, JS
Schemastud `67a8c811b9026987ef4354dad280619932e70c64`, Beam UI
`614f463cb1daa65388b213b648bbf3ee35833e26`. These are local development candidates, not published
releases. Keep this starter's canonical Composer/pnpm locks and all other peers pinned.

## Install and run

Follow the paired guide's committed Composer/pnpm installs, new source clones and pinned build tools,
one-package PHP Frame alternate Composer manifest/lock and `prepare-read-schema-ui.mjs` preparation.
Build Frame and Beam from those explicit clones. Then follow the approval guide's new local
`database/service-desk.sqlite`, private environment, key generation, registry cache, normal installer
and explicit `ServiceRequestApprovalSeeder`. Do not use an owner checkout, environment or database.

Choose an unused localhost port explicitly (46630 in the example) and use it as APP_URL and the
server port. Finish in the new host:

```bash
XDEBUG_MODE=off herd php artisan splicewire:beam:generate:assets --json
XDEBUG_MODE=off pnpm types:check
XDEBUG_MODE=off pnpm exec tsc --project tsconfig.service-request.json
XDEBUG_MODE=off pnpm build
XDEBUG_MODE=off herd php artisan splicewire:beam:ux:seed-nav
XDEBUG_MODE=off herd php scripts/service-request-approval-fixture.php storage/app/private/approval-actors.json
XDEBUG_MODE=off herd php artisan test --filter='ApprovalUsageTest|ExternalRequestTest|ServiceRequestSchemaPurposeTest|ServiceRequestApprovalTest|ServiceRequestTeamTest|ServiceRequestJourneyTest|ServiceRequestFrameTest|OperatorInspectionTest|ThemeSharedPropTest'
XDEBUG_MODE=off herd php artisan serve --host=127.0.0.1 --port=46630 --tries=1 --no-reload
```

Keep fixture credentials in that private file. The existing helper refuses reused users/teams/output,
nonlocal/non-SQLite hosts, wrong database location/name, non-file sessions, asynchronous queue or
non-array mail. It issues ordinary verified Owner/Admin/Member actors in A, an outsider in B and a
teamless actor, plus the separate operator/Theme actors from earlier chapters. No new Root or usage
permission grant is needed. Existing external-source setup is optional for its own guide, not needed
to read this database-backed usage.

## Ordinary UI and actual client

1. Log in through the normal form as Member. Under Service desk, open **Approval usage**. One row
   shows Meter `service-request.approval`, Quantity0, Unit `approvals`, Period `lifetime`. Open it;
   the same fields and ID appear disabled. There are no New/Save/Delete/Approve/Top up/Upgrade actions.
2. Use Service requests to create an open request. Sign in separately as Owner, open that shared
   request and confirm the existing Approve action. Its marking and fact commit together. Navigate
   to Approval usage, refetch/reload and inspect Quantity1; reload the disabled detail too.
3. Create a closed request as Member and approve it as Admin. Approval usage becomes2 after actual
   navigation/refetch/reload; closed remains its ordinary request status. A repeated approval409 or
   Member/foreign refusal adds nothing. Observe stock list/detail in light and dark modes.
4. Log in as B's outsider: their own summary is0, with no A values. Teamless and removed members are
   forbidden, not shown a successful zero. This guide does not add a team-switch UI or cross-tab push.

At the zero, one and two checkpoints, run the real generated-route/DTO consumer against the same
host, setting the expected quantity accordingly. It uses bearer credentials privately and prints
only safe rows and method/path/status:

```bash
APPROVAL_USAGE_ACTORS=storage/app/private/approval-actors.json \
APPROVAL_USAGE_BASE_URL=http://127.0.0.1:46630 APPROVAL_USAGE_EXPECTED=0 \
pnpm example:approval-usage
```

Change EXPECTED to1 and2 only after those actual approvals. Each run sends two GETs through the
actual generated `service-request-usage.index` route, verifies A's integer quantity and B's0, and
validates the one-row cursor envelope at runtime. It does not execute generated hooks or claim typed
pagination metadata. Generic REST has index only; `/api/service-request-usage/approvals` is not a
mounted detail endpoint. Stock Frame supplies detail at `/frame/resources/service-request-usage/records/approvals`.

## Declaration and exact source

Read [ApprovalUsageData](../../app/Data/ApprovalUsageData.php),
[ApprovalUsageBacking](../../app/Backings/ApprovalUsageBacking.php) and
[ApprovalUsageReadPolicy](../../app/Authorization/ApprovalUsageReadPolicy.php). The particle declares
`input:false`, `publicRead:null`, the read DTO, backing, explicit subject-free ability, stock framing
and read-only capabilities. `AppServiceProvider` defines `service-desk.usage.read` from live selected
membership; `config/frame.php` seats it in the existing tenant realm. `routes/api.php` mounts index
through the existing authenticated particle transport. Installed REST's ResourceReadPolicy adapter
delegates that same ability; a model-less policy string alone is not that REST read decision.

Every backing call resolves the authenticated User's selected team through
[ServiceRequestTeam](../../app/Support/ServiceRequestTeam.php), querying membership afresh. It joins
`workflow_transition_facts.subject_id` to retained `service_requests.id` and counts facts satisfying:

- subject_type `service-request`, transition `approve`;
- from exactly `[pending]`, to exactly `[approved]`;
- the joined request's currently persisted team_id equals the freshly resolved selected team.

One eligible immutable fact contributes one integer **approval**. It counts facts, not distinct
request IDs, current approval markings, attempted operations, activity Display entries or log
receipts. Open/closed status and actor do not change its unit. There is no date cutoff: Period is
**lifetime**, with no billing month/reset or money/cost conversion. No eligible facts returns one
summary row with quantity0; an orphan, null-team row or nonmatching fact contributes nothing.

The fact's actual opaque actor/version/time stays on workflow history. The inspecting user need not
have caused it. Attribution uses the request's **current persisted team**, while admission uses the
viewer’s **current membership**. The accepted host offers no request transfer/delete flow and team_id
is outside writable input. This is retained/team-stable usage, not a historical team snapshot after
reassignment or deletion; those future lifecycle changes require a new accounting decision.

No usage read writes another fact or wallet debit. Existing LifecycleService commits marking and fact
atomically; a failed/rolled-back transition adds no usage. Approval's policy, transition, status,
version pin, after-commit receipt and separate Display behavior remain the earlier chapter's contract.

## Authority and failure/recovery

Guest JSON reads are401; authenticated teamless/stale/removed membership is403 before the usage
source is queried, even with a cached currentTeam. Member/Owner/Admin can inspect their selected
team; approving still requires the existing Owner/Admin authority. Missing summary ID is404 for an
authorized reader. The explicitly gated Usage schema exposes metadata only. It does not alter the
older ServiceRequest global-metadata versus scoped-record distinction from the paired guide.

Caller team/actor/meter/period/quantity parameters cannot change the computed result. Unsupported
`filter[...]` inputs reject422. Frame writes refuse403 and the unmounted REST write refuses405;
readOnly UI is presentation alongside these backend boundaries, not the authorization mechanism.

Database failure remains non2xx. On a disposable proof host only, temporarily renaming its fact table
is a source-failure control: both list/detail show the stock error and Retry rather than a successful
zero. Restore the exact table/store before Retry and confirm the same count. Keep this injection
private to owned proof; do not edit product source or add an HTTP fault switch. Use a separate
baseline around intentional approvals, fixture selection/removal and ordinary read/refusal/recovery.

## Limits and verification

The focused suite exercises exact arrays, wrong subject/transition, additional same-subject fact,
orphan/null-team/current-marking counterexamples, live A/B/A and cached removal, actual approval and
repeat, rollback, writes and source failure. Exported quantity types and runtime envelope checks have
separate negative controls. Full configured PHPStan retains the two known unchanged migration errors;
a focused green result is not full-suite health.

Existing commerce packages provide wallets/budgets and tenant-oriented meter/billing contracts, but
this host installs none of them. This projection is not allowance enforcement, a concurrent hard cap,
a CostEvent ledger, historical billing attribution, subscription, invoice, charge, marketplace or
production automation provider. Those integrations need separately selected work.

Stop owned listeners/browser/proxy, release capacity and use guarded scratch retention/teardown.
Keep private credentials/SQLite under the disposable host; do not publish this fixture or touch owner
artifacts. No new catalogue is necessary for this host-only reuse of the stock resource shell.
