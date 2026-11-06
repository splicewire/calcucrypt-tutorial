# Calcucrypt tutorial

Calcucrypt is a site of crypto calculators. This repository builds four of them on the
[Beam starter](https://github.com/splicewire/laravel-beam-starter), one chapter at a time:
declare the data, add the one operation, give teams a plan, lock what a plan doesn't cover, then
sell the plan.

The calculator engine (the expression evaluator, the graph, the market-data connectors and the
widget that renders a calculator) is supplied at `start`. The tutorial is about everything Beam
does around it.

Each chapter is one commit with a tag, so you can read a chapter as a diff or check it out and run
it.

| tag | chapter |
|---|---|
| `start` | The Beam starter, plus the calculator engine, four calculators and two Guides |
| `ch-01` | Declare the `calculators` and `guides` resources, and the pages that render them |
| `ch-02` | The `resolve` operation, called through the generated client |
| `ch-03` | Plans and entitlements: teams carry a plan, plans grant keys |
| `ch-04` | Gate the data: a locked calculator ships no graph |
| `ch-05` | Sell a plan on the fake payment rail |

```bash
git diff start ch-01          # what chapter 1 changes
git checkout ch-02            # the app as chapter 2 leaves it
```

`start`'s parent is the starter's own commit, so `git log start` shows the starter's history and
`git diff 2f4742e start` is exactly what the tutorial adds to it.

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
`ch-02`, the calculator pages call the API with the session cookie, and Sanctum only treats the
app's own origin as stateful.

## No API keys

The calculators price assets from live market data. Here the default connector is
`FixtureConnector` (`FACTS_DRIVER=fixture`): fixed prices at one fixed instant, so every machine
sees the same numbers, offline. To use CoinMarketCap instead, set `FACTS_DRIVER=live` and
`CMC_API_KEY`.

## The four calculators

| calculator | plan from chapter 3 |
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
```

Both pass at every tag, with no keys. `tests/fixtures/expression-golden.json` holds the golden
vectors that keep the PHP and TypeScript evaluators in step.

`scripts/parity-check` compares this app at `ch-05` with Calcucrypt, the production app it follows,
for the four calculators. It needs a Calcucrypt checkout, so it is for maintainers.

## Licence

MIT. See [LICENSE](LICENSE).
