# Calcucrypt tutorial

Calcucrypt is a site of crypto calculators. This repository builds four of them on the
[Beam starter](https://github.com/splicewire/laravel-beam-starter), one chapter at a time:
render one calculator, add the Guides yourself, add the one operation, make a small write, lock the
paid calculators, give teams a plan that unlocks them, then sell the plan.

The calculator engine (the expression evaluator, the graph, the market-data connectors and the
widget that renders a calculator) is supplied at `start`. The tutorial is about everything Beam
does around it.

Each chapter is one commit with a tag, so you can read a chapter as a diff or check it out and run
it.

| tag | chapter |
|---|---|
| `start` | Current starter accounts/realms/codegen, the supplied calculator engine, four calculators and two Guides on UX publication |
| `ch-01` | Render one calculator: declare the `calculators` resource and the page that renders it |
| `ch-02` | Add a field and break it, then declare Guide reads and render UX-published bodies with calculator links |
| `ch-03` | The `resolve` operation, called through the generated client |
| `ch-04` | Declare Saved Inputs over shared particle storage with explicit owner/schema isolation; no dedicated model or table |
| `ch-05` | Lock first: a paid calculator ships no graph until a plan holds its key |
| `ch-06` | Plans and entitlements: teams carry a plan, plans grant keys |
| `ch-07` | Sell a plan on the fake payment rail |

```bash
git diff start ch-01          # what chapter 1 changes
git checkout ch-02            # the app as chapter 2 leaves it
```

This revision's reconstruction base is starter commit
`b97ee33bf56799df2afe1c49263b19bebf826515`. The reconstructed `start` is cut directly from it,
so `git diff b97ee33 start` shows the supplied tutorial foundation. Chapter refs move together
after the complete ladder is verified.

The foundation includes Guide sources and their UX import/publication adapter. Chapter 2 adds the
read boundary and pages; UX governs draft/published visibility, while the application keeps calculator
associations in explicit `calculators_json` metadata. Chapter 4 declares actor-owned Saved Inputs on
Beam's shared particle store. A dedicated model/table is an explicit choice for requirements that
need relational storage; it is not required for this example. Existing app data has a separate,
opt-in conversion procedure in [the legacy Saved Inputs guide](docs/legacy-saved-inputs.md).

## Run it

You need PHP 8.4, Composer, Node 22 and pnpm (the version is pinned in `package.json`, so
`corepack enable` picks it up).

```bash
git clone https://github.com/splicewire/calcucrypt-tutorial.git
cd calcucrypt-tutorial
git checkout start
composer setup
php artisan migrate --seed
composer dev
```

`composer setup` installs both lockfiles, writes `.env`, creates the sqlite database and builds the
frontend. Everything it fetches is public. `package.json` overrides one transitive JavaScript
dependency, `@splicewire/_resources`, to nothing: it holds TypeScript declarations only, nothing in
this app reads them, and it is not public.

From `ch-01` on, open `/calculators`. Set `APP_URL` to the address you serve the app on: from
`ch-03`, the calculator pages call the API with the session cookie, and Sanctum only treats the
app's own origin as stateful.

## No API keys

The calculators price assets from live market data. Here the default connector is
`FixtureConnector` (`FACTS_DRIVER=fixture`): fixed prices at one fixed instant, so every machine
sees the same numbers, offline. To use CoinMarketCap instead, set `FACTS_DRIVER=live` and
`CMC_API_KEY`.

## The four calculators

| calculator | plan from chapter 6 |
|---|---|
| `crypto-profit` | free |
| `crypto-converter` | free |
| `crypto-position-size` | Trader |
| `crypto-capital-gains` | Pro |

Two Guides embed them: *How crypto profit really works* and *Crypto taxes, end to end*.

## Tests

```bash
composer test    # Pest, on sqlite :memory:
pnpm test        # Vitest: the TypeScript half of the calculator engine
pnpm types:check # generated contracts and negative TypeScript fixtures
```

Run the suites and type check at each tag with no keys. `tests/fixtures/expression-golden.json`
holds the golden vectors that keep the PHP and TypeScript evaluators in step.

`scripts/parity-check` compares this app at `ch-07` with Calcucrypt, the production app it follows,
for the four calculators. It needs a Calcucrypt checkout, so it is for maintainers.

## Licence

MIT. See [LICENSE](LICENSE).

## Registries

- [Calculator definitions](docs/registries/calculators.mdx) — the application-owned authored catalog.
