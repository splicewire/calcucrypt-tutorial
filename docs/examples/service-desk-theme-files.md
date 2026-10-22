# Edit an owned Theme mirror deliberately

Start with [the local recording guide](service-desk-recording.md), using the selected slice16
starter/PHP UX/Beam candidates and unchanged accepted Frame/Schemastud/workflows checkpoints.
Keep all source/builds in disposable clones and the private SQLite/actor fixture. The accepted15
upgrade uses its copied database and owned files: no new dependency, lock, migration or table is
introduced. Install the same three PHP overlays, refresh registries and regenerate/build with the
existing canonical workflow-peer and prepare-release-ui recipe from the Release guide. Rebuild
Beam UX and Inertia; preserve the original preview/theme for catalogue proof.

```sh
XDEBUG_MODE=off herd php artisan popcorn:registries:cache
XDEBUG_MODE=off herd php artisan splicewire:beam:generate:assets --json
pnpm build
XDEBUG_MODE=off herd php artisan route:list --path=beam-ux-release-files
```

The three selected routes are GET inspect-file, POST take-file-draft and POST restore-missing-file.
They take a Release member ID, not a file path. The generated client includes typed inspection,
input and result projections. The starter supplies its own /account/theme editor href through
Beam Inertia configuration. No starter form adapter is needed.

## File to draft to reviewed publication

1. Log in normally as the existing theme-owner fixture actor. Publish a Theme through Account →
   Theme first, then open its Files recording link and the read-only historical member detail.
2. In this disposable clone only, locate the mirror with the recording inspector. Preserve its
   complete generated CSS; change one existing `--theme-site-accent` hexadecimal value. Keep the
   canvas, shell, site, responsive and any extension tokens intact. Do not edit the generated header,
   selector, whitespace or key order. The bounded import accepts its exact canonical representation.
3. Reload comparison. Current published bytes and Local bytes show the change; historical Release
   version remains distinct from current pin/head. Git status/mtime do not decide the comparison.
   Take file as draft opens a confirmation capturing this inspection. Cancel returns focus; reopen
   and confirm deliberately. Nothing is published or committed. Mirror bytes remain your edited file.
4. Open Theme shows the imported working draft. Readers and the published pin still use the old body.
   With review required, Publish submits this saved version. Save a newer draft before an independent
   admin reviewer approves/publishes the pending Release; the selected imported version alone goes
   live. Verify the pinned body, exact mirror bytes, new local SHA and History receipt, with unrelated
   index/file changes preserved. This is the existing Release contract, not a special import publish.
5. If head/pin/file/placement changes after inspection, the confirmation fails visibly. It retains
   captured tokens and requires explicit Reload; it does not silently rebase or retry. Invalid or
   unsupported CSS has no Take action. Canonical empty is distinguished from malformed source, but
   token clearing/removal is outside this import.

## Restore only a missing file

Preserve and move the owned mirror out of its configured path; keep a newer draft as a control.
Reload comparison → missing → Restore missing published file → confirm. It exclusively creates
exact current PIN bytes while head, history, draft, receipt and Git stay unchanged. If another file
appears after inspection, Restore refuses and preserves it. Existing different files cannot be
overwritten: preserve/move them deliberately before a new inspection. A fresh identical-file call
is an explicit no-op. Failed/partial creation preserves the ambiguous target; inspect/preserve/move
it before retry. Never delete unknown bytes or blindly repeat a failed Restore. Noncooperating edits
AFTER exclusive creation are outside the promise; detected readback mismatch reports failure.

Member/diagnostics-only/revoked authors and direct publishers without edit cannot read source or
reconcile. Active/unknown tenancy, noncentral placement, unsafe paths and unsupported codecs refuse.
No Git executable is required for comparison/import. Whole-Release Commit now remains separate.
Broader batch reconciliation, arbitrary CSS/codecs and existing-file overwrite remain unimplemented.

If Publish overlaps a local writer under SQLite, its earlier workflow read can prevent lock
upgrade. A recognized busy/locked statement after confirmed rollback returns409 with Reload and
explicit retry guidance; pin, DB and recording remain unchanged. Reload after contention and
retry deliberately. This is not universal busy elimination, automatic retry or callback replay.
Other database faults retain500; post-commit recording failures keep their existing separate result.
