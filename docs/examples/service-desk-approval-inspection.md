# Inspect committed request approvals

For the current **one-host, one-request** trail, start with [the cumulative preview](service-desk-current-preview.md).
The setup/checkpoint commands below retain their own historical inputs; do not concatenate their fresh fixtures.

An Owner, Admin or Member can inspect a request in their currently selected team at
`/service-requests/{id}/inspection`. Open a request in the Service desk console to obtain its ID,
then visit that URL. **Back to request** returns to the existing console detail.

The page is a read surface. Owners and Admins approve through the existing request action;
Members can inspect but cannot approve. A Root role alone does not grant access to another
team's request. Membership is checked again on every read.

## Prepare the example

Use a fresh local host and database as described in [the approval guide](service-desk-approval.md).
Keep optional marketplace and wallet examples disabled; this page needs no additional package.
Before the native installer, run `php artisan popcorn:registries:cache` after package discovery.
Run the native installer with `--no-seed`, then seed the approval definition:

```sh
XDEBUG_MODE=off herd php artisan splicewire:beam:install --no-seed --no-interaction
XDEBUG_MODE=off herd php artisan db:seed --class='Database\Seeders\ServiceRequestApprovalSeeder'
XDEBUG_MODE=off herd php artisan splicewire:beam:generate:assets --json
pnpm run build
```

Use the host's supported PHP toolchain. Every isolated Composer preparation should set
`COMPOSER_HOME` and `COMPOSER_CACHE_DIR` to private directories inside that run before its first
Composer command. The committed shipping lock still has known security advisories; the isolated
verification composition patches only CommonMark to 2.10.4 and HttpFoundation to 8.1.9. This guide
does not replace that lock or select a package release.

## Create, approve, inspect

Create a request through the Service desk console, whose authenticated browser transport is
`POST /frame/resources/service-requests` with `{ title, status }`; its response contains `data.id`.
The separate `/api/service-requests` REST door uses token authentication in this host. A browser
session alone is not a substitute for that API credential. No authentication middleware changes
are needed for the inspection page.

The generated client declares `useServiceRequestsApprove` and
`useServiceRequestsApprovalHistory` in `resources/js/generated/hooks/service-requests.ts`.
The history hook returns `ReadRequestApprovalHistoryOutputData`, whose fact `from` and `to` are
`string[]`. Hooks belong inside React components:

```tsx
const approve = useServiceRequestsApprove();
const history = useServiceRequestsApprovalHistory({ id: requestId });

// An Owner or Admin performs the existing action for the returned request ID:
approve.mutate({ params: { id: requestId } });
// Refetch after the action. A repeated approval refuses with 409.
void history.refetch();
```

The host router declares the inspection page. The generated client declares the input-free GET
operation `/service-requests/{id}/approval-history`. The page uses those generated route and Data types with
a small status-preserving read adapter so it can distinguish denied reads and cancel obsolete
requests. It hides cached title and facts during a new read or after a denial. Changing teams
requires a fresh authorized read. Extra subject, class, connection, version or filter inputs are
rejected; the request ID is the only selector.

## Read the result

Current approval and current definition pin are shown separately from the committed timeline.
Each fact shows its ID, transition, place lists, recorded user reference, UTC occurrence time and
exact fact-time version ID. The definition name, lineage key and version number come from that
historical version, even after a newer version becomes active. Actor references are recorded
identifiers, not an identity-directory lookup.

A successful empty read says **No committed approval transitions.** It does not prove the request
was never attempted. A source failure says **History could not be loaded**, with a read-only Retry;
access denial does not offer Retry or display an earlier successful result.

**Version not recorded** means the fact has no version ID. **Historical definition unavailable**
means a recorded version or its lineage is missing; the fact retains its exact ID. Neither case
substitutes the current or latest definition. An unreadable definition store is a failed read.
Unknown or unsafe actor references remain **Unknown actor**.

Only committed transitions are shown. Refused attempts and effect delivery are not recorded here.
A rolled-back approval creates no fact; an observer failure after commit can leave a valid fact
without successful delivery. This page does not expose raw context, blueprint, logs, receipts or
credentials, and offers no replay or approval action. The complete per-request timeline is bounded
to this example's one-way approval flow, not a general history pagination API.

Run the focused checks with:

```sh
XDEBUG_MODE=off herd php artisan test --filter=RequestApprovalInspectionTest
```

The complete generated registry currently also contains accepted Release/Files API declarations
from a later source composition. Full generation with the shipping UX package does not reproduce
those unrelated entries. This example preserves them and propagates only the native-generated
history route, alias and hook; its new DTO schemas are native outputs. The separate API publication
prerequisite must eventually make full generation match the tracked registry.
