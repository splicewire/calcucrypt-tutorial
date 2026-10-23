# Recover a missing local checkout in a disposable example

Use a developer installation (including dev dependencies) of this starter. It includes
Doctor and Surgeon; a production `--no-dev` install need not expose these commands.
This example changes metadata in a new, exclusively owned scratch fixture. It does
not repair your application's real overlay or install its illustrative packages.

The command host is the fresh starter at `$R/host`. The target is a separate registered
fixture `$F` under the same owned run root. Use your actual OpenRig seat and row with
its supported scratch tool. On a Mac with Herd, set `PHP` to Herd's PHP executable.
All dependency preparation must use the reviewed scratch-only `composer-leg` tool
with private HOME/cache from the first command; see the isolated host preparation
instructions. Do not copy an owner vendor tree, `.env`, auth file or database.

```sh
# TOOL points to a reviewed ecosystem clone with nested scratch support (b4b2825ef or later).
# R is your freshly registered, exclusively owned scratch root; ROW is your actual queue row.
F=$(node "$TOOL/rigs/launch/bin/scratch.mjs" create --parent "$R" --prefix overlay-example- --row "$ROW")
PHP=$(XDEBUG_MODE=off herd php -r 'echo PHP_BINARY;')
cd "$R/host"
python3 examples/overlay-recovery/fixture.py prepare --root "$R" --fixture "$F"
```

`prepare` refuses a nonempty fixture. It creates an independent Git baseline, a
positive disposable-example marker bound to root/fixture identity and lock hash,
and a private before-byte backup outside the fixture. It does not bless an existing
starter or site as disposable. Its metadata is deliberately small:

`composer.json`:

```json
{"name":"fixture/overlay-example","require":{"fixture/kept":"dev-main"}}
```

`composer.local.json`:

```json
{
  "require": {"fixture/kept":"dev-main"},
  "repositories": [
    {"type":"path","url":"packages/kept"},
    {"type":"path","url":"packages/stale-checkout"}
  ],
  "extra": {"example-canary":["preserve",25]}
}
```

`packages/kept/composer.json` names `fixture/kept`. Its directory exists; the stale
checkout is deliberately absent. The illustrative lock and canary are preservation
controls, not a resolved Composer installation.

## Diagnose and preview

Run the native commands from the command host:

```sh
XDEBUG_MODE=off "$PHP" artisan surgeon:overlay list --base="$F" --json
XDEBUG_MODE=off "$PHP" artisan surgeon:overlay remove packages/stale-checkout --base="$F" --json
```

The list reports an active overlay, one required present package and a broken path.
Look for `overlay.broken-path-repo`, status `fail`, and the `overlay-remove`
suggestion with URL `packages/stale-checkout`. The missing checkout's package name
can be null, so use the exact URL. **List exits 0 even with this FAIL.** Read the
findings, not just the process status.

The removal command previews by default. Its JSON reports the verb, base and
`composer.local.json` modify action. It is not a complete before/after diff. No
sidecar, file or index change is expected. To also assert unchanged bytes and index,
use the example's read-only `diagnose` and `preview` actions with the common flags.
Neither read action requires the disposable write marker; they still validate the
owned-root target layout/JSON and assert no file/index changes. The native CLI itself
is trusted developer filesystem access, not an ownership authorizer:

```sh
python3 examples/overlay-recovery/fixture.py preview --root "$R" --fixture "$F" --host "$R/host" --php "$PHP"
```

## Apply, inspect, then restore

Use the marker-checked entrypoint for writes, rather than calling native apply on an
arbitrary path:

```sh
python3 examples/overlay-recovery/fixture.py apply --root "$R" --fixture "$F" --host "$R/host" --php "$PHP"
XDEBUG_MODE=off "$PHP" artisan surgeon:overlay list --base="$F" --json
```

The entrypoint rechecks the marker, ownership identities, original bytes/modes,
lock, kept package, Git HEAD/index and the missing target immediately before native
apply. An unmarked site, changed lock, recreated checkout, invalid JSON or symlink
refuses. Stop and inspect a refused fixture; do not silently retry with new intent.
These are example safeguards, not Surgeon's native CAS or authorization guarantees.
The fixture must remain exclusively owned: check-then-write is not atomic against
another editor.

Apply removes only the stale repository. The kept repository, requirements and
unrelated values remain. Inspect the real file and staged diff. Native apply stages
touched paths if Git is available, never commits, and writes
`.surgeon/overlay-manifest.json`. That sidecar records scope/actions, **not backup
bytes**. Re-audit must lose the specific broken-path finding. No `--composer` is
passed; an announced `composer update` is advice, not an executed dependency update.

```sh
python3 examples/overlay-recovery/fixture.py restore --root "$R" --fixture "$F"
XDEBUG_MODE=off "$PHP" artisan surgeon:overlay list --base="$F" --json
```

Restore requires the recorded post-apply snapshot and unchanged lock/identity, copies
back the exact private backup with its mode, and restores only the two recorded
index/sidecar paths. The original broken-path finding returns. This is an explicit
example restore, not a native rollback CLI or automatic recovery. Preserve a failed
or partially written fixture for inspection; no multi-file atomicity, concurrent
editor or failed-write guarantee is promised.

## What these foundations mean

Doctor findings and the host's advisory/gate posture are separate: a registered gate
may enforce a floor, but this reporting command does not become a CI health gate.
An absent overlay, a `.dist` template and a requirement with no path repository are
different diagnoses. Validate JSON independently; the native reader can treat invalid
JSON as an empty input. Do not substitute a broad `surgeon:audit` sweep or invent an
`--only` option for it.

See the installed owning sources under `vendor/rushing/laravel-surgeon/src/Overlay/`
(`OverlayManifest`, `OverlayAudit`, `OverlayEditor`, `OverlayMutation`) and its
`Console/OverlayCommand.php`; Doctor's `Finding`, `DoctorRegistration` and
`DoctorRunner` live under `vendor/rushing/laravel-doctor/src/`. Popcorn supplies keyed
registry discovery and registration beyond remote connectors; Graphine supplies
graph contracts/projections, not a GraphQL endpoint. This overlay removal invokes no
topology or cycle evaluator. It says nothing about the host's general dependency
health, shipping advisories, frozen generated APIs or release readiness.
