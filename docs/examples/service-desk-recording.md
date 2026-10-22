# Record a published Theme locally

Follow [the Release guide](service-desk-releases.md) in a disposable host using the exact
slice15 candidate checkpoints: the existing PHP Frame/UX/workflows overlay and paired
Beam/Schemastud UI builds. Rebuild Frame as well as UX/Inertia; the default detail/action forms
now have independent IDs. Use the existing canonical workflow-peer generator and
prepare-release-ui command. No additional dependency or shipping lock change is needed.

The database versions and publication pins remain authoritative. Save draft does not commit.
An explicit Release completes its fixed members' mirrors, then records their exact frozen bytes
in one local commit. It never pushes. A recording failure leaves publication live.

## Fresh and existing hosts

Keep the Release guide's fresh SQLite/private actor setup, then publish the one additive migration:

```sh
XDEBUG_MODE=off herd php artisan vendor:publish --tag=beam-ux-migrations
XDEBUG_MODE=off herd php artisan migrate --force
XDEBUG_MODE=off herd php artisan popcorn:registries:cache
XDEBUG_MODE=off herd php artisan splicewire:beam:generate:assets --json
pnpm build
```

After the existing approval-actors fixture is created, prepare its independent reviewer with
`XDEBUG_MODE=off herd php examples/prepare-release-reviewer.php`. This guarded disposable-only
helper adds the existing admin actor to the Theme team's Admin membership through TeamProvisioner.
It does not grant the separate owner/Member/diagnostics-only actors UX authoring, or switch the
reviewer's selected Service desk team.

The one new stub is shared/create_beam_ux_release_recordings_table.php. On an accepted14 guide
database, publish without force-replacing existing stubs. Old Releases, members, versions, heads,
pins and mirror bytes remain unchanged; old published Releases without a receipt honestly show
recording unavailable. There is no automatic recording backfill.

The starter explicitly places beam-ux-release-files in frame.realms.tenant and maps its console
path to /release-files using RouteContextPlan. Its authenticated API prefix is
/beam-ux-release-files. EditorRoutes::mount mounts its read and Commit now operation inside the
existing auth+verified group. Another host must choose a console path distinct from its API
prefix and explicitly place the resource in a realm. Release links derive from that route context.
Global diagnostics/Mirror status policy is unchanged.

The configured local mirror disk must lie inside this disposable working copy. A normal clone
already has Git. For an archive only, initialize Git and make its initial commit first. Configure
identity locally, and retain any existing hooks:

```sh
git rev-parse --show-toplevel
git config user.name 'Release guide'
git config user.email 'release-guide@example.invalid'
git status --short
```

Keep another staged file plus unrelated unstaged/untracked edits as controls. Capture their bytes,
staged patch and real index hash before publishing.

## Ordinary publication

Log in through the normal form as the existing guide fixture's theme-owner. Account → Theme →
change Site theme Accent → Save draft. Save waits for its saved-version base; a failed history
read offers Retry history. Readers, publication pin and Git still see the previous publication.

Click Publish. With review off, the Release becomes published and Local Git record shows its SHA.
With review required, an independent reviewer still approves the selected saved version before
publication; a newer draft does not slip in. The existing History row shows the Release reference
and SHA. Git recording cannot bypass review.

Copy the Release ID from Files recording and inspect the effects:

```sh
XDEBUG_MODE=off herd php examples/inspect-release-recording.php RELEASE_UUID
git show --format=fuller --stat RECORDED_SHA
git diff-tree --no-commit-id --name-only -r RECORDED_SHA
git status --short
```

The read-only inspector separates publication/recording and compares fixed member/pin/mirror
hashes without exposing private repository/preparation paths. Inspect the commit's actual blobs
and parent, not just its path list. Only this Release's member mirror files belong in the commit.

The real index bytes and unrelated staged/unstaged/untracked contents remain unchanged.
Advancing HEAD with that unchanged index can reverse-stage MEMBER changes. Identical total
git status is not promised; the recorder does not silently reconcile a developer's index.
Republishing bytes already identical to the parent is an explicit exception: recorded baseSHA,
noChange:true, zero new commits and honest no-new-commit wording.

## Hook failure and Files recovery

In the disposable copy only, preserve any existing pre-commit hook and install an executable hook
that exits 1. Normal Git identity, signing and hooks apply; recording never bypasses them.
For linked worktrees, discover directories rather than assuming .git is a directory:

```sh
git rev-parse --absolute-git-dir
git rev-parse --path-format=absolute --git-common-dir
```

Change/Save another draft and Publish. The publication request succeeds, fixed pin/mirror move,
and the separate recording fails. Open Files recording from the Release summary. The row names
the file, explicit Release ID and every member of the whole Release.

Commit now opens the stock action form. Confirm that Release ID. While the hook fails, the
action returns field validation feedback and keeps the form open. Cancel/reopen preserves the
read-only detail, labels and focus. Repair the hook, then submit again. The same receipt records
one verified SHA. Reload Files and return to Theme: existing History and Release summary show
the recovered SHA. Retry never republishes, changes a pin or takes the working draft.

The declared operation is POST /beam-ux-release-files/{memberId}/commit-now with input
{releaseId: UUID}; authenticated cookie CSRF applies. The addressed row must belong to that
Release. No path, body, ref or actor override is accepted. The default typed Inertia client exposes
recordReleaseFile(memberId, releaseId); custom mounts supply their own transport. Stock Frame
actions read the declared schema. Repeating an already-recorded operation returns the same result.

## Authority and recovery limits

Initial recording and retry recheck the existing direct/built publication decision for ALL members.
An unrelated diagnostics/operator grant is insufficient; an inaccessible member hides the whole
Release from scoped Files. Guest, Member and revoked actors cannot record. Proven central model
and Release residency AND no active tenancy are required. Central pins/Root do not override
active or unknown tenancy.

Recording needs an enabled local mirror, one normal/linked local repository, an attached born
branch and contained regular files. Missing Git/repository, hook/signing refusal, merge/rebase/
cherry-pick/revert/bisect/lock state, unsupported sparse/split/worktree config, symlinks/escapes/
submodules/case-colliding paths stop recording visibly. Exact new untracked member files are
allowed. Missing/drifted files are not overwritten by retry: resolve them deliberately.
Take-down/null-ref/deletion/move recording is unavailable here; publication behavior remains.
Existing nonlocal mirrors still publish but get no local Git recording.

Cooperating local application requests serialize by Git common directory. Native hooks run
against private HEAD/index, and a verified candidate is saved before branch compare-and-swap.
External branch advancement cannot be overwritten. External concurrent checkout/rebase/reset
is unsupported; detected changes refuse. Hooks are not sandboxed.

The frozen receipt stores encoded member bytes/path/mode/hash, repository identity and expected
parent/tree/candidate; it is not editable version history. A hook failure proven to have created
no candidate can start a new serialized attempt on the current parent. Saved/orphan candidates
are never replaced by a guessed second commit.

If Git succeeds but result persistence fails, another process recovers the SAME verified candidate
only when reachable on the captured branch. Installation intent is saved before CAS. An
unreachable result after installation started, unknown/duplicated/mismatched candidate or
rewritten history refuses recovery. Diagnose that state; deleting a pending receipt to retry
blindly is not supported.

Local recording now composes with bounded canonical Theme draft import and missing-only Restore
in the current-file panel. Batch import, existing-file overwrite, moves/deletions, generic
beam-versioning panel adoption and remote push remain separate. Version snapshots
stay immutable; one version can show multiple Release references.

Owning tests require an explicit registered disposable BEAM_RECORDING_TEST_ROOT; product Git
tests never target candidate checkouts. Retain Release/approval/usage checks. Full UX844 selected
runtime failures and the two host PHPStan migration errors remain explicit; focused recording
proof does not establish full-suite health. Build, interaction, visual and consumed-source proof
are distinct in slice15 evidence.

For current-pin local comparison, guarded import and missing-only Restore, continue with [Theme files](service-desk-theme-files.md).
