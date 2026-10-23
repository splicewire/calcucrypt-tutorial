# Buy a scoped SLA license in the local sandbox

This opt-in example adds paid purchase and revocation to [the local marketplace](local-marketplace-sla.md). It uses the real Market purchase operation, commerce fake rail and LicenseIssuer, with a local payout gateway. The page says **“Local payment and payout sandbox — no charge.”** There is no Stripe account, external payment call, private registry delivery or refund. Keep it in a fresh private local host. Both marketplace switches default off; production cannot use the sandbox.

## Selected isolated inputs

Keep shipping `composer.json`, `composer.lock`, `package.json` and `pnpm-lock.yaml` unchanged. First prepare the previous example's accepted **254-package alternate context**, including its disclosed proof-only Lunar patch. Do not install that composition into a live site. Then use clean named source branches:

| Source | Exact ref |
| --- | --- |
| Market authoritative entitlement repair | df2c1710e1fb77a6c83880c02db7af813ae28b85 |
| optional BeamCommerce | 42c8d495630d4d3f3b174a57f5e9abb836350a09 |
| optional BeamTenancy | 4b527177171f2732699a9470abcdb376b3f93896 |
| Licenser | 8336a345f20d7f8338fb1c042d08e71b3c67baa3 |
| commerce | aef4bfbdce492bbcc91c3027a46bdeb1042a4a77 |
| proof-only patched Lunar | 1adeac5a3f404452cabf85095fc839afd713f6d8 |
| SLA artifact, tagged v0.1.1 in an owned standalone clone | 271bb0f5927fc1e2b8bc0f8a772a45579ea57b60 |

```sh
python3 examples/local-marketplace/prepare-paid-composer.py \
  --base-manifest composer.market.json --base-lock composer.market.lock \
  --market-source "$MARKET_SOURCE" \
  --beam-commerce-source "$BEAM_COMMERCE_SOURCE" --tenancy-source "$TENANCY_SOURCE"
COMPOSER=composer.paid.json XDEBUG_MODE=off herd composer update \
  splicewire/laravel-beam-market splicewire/laravel-beam-commerce \
  splicewire/laravel-beam-tenancy rushing/laravel-postgres-rls \
  --minimal-changes --no-scripts --dry-run --no-interaction
```

The optional purchase closure adds seven packages: BeamCommerce, BeamTenancy, RLS74567d0edab0f7bf7ace4bd598af3861d41cfa59, Cashier16.9.0, Money4.9.0, Stripe PHP17.6.0 and polyfill-intl-icu1.43.0. The Market repair is an explicitly selected source replacement; the other253 accepted records must stay unchanged. The measured original dependency probe used Market09beb and added7/changed0/removed0; repairing Market subsequently changes that one source record. Inspect the actual solver output before removing `--dry-run`. Install with `--no-scripts`, compare installed metadata to the exact alternate lock, and run an **installed** `composer audit --format=json` before discovery or boot. Do not disable audit or use a broad update to resolve another prerequisite.

The Lunar patch is a disposable compatibility prerequisite, not an upstream release or a selected live maintenance strategy. BeamTenancy is an optional purchase prerequisite here; this single SQLite host does not prove tenant isolation. Cashier/Stripe are installed code only: do not supply Stripe keys or contact `api.stripe.com`.

## Native fresh preparation

Use the previous guide's private SQLite/session/artifact/toolchain setup, with a new application key, `database/service-desk.sqlite`, and these additional local-only flags:

```dotenv
SERVICE_DESK_MARKETPLACE_ENABLED=true
SERVICE_DESK_MARKETPLACE_PAYMENT_SANDBOX=true
BEAM_TENANCY_SEED_DEMO_TENANT=false
```

Keep `BEAM_LICENSER_ENABLED=false`; real listing/buyer licensing still uses the issuer. Configure the exact owned SLA repository/tag through the existing LocalGit allowlist. After installed audit passes:

```sh
COMPOSER=composer.paid.json XDEBUG_MODE=off herd php artisan package:discover
COMPOSER=composer.paid.json XDEBUG_MODE=off herd php artisan popcorn:registries:cache
COMPOSER=composer.paid.json XDEBUG_MODE=off herd php artisan splicewire:beam:install --no-interaction --no-seed
COMPOSER=composer.paid.json XDEBUG_MODE=off herd php artisan migrate --path=examples/local-marketplace/migrations
```

The optional tenant workflow files are not automatically run on this central SQLite connection. Run **only these six committed paths**, in order, before the approval seeder or actor helper:

```sh
COMPOSER=composer.paid.json XDEBUG_MODE=off herd php artisan migrate \
  --path=database/migrations/tenant/2026_09_11_220453_create_workflow_definition_tables.php \
  --path=database/migrations/tenant/2026_09_11_220454_create_workflow_bindings_table.php \
  --path=database/migrations/tenant/2026_09_11_220455_create_workflow_awaitings_table.php \
  --path=database/migrations/tenant/2026_09_14_151527_create_workflow_transition_facts_table.php \
  --path=database/migrations/tenant/2026_09_14_151528_create_workflow_action_receipts_table.php \
  --path=database/migrations/tenant/2026_09_14_151529_create_workflow_reactions_tables.php
COMPOSER=composer.paid.json XDEBUG_MODE=off herd php artisan db:seed --class='Database\Seeders\ServiceRequestApprovalSeeder'
COMPOSER=composer.paid.json XDEBUG_MODE=off herd php scripts/paid-marketplace-fixture.php
node scripts/prepare-local-marketplace.mjs
COMPOSER=composer.paid.json XDEBUG_MODE=off herd php artisan splicewire:beam:generate:assets --json
pnpm run build
```

The helper refuses a nonempty actor/team database and missing workflow prerequisites. Its private0600 JSON contains only this fixture's credentials; never publish it. A prior failed preparation must be preserved and a new empty owned database prepared, rather than resetting existing business data. The inherited optional userables migration is the exact Accounts tenant stub described in the previous guide, not the broader auth estate.

Use the selected built Beam Market candidate (including entitlement safety and full keyboard fixes), Frame5ac and resourcesf3 with the exact isolated peer closure. The committed host JS lock alone is not the tested optional Market composition. Prepare generated Workflow/DTO inputs through their owning recipe; link only into the owned copy. This example does not prescribe a package-manager link against shared sources. Record installed source, resolved exports, build outputs and served bytes separately.

## Creator, buyers and revoke

1. Log in as Creator at `/local-marketplace`. Associate the allowlisted local repository through the explicitly simulated provider callback. Create “Paid service desk SLA” and “Declined service desk SLA” drafts. Before variants exist, run the fixture-only native Lunar price command with the actual draft IDs:

   ```sh
   COMPOSER=composer.paid.json XDEBUG_MODE=off herd php scripts/paid-marketplace-price.php "$PAID_ID" 1900
   COMPOSER=composer.paid.json XDEBUG_MODE=off herd php scripts/paid-marketplace-price.php "$DECLINE_ID" 66602
   ```

   Release exact v0.1.1, submit each, and approve through a separate Root review session. The native onboarding invokes the labelled local payout gateway. No seller price editor or provider onboarding is claimed.
2. Log in as Buyer, not Root. Buy $19.00. “Purchased” shows the real payment reference and license UID. The key is hidden until “Show key.” Afterward it is available on the Installed tab. A sequential repeat returns the same grant/payment without another capture/license. The $666.02 listing deliberately returns402 with “Payment not completed. Nothing was purchased.” and creates no grant.
3. A second buyer in the same team buys independently. For the same buyer's second membership, `scripts/paid-marketplace-scope.php alternate` uses existing `User::switchTeam` and verifies the persisted selection. Return with `consumer`. This is **fixture context selection**, not a claimed public team selector. Both buyer and selected team must match a grant. Purchase permission is the explicit team-scoped `marketplace.buyer`; membership is freshly rechecked. Buyer installation additionally requires the actual selected paid listing and its effective buyer/team license. Free listing installation remains an operator action. UI install admission receives that listing as an optional second `can` argument and refuses unknown/free/unentitled buyer subjects; existing one-argument operator adapters remain compatible.
4. The entitled Install action records a request while code remains absent. In the owned terminal add the exact SLA VCS repository and require0.1.1 to the alternate manifest, dry-run and install only `splicewire/service-desk-sla`, then use native `--no-seed`, assets generation and host build. All261 prior full lock records must stay unchanged; compare all22 installed SLA files to the selected archive. The browser detects “Active v0.1.1.” This proves local installation, not license-gated registry download.
5. As the consumer team's Admin save one enabled15-minute rule at `/service-request-sla`, create a request and approve it through the existing confirmation. Inspect the real immutable fact, usage quantity1 and one SLA evaluation. Observer evaluation is best effort after committed approval; it cannot roll approval back.
6. As separate Root, open **Buyer licenses**, select the exact buyer/team/listing and confirm **Revoke buyer license**. The real issuer cancels that license. Buyer refetch/reopen/reload shows revoked/acquisition unavailable, without a credential or registry instructions, while retaining payment/license history. Another buyer and the same buyer's other team remain entitled. Repeated revoke returns `alreadyRevoked`; it does not charge, refund, uninstall code or delete requests/rules/evaluations.

Authoritative admission independently refuses cancelled, missing, wrong-product or expired licenses even if a stale entitlement row says active. New acquisition after revoke is403. A revealed cached credential disappears when the authoritative read denies it; a transport error is not a successful entitlement read. A new purchase is an explicit separate action, never an automatic retry charge.

## Focused checks and limits

Run `PaidMarketplaceEntitlementTest`, `LocalMarketplaceAuthorityTest`, `LocalMarketplaceRoutesTest`, `LocalMarketplaceDependenciesTest`, `ServiceRequestApprovalTest`, `ApprovalUsageTest` and `ExternalRequestTest` against the alternate context. They assert `:memory:`. For existing `migrate:fresh` tests, stage only byte-identical copies of the six named workflow files in the owned test migration root, recording exact source/destination/hash and removing only those unchanged copies afterward. Never point resets at the served proof database. Default-off tests need `SERVICE_DESK_MARKETPLACE_ENABLED=false`; the relevant tests explicitly mount the selected API contract.

Focused controls cover real issuer revocation/missed-event stale rows, missing/wrong-product/expired license, duplicate capture, decline, unresolved process-local step-up, production fake refusal, team permission/membership, native Activitylog/Scout behavior, rollback and service HTTP failures. Public proof separately covers real actors, creator/review, buyer/scope, Root revoke, the installed SLA effect, light/dark/mobile and complete Enter/Space focus behavior. No full-host, concurrency exactly-once, real-money settlement, SCA browser, refund, federation or private registry claim is made. The separate registry shared-cache revocation gap remains outside this example.
