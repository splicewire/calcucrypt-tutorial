# Save a Theme draft and publish a fixed-version Release

This local development guide adopts existing EP-15. It changes Theme **Save** into **Save draft**;
**Publish** creates a server-owned Release of one. The selected local recording increment adds
member-only Git records and scoped Files recovery; follow [that chapter](service-desk-recording.md)
after this Release setup. The database's
version history/head and published pin remain authoritative; a mirror is a published projection.

Use the supplied starter, PHP UX and Beam authoring checkpoints, plus PHP workflows
`7ee34de5511aecbaacf173dcd1b39cd6397e3d65`, PHP Frame
`b9adc1ac13562f3e32d0c84926a1bd97c64e7f85` and JS Schemastud
`67a8c811b9026987ef4354dad280619932e70c64`. Exact worktree paths/checkpoints and receipts are supplied
with the candidate. These are local candidates, not published package versions. The original Beam
`614f463` branch remains intact; the authoring branch ports Theme/Release/transport only.

## Fresh disposable host and explicit dependencies

Start with the [paired guide](service-desk-read-schema.md): new clone, committed Composer/pnpm
installs, new private `.env` and `database/service-desk.sqlite`, local URL/unused port, file sessions,
sync queue, array mail and `ACCOUNT_SEED_DEMO_USERS=false`. Never reuse an owner checkout/database.
Before registry caching or installation, make an **alternate** Composer manifest/lock in this clone.
In addition to the paired PHP Frame path repository, add the exact candidate UX and workflow paths:

```json
{
    "type": "path",
    "url": "/absolute/path/to/selected/laravel-beam-ux",
    "options": {
        "symlink": true,
        "versions": { "splicewire/laravel-beam-ux": "dev-main" }
    }
}
```

Use the same shape for `splicewire/laravel-beam-workflows`, selecting the exact source above. Copy
`composer.json` to `composer.proof.json`, prepend these three path repositories, and copy the canonical
lock to `composer.proof.lock`. Then run in the new host:

```bash
COMPOSER=composer.proof.json XDEBUG_MODE=off herd composer update \
  schemastud/laravel-frame splicewire/laravel-beam-ux splicewire/laravel-beam-workflows \
  --no-scripts --no-interaction
XDEBUG_MODE=off herd php artisan key:generate
XDEBUG_MODE=off herd php artisan popcorn:registries:cache
XDEBUG_MODE=off herd php artisan splicewire:beam:install --no-interaction
XDEBUG_MODE=off herd php artisan db:seed --class=ServiceRequestApprovalSeeder
XDEBUG_MODE=off herd php artisan db:seed --class='Splicewire\Beam\Ux\Seeders\ThemeSeeder'
```

Keep canonical locks unchanged. Compare alternate lock references with the committed lock: only the
three selected packages may differ. UX's manifest require set is unchanged from installed508cdb1,
but its selected source has102 changed src/database/manifest paths. Workflow adoption over locked
b328f2de adds exactly the transaction participant interface and six invocation lines; there are no
workflow dependency/migration changes. The hook is required for pins/marking/facts to share rollback;
an empty compatibility interface would break that guarantee. UX's bounded dashboard compatibility
keeps older Beam's authenticated OPEN_ABILITY and host-defined gates; it does not adopt newer realm
security semantics. Beam PHP and every other runtime package remain locked.

## Build the selected UI and its declared peer

Use new clones of the selected JS Schemastud and Beam authoring checkpoints, and the paired guide's
pinned tsup8.5.1/TypeScript5.9.3/zustand5.0.14 tools. Include the existing beam-ux parser
build dependencies explicitly in that disposable tool install: recast0.23.12, @babel/parser7.29.7
and @babel/types7.29.7. Set FRAME_JS_ROOT, BEAM_UI_ROOT and UI_BUILD_TOOLS
as in that guide. Instead of `prepare-read-schema-ui.mjs`, run:

```bash
# A NEW output directory under your existing scratch parent:
XDEBUG_MODE=off herd php examples/generate-release-workflow-peer.php /absolute/scratch/workflow-peer
RELEASE_WORKFLOW_PEER=/absolute/scratch/workflow-peer \
  node examples/prepare-release-ui.mjs
npm --prefix "$FRAME_JS_ROOT" run build --workspace frame
npm --prefix "$BEAM_UI_ROOT" run build:action-form --workspace beam-workflows
npm --prefix "$BEAM_UI_ROOT" run build --workspace beam-ux
npm --prefix "$BEAM_UI_ROOT" run build --workspace beam-inertia
XDEBUG_MODE=off herd php artisan splicewire:beam:generate:assets --json
XDEBUG_MODE=off pnpm build
XDEBUG_MODE=off herd php artisan splicewire:beam:ux:seed-nav
XDEBUG_MODE=off herd php scripts/service-request-approval-fixture.php storage/app/private/approval-actors.json
```

The build-only peer comes from installed WorkflowProjectionData/WorkflowTransitionData through the
canonical Spatie transformer and existing TransformTypesStage. Missing/ambiguous/dangling projection
notes fail generation. It adds no runtime policy, ambient type suppression, Tower package or shipping
version. The action-form public subpath reuses existing WorkflowActionForm without loading the
unrelated graph/admin barrel. Its old barrel remains exported. The full workflow/admin build needs
other generated declarations not installed by this Beam-only host; this recipe claims the bounded
subpath only. Generated declarations carry package-relative imports, never private fixture paths.

The fixture helper supplies the earlier ordinary actors and Theme-only owner; credentials stay in
its private mode600 file. For the recording guide, run `XDEBUG_MODE=off herd php examples/prepare-release-reviewer.php`
to add the existing tutorial Admin to the Theme team through TeamProvisioner; no new grant is needed. Start your chosen localhost port:

```bash
XDEBUG_MODE=off herd php artisan serve --host=127.0.0.1 --port=46650 --tries=1 --no-reload
```

## Ordinary authoring and published consumer

1. Sign in as the Theme owner and open account **Theme**. The server supplies the entry ID/schema;
   the page imports packaged ThemeEditor through beam-inertia. Change Site theme Accent (and an
   appropriate foreground), leaving Canvas theme separate. **Save draft** reports that readers still
   receive the published Theme. Inspect head advancing while published pin, mirror and ordinary
   Service requests list/New/read-only detail colors stay unchanged after navigation/reload.
2. Click **Publish**. With review off by default, the existing publish transport posts a fixed saved
   reference to EntryPublishOp → ReleaseService::requestOne, and returns a **direct** Release of one.
   The existing ReleasePanel shows Published. Head/pin/mirror and resolved tokens agree; request
   controls change on their next page request, in light/dark, without a rebuild. Reload Theme: history
   marks the published version and the Release remains inspectable. Published Releases do not show a
   stale warning merely because their own successful publication moved the pin.
3. Opt into review in this disposable host with `BEAM_UX_RELEASES_REVIEW_REQUIRED=true` in its private
   `.env` (clear cached config/restart your own server if used). Save another draft and Publish. The
   panel waits for review, with no pin/mirror/consumer change. Save a newer draft: the Release still
   names its previously selected version. Self approval refuses. An independently authorized reviewer
   logs in normally, opens Theme and uses existing **Approve**, then **Publish now**. The selected
   version publishes; the newer draft stays at head. No UI-computed policy bypasses server admission.
4. Membership changes revoke review; moved pins/markings require explicit rebase or drop. Guest,
   Member and revoked-author attempts cannot publish. The existing service returns validation422 for
   moved Release state; draft expected-head conflict remains409. Verify actual refusal effects rather
   than treating disabled/absent buttons as authorization.

The form still preserves namespaces it does not display, including shell. Existing save-body remains
an older immediate-publication API for its existing callers; ThemeEditor no longer calls it. Other
editors retain their accepted behavior. Theme is host-global branding, not a team-specific skin.

## Upgrade an owned old-schema copy

First install the accepted old starter/locks into another disposable clone with a new SQLite file.
Seed Theme and actual requests/approval facts, publish one token using its old supported path, then
save a different unshipped draft. Back up that SQLite database and mirror files, capturing entry/
particle bodies, every version/head/pin, workflow/team/request/usage rows and old migration bytes.
Copy those owned fixtures into the upgrade host, keeping its DB path private. Apply the alternate
three-package overlay above. Boot/registry checks must succeed before migration; no other package
upgrade is implicit.

Review old host migration customizations first. In this uncustomized disposable fixture, deliberately
republish **only UX migration stubs**, then migrate:

```bash
XDEBUG_MODE=off herd php artisan vendor:publish --tag=beam-ux-migrations --force --no-interaction
XDEBUG_MODE=off herd php artisan migrate --no-interaction
XDEBUG_MODE=off herd php artisan popcorn:registries:cache
```

The supported publisher keeps the prior timestamped filenames. Force refreshes already-published
stubs; it does not re-run their already-recorded migrations. New stubs add page settings, Release/
members tables and origin; changed baseline is intentionally reviewed/refreshed too. Never force over
customized owner migrations blindly. Compare exact before/after path/byte inventory and migration
ledger. Existing history-without-pin must not silently publish, nor may an unshipped head replace a
pin. Compare all old rows using their old columns (new nullable SEO/timestamp columns are separate),
excluding only the migration ledger from row equality. Inspect new empty Release tables separately.
Check original mirrors byte-for-byte and run a **new post-upgrade** draft/Publish and old approved
request/Usage consumer journey. This is separate from fresh installation proof.

## Checks and limits

Run ReleasePrerequisiteTest with the paired dependencies and published migrations, together with
ApprovalUsageTest, ExternalRequestTest, ServiceRequestSchemaPurposeTest, ServiceRequestApprovalTest,
ServiceRequestTeamTest, ServiceRequestJourneyTest, ServiceRequestFrameTest, OperatorInspectionTest,
ThemeSharedPropTest, ThemeEditorSeatTest, RealmAuthoringGatesTest and EntitlementGuideTest. Generation,
production build/types, PHP style/static and package UX/transport/participant tests are distinct from
actual browser/SQLite/mirror proof. Inspect generated routes and actual response data.

Full current UX suite against the older locked core has known incompatible binding/realm test
expectations and absent optional calendars; a focused pass does not make that full suite green.
Full host PHPStan also retains the two previously recorded migration errors. No full monorepo,
physical-tenant UI, scheduling runtime, batch/Files import/Restore, push or
publication/deployment claim is made. The [recording chapter](service-desk-recording.md) covers
the first EP-16 record/SHA/retry increment; broader EP-16 remains unfinished.
Stop owned listeners/browser/proxy, release capacity and retain/teardown only guarded private fixtures.
