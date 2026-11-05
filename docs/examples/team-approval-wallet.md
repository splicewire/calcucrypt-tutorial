# A team approval wallet, with sandbox funding

This optional example adds an observational wallet beside Approval usage. A current team Owner can add sandbox USD funds; Owner/Admin/Member can inspect the same selected-team balance. Each lifetime committed approval costs a fixed teaching tariff of $0.25. Approval admission is unchanged: zero or negative balance does not prevent approval. This is not settlement, an invoice, a hard cap or a durable order ledger.

The ordinary starter stays off and has no Commerce dependency. No Marketplace, Lunar fork, BeamCommerce, Registry, Stripe/Cashier or BeamTenancy adoption is needed. Use a fresh isolated host from this source, SQLite database `database/wallet.sqlite`, local accounts and outputs. Do not run this tutorial against owner data.

## Explicit dependency and bootstrap context

From the FIRST Composer command, export both variables inside your owned run root, verify real containment and refuse symlinks/owner homes. Create fresh private direct children; do not copy auth/config from any owner context:

```sh
export RUN_ROOT=/your/owned/run
export COMPOSER_HOME="$RUN_ROOT/composer-home"
export COMPOSER_CACHE_DIR="$RUN_ROOT/composer-cache"
export XDEBUG_MODE=off
```

Before creating them, assert the canonical RUN_ROOT exists and each path's canonical parent is RUN_ROOT, neither path is a symlink, and no existing foreign path is overwritten. Create them mode0700. `XDEBUG_MODE=off herd php /path/to/composer config --global home` and `... config --global cache-dir` must resolve inside those paths. All later install/audit/hook/producer invocations inherit them. Earlier tutorial proof bootstraps used defaults; that isolation incident remains recorded and must not be repeated.

Check out exact `rushing/laravel-commerce` aef4bfbdce492bbcc91c3027a46bdeb1042a4a77 in a clean named local VCS branch. Prepare without changing the four shipping manifests/locks:

```sh
python3 examples/team-wallet/prepare-composer.py --commerce-source /your/owned/commerce
export COMPOSER=composer.wallet.json
XDEBUG_MODE=off herd php /path/to/composer update rushing/laravel-commerce league/commonmark symfony/http-foundation --dry-run --no-scripts --no-interaction
```

Selected delta against shipping225 is one Commerce addition and two advisory patches: CommonMark2.10.0→2.10.4 and HttpFoundation8.1.6→8.1.9. All other223 full records stay equal. Verify the exact returned lock, then use the same constrained update with `--no-install` to write it. Install with `--no-scripts`; join installed records and physical Commerce source to that lock, then run installed `composer audit --format=json` BEFORE discovery or boot. No audit-disable, broad update, vendor edit or extra optional composition. Local source URL remaps are transport changes and must be recorded separately from full-record comparisons.

## Native preparation

Create a fresh local env with a new APP_KEY, loopback APP_URL, own SQLite `database/wallet.sqlite`, file sessions, sync queue, array mail and Scout collection. No Stripe keys. Set:

```dotenv
SERVICE_DESK_WALLET_ENABLED=true
SERVICE_DESK_WALLET_SANDBOX=true
SERVICE_DESK_MARKETPLACE_ENABLED=false
BEAM_TENANCY_SEED_DEMO_TENANT=false
COMMERCE_DRIVER=fake
COMMERCE_CURRENCY=USD
COMMERCE_CREDIT_UNIT=usd
```

With the explicit Composer context still set, run native package discovery, `popcorn:registries:cache`, and `splicewire:beam:install --no-seed --no-interaction`. Check actual rc and emitted errors; success of downstream commands cannot repair an earlier failed install. The lean composition supplies its native workflow prerequisites; confirm definitions/bindings/awaitings/facts/receipts/reactions before `db:seed --class=ServiceRequestApprovalSeeder`. Do not substitute tenant-wide migrations or fake tables.

`vendor:publish --tag=commerce-migrations` emits five owning shared stubs. Select ONLY the credit-entry migration for this example. Preserve the other four generated stub files with hashes outside migration discovery before later migrate/test runs; they are not selected schemas. Run native `migrate --path=database/migrations/shared/<actual-timestamp>_create_commerce_credit_entries_table.php` and verify ledger/schema. No parallel debit table. Inspect actual CreditEntry, fact and request connection identity; this proof is one SQLite connection, not tenant isolation or a split-connection transaction.

`examples/team-wallet/actors.php <host-private-output>` refuses missing workflow/credit prerequisites, nonlocal/non-SQLite/nonloopback contexts, existing output and nonempty actors. It creates six private tutorial actors through real TeamProvisioner: Owner, Admin, Member, other Owner, teamless and a nonmember central Root. Output mode0600 includes credentials/tokens; never print or transfer it into safe proof. The bounded `switch-a`, `switch-b`, `remove-member`, `credits-off/on` and `facts-off/on` modes are fixture controls, not application APIs or user authority bypasses.

## Declare, generate and use

The declaration is outside unconditional app/Data discovery and is registered only when the opt-in flag AND Commerce are present. The readonly CollectionBacking resource exposes selected-team figures through stock Frame. `FundTeamApprovalWallet` is a NoSubject collection write with one declared `amount_usd` from1 to10000 with at most two decimal places, separate fresh Owner authority and an explicit entitlement-plane ability. Its literal route precedes wildcard record/console routes. No generic credit CRUD, posted party/team/actor/unit/driver/tariff or global UsageMeter/BillingPartySource rebinding.

Run the owning `splicewire:beam:generate:assets --json` and declared host build with an isolated validated JS toolchain. Generate contracts, schemas and client from the actual registered resource/op; do not hand-author generated files. The accepted optional proof composition uses exact Beam JSf5a, Schem5ac and resourcesf3 with their recorded generated peer closure. That composition is distinct from claiming the older locked JS package set compiled; preserve failed preparation receipts and shipping locks. Run `schemas:generate --path=app/Wallet/Data` for the three optional schema files as well; these declarations intentionally live outside unconditional app/Data discovery. The reusable input source is examples/team-wallet/prepare-composer.py, not a marketplace alternate lock. `examples/team-wallet/client.ts` consumes generated input/output types and the generated route: its explicit amountUsd→amount_usd mapping follows MapInputName. It performs no automatic payment retry.

Log in as Owner, open Service desk → Approval wallet, see true zero. Add sandbox funds $2 through the generated action form: pending submit is disabled. It creates a real CreditTopup Order, uses CONFIGURED MoneyIn (no driver override) and the registered PurchaseCompleted→FundWalletFromCreditTopup listener writes one CreditEntry. The real reason is `topup:<provider-reference-or-orderId>`; this listener leaves purchase_id null. The result includes a safe reference and refetched wallet. It is fake-rail capture, not real settlement.

Create a request through the existing resource and approve it normally: count1, usage$0.25, balance$1.75. Repeat approval409 and rollback/refusal add no usage. A successful unfunded approval yields negative$0.25; policy did not change. Other team starts0; an A/B/A current selection reads fresh state. Admin/Member read without the fund affordance; teamless, removed membership and nonmember Root cannot fund.

The configured fake rail's known decline amount$666.02 returns402 without PurchaseCompleted/credit. A distinct explicit attempt can recover. Sequential redelivery of the SAME captured Purchase/reference adds no second credit through topUpOnce. A separately submitted purchase has a new reference and can add funds again. There is no unique DB reason constraint or concurrent exactly-once promise.

An error/timeout or listener write failure can happen AFTER capture; do not auto-retry or infer payment rollback. Close the form, read wallet state, and decide explicitly whether to make a NEW purchase. Credit/fact/configuration errors stay errors with Retry; they never become successful zero. No external payment reconciliation, refunds, automatic retries or reservation mechanism is implemented.

## Scope and checks

The exact shared CommittedApprovals query feeds both old Approval usage and the explicit wallet UsageMeter: retained requests in the current selected team, subject_type service-request, transition approve, from exactly[pending], to exactly[approved], one persisted fact per count. Attempt counts, markings and display events do not count. Current retained request team attribution and the fixed teaching price revalue history: transfer/deletion, historical tariff versions, tax, FX, periods and accounting guarantees are not promised. Reads write no debit, CostEvent or business fact.

Run the affected tests in an isolated in-memory test DB with the exact native published credit migration available:

```sh
XDEBUG_MODE=off herd php -d memory_limit=512M vendor/bin/pest tests/Feature/TeamApprovalWalletTest.php tests/Feature/TeamApprovalWalletOffTest.php tests/Feature/ApprovalUsageTest.php tests/Feature/ServiceRequestApprovalTest.php
```

The wallet suite refuses a served DB before tests reset it. Retain native migration paths/ledger, installed audit, source/archive/generated/served hashes, actual browser roles/keyboard/Light/Dark/mobile, failure/recovery and private process teardown separately. A build is not a UI or funding proof. Shipping locks, approval policy and marketplace defaults remain unchanged. No live adoption, real accounts/payments, invoice/settlement or complete DESIGN acceptance is implied.

## Optional-composition static analysis

`phpstan.optional.php` omits only named adapters when their owning package file is absent. Core ServiceRequest policies, CommittedApprovals, ApprovalUsage and team-authority code remain checked. The dependency-free ApprovalWalletMeter is fully analysed even without Commerce. A tiny optional CommerceUsageMeter implements the real UsageMeter contract and delegates to that core; only this bridge and the actual Commerce adapters are excluded when Commerce is absent. Installing Commerce automatically removes those wallet exclusions; Marketplace and SLA adapters are likewise restored when their own packages are present. No namespace-wide ignore or global unmatched-error suppression. Check the actual optional composition at level7 as well as shipping225; absence is a skip boundary, not proof of optional behavior.

The exact shipping225 baseline retains three known advisories (CommonMark2.10.0 and HttpFoundation8.1.6). A lead-selected isolated compatibility-only composer test/types control may record those nonzero audit results; it is not the audit-clean public wallet composition, a shipping-security pass or permission to run a server with that baseline. The wallet226 public fixture uses the two selected security patches and requires installed audit0 before boot. Never disable audit or edit shipping locks for these controls.
