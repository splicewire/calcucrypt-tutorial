# One request, one approval, one trail

This local preview follows **one request on one fresh host**. A Member creates it; a different
same-team Owner approves it once. Usage then counts its committed approval and Inspection shows
that same fact and historical definition. It uses the stock Frame form, detail and declared
Approve action. There is no edit/delete promise and no replacement dashboard.

The [input manifest](../../examples/service-desk-preview-inputs.json) identifies the current
preview sources. Obtain the matching supplied source/input packet before starting. These are
unpublished local checkpoints, not a registry release or an install from remote main. The packet
includes the measured alternate Composer pair, three local VCS transport mappings, exact JS
source/tool inputs and the ordered fresh-build recipe. Do not substitute a broader update or
copy an old vendor, environment, database, actor file, cache or compiled output.

## Prepare one empty local host

Use a registered disposable root, Herd PHP 8.4, Node 22 and pnpm 9.15.3. Keep all files, Composer
homes, caches, package stores and browser state under that root. `HOST` is its new host checkout;
`INPUTS` is the supplied credential-free preview packet; `PHP` is your explicit Herd executable.
Never point these commands at an owner site. Copy the packet's measured alternate pair into the
host as `composer.example.json` and `composer.example.lock`, remapping only its three local
source/repository URLs to your own exact-ref clones. The packet's full-record check must pass.
The host's committed Composer and pnpm manifests/locks stay unchanged.

The supplied packet contains a **complete offline Git bundle** of reviewed ecosystem tool
b4b2825ef0d3e938806d1e2a263589fc37753f44, not a promise that this commit can be fetched from
remote main. Before using it, verify every packet file against the committed input manifest:

```bash
python3 - "$INPUTS" "$HOST/examples/service-desk-preview-inputs.json" <<'CHECK'
from pathlib import Path
import hashlib, json, sys
root = Path(sys.argv[1]).resolve()
for name, expected in json.loads(Path(sys.argv[2]).read_text())['packetFiles'].items():
    file = (root / name).resolve()
    assert file.is_relative_to(root) and file.is_file(), name
    assert hashlib.sha256(file.read_bytes()).hexdigest() == expected['sha256'], name
CHECK
git clone --no-checkout "$INPUTS/ecosystem-b4.bundle" "$RUN_ROOT/eco"
git -C "$RUN_ROOT/eco" checkout --detach b4b2825ef0d3e938806d1e2a263589fc37753f44
test "$(git -C "$RUN_ROOT/eco" rev-parse HEAD)" = b4b2825ef0d3e938806d1e2a263589fc37753f44
```

The immutable `RECIPE.md` and `recipe-commands.json` are hash-bound by that manifest. Verify the
original Composer pair **before** the three documented local transport remaps; record those URL
changes separately and prove all 225 complete package records otherwise identical. No version,
reference, dist or additional field change is a transport remap.

Use the tool for **every** Composer command, beginning with configuration/install. Run in `$HOST`:

```bash
export COMPOSER=composer.example.json
export PHP_BIN="$PHP"
export COMPOSER_BIN=/absolute/path/to/your/composer
node "$RUN_ROOT/eco/rigs/launch/bin/composer-leg.mjs" --root "$RUN_ROOT" -- config --global home
node "$RUN_ROOT/eco/rigs/launch/bin/composer-leg.mjs" --root "$RUN_ROOT" -- config --global cache-dir
node "$RUN_ROOT/eco/rigs/launch/bin/composer-leg.mjs" --root "$RUN_ROOT" -- install --no-scripts --prefer-dist --no-interaction
node "$RUN_ROOT/eco/rigs/launch/bin/composer-leg.mjs" --root "$RUN_ROOT" -- audit --format=json --no-interaction
```

Both effective paths must be contained in the root. Stop if the installed audit or the packet's
225 installed/physical-source comparison fails. This preview has five explicit differences from
the shipping lock: Frame, UX, Workflows and the two security patches in the manifest. It adds no
Commerce, wallet, marketplace, Lunar or tenant runtime. Shipping API/registry/publication and
security work remains separate; this is not a clean-shipping-lock claim.

Create a new `.env` from `.env.example`, a new empty `database/service-desk.sqlite`, and an owned
`storage/app/private` directory with mode 0700. Use local loopback APP_URL on an unused port,
SQLite pointing at that exact database, file sessions, array cache/mail and synchronous queue.
Keep `BEAM_CANONICAL_MDX_REVIEW_ENABLED=false`. Point `BEAM_SCRATCH_STORAGE_ROOT` inside the run
root; this tutorial is not Git recording or MDX publication. Generate a new APP_KEY. Then use
the native preparation in this order (Xdebug off throughout):

```bash
XDEBUG_MODE=off "$PHP" artisan key:generate --force
node "$RUN_ROOT/eco/rigs/launch/bin/composer-leg.mjs" --root "$RUN_ROOT" -- dump-autoload --no-scripts --no-interaction
XDEBUG_MODE=off "$PHP" artisan popcorn:registries:cache --no-interaction
XDEBUG_MODE=off "$PHP" artisan splicewire:beam:install --tenancy=single --no-seed --no-interaction
XDEBUG_MODE=off "$PHP" artisan config:clear
XDEBUG_MODE=off "$PHP" artisan db:seed --class='Database\Seeders\ServiceRequestApprovalSeeder' --no-interaction
XDEBUG_MODE=off "$PHP" artisan splicewire:beam:generate:assets --json
```

Use the packet's per-owner JS tool contexts to build the required package ESM **and** declarations
fresh. Their output manifests must be captured immediately. Install the host's frozen pnpm lock
with scripts ignored and an owned store; compose the four selected package namespaces using the
packet's checked local links, including existing isolated-store dependent links. This does not
change the shipping lock. Do not use `pnpm link` or run a hidden package preparation step.
Install this explicit contained Composer shim before the declared host build. It is the
same wrapper, not an unwrapped Composer fallback; the heredoc preserves its argument forwarding.
`NODE` is the absolute executable of the selected Node 22 installation.

```bash
export NODE=/absolute/path/to/selected/node
mkdir -p "$RUN_ROOT/bin"
cat > "$RUN_ROOT/bin/composer" <<'SHIM'
#!/bin/sh
exec "$NODE" "$RUN_ROOT/eco/rigs/launch/bin/composer-leg.mjs" --root "$RUN_ROOT" -- "$@"
SHIM
chmod 700 "$RUN_ROOT/bin/composer"
export RUN_ROOT NODE
export PATH="$RUN_ROOT/bin:$PATH"
test "$(command -v composer)" = "$RUN_ROOT/bin/composer"
pnpm run build
```

That declared build performs native type transformation, both TypeScript checks and Vite.
**Generation and the declared build must pass with review off before starting a browser.** Stop
on a concrete missing type/input; do not enable host-wide review, handwrite a generated type or
drop declarations to make the build pass.

## Prepare fresh actors, then keep their data private

```bash
XDEBUG_MODE=off "$PHP" scripts/service-desk-preview-fixture.php "$HOST/storage/app/private/service-desk-actors.json"
```

The helper refuses an occupied fixture or invalid private destination before provisioning. It
calls the existing shared-actor helper: Owner and Member (`other`) share Team A; Outsider owns
Team B; Teamless has none. Passwords/tokens stay in the private file. This teaching shortcut uses
native team/membership provisioning; it does not demonstrate invitation delivery or physical
tenant isolation. Start the host on the selected loopback port after its successful build.

## Follow the same request

1. Sign in as Member from the private fixture. Open **Service requests** in the actual navigation,
   choose Create, submit a title such as “Printer access” and status `open`. Keep the returned
   request ID **R**. Read its stock detail and reload: title/status/approval are displayed, with approval pending.
   Native readback binds the server-owned creator to this Member and team to Team A; the four-field
   stock DTO does not display ownership columns. Do not create a second request to recover an assertion failure.
2. Open the existing **Approval usage** surface as Team A: lifetime quantity is zero. Inspection
   for R says there are no committed approval transitions. Usage counts approvals, not requests.
3. In a separate ordinary session sign in as Owner. Open R, inspect its title/state, then use its
   declared **Approve** action once. Owner is a different actor in the same team. Record fact **F**,
   actual time and pinned workflow version from the resulting safe history. No second approval
   is needed after a timeout; inspect persisted state before continuing.
4. Reload R as Member/Owner, refetch Approval usage (quantity one), and open the documented per-ID Inspection URL
   `/service-requests/R/inspection` (replace R with the observed ID; there is no visible history action). The safe history identifies the same R and F, transition `approve`, pending→approved,
   actual actor/time and fact-time definition version. Reload retains them. Do not substitute a
   latest definition for missing historical metadata.

The generated contracts come from `ServiceRequestData`, its input DTO and the declared operations;
the Node [client](../../examples/service-request-client.ts) uses generated route/response types.
Its mutation input is not runtime validation. Running that client creates auxiliary control rows;
it does not replace the one main stock-form request above. The inspection page uses the generated
history hook. Its allowlist excludes raw workflow context, payloads and internal refusal logs.

## Read the boundaries honestly

Member approval refuses; repeating an approved transition conflicts without another fact. Foreign
teams see their own data and zero usage, and cannot read R's history. Removed/teamless membership
refuses rather than returning an empty success. Invalid declared title/status and forbidden history
selectors fail without business writes. The four forged ownership keys (`user_id`, `userId`,
`team_id`, `teamId`) are **ignored with 201** by the existing whitelist: actual creator/team remain
server-derived. They are not a promised unknown-key 422 contract.

For a source-store failure, the inspection page hides stale data and shows its fixed error/Retry;
restore the owned source before retrying. A real successful empty history is a different state.
Reads do not create business facts. Auxiliary rollback controls do not undo a previously committed
approval. Quantity is observational lifetime usage, not a wallet debit, billing ledger or cap.

Continue through the existing source chapters for [team invitations](service-desk-team.md),
[entitlements](service-desk-entitlements.md), [tenancy](service-desk-tenancy.md),
[operator inspection](service-desk-operator-inspection.md), [external request providers](service-desk-external-requests.md),
[wallet](team-approval-wallet.md), [sandbox marketplace](local-marketplace-sla.md),
[licensed delivery](licensed-composer-sla.md) and [MDX review](canonical-mdx-file-draft.md).
These are separate opt-in branches with their own prerequisites, not effects of this SQLite trail.
The current guide is source content; updating it does not update a stored/published owner page.
