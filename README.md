# Calcucrypt tutorial

Calcucrypt is a site of crypto calculators. This repository rebuilds four of them on the
[Beam starter](https://github.com/splicewire/laravel-beam-starter), one chapter at a time:
declare the data, validate the one operation, give teams a plan, lock what a plan doesn't cover,
then sell the plan.

Each chapter is one commit with a tag, so you can read a chapter as a diff or check it out and run
it.

| tag | chapter |
|---|---|
| `start` | The starter, with Calcucrypt's calculator engine and a hand-rolled controller |
| `ch-02` | Declare the `calculators` and `guides` resources |
| `ch-03` | `resolve` as a declared, validated operation |
| `ch-04` | Team plans and an entitlement resolver |
| `ch-05` | Gate the data: locked calculators ship no graph |
| `ch-06` | Sell a plan on the fake payment rail |

```bash
git diff start ch-02          # what chapter 2 changes
git checkout ch-03            # the app as chapter 3 leaves it
```

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

Then open `/calculators`. `composer setup` installs both lockfiles, writes `.env`, creates the
sqlite database and builds the frontend. Everything it fetches is public.

From chapter 5 on, the pages call `/api` with your session (Sanctum's stateful API). Sanctum
trusts `localhost` and `127.0.0.1:8000`; if you serve the app anywhere else, set `APP_URL` to that
origin, or a signed-in reader's widget is treated as a guest.

## No API keys

The calculators price assets from live market data in Calcucrypt. Here the default connector is
`FixtureConnector` (`FACTS_DRIVER=fixture`): fixed prices at one fixed instant, so every machine
sees the same numbers, offline. To use CoinMarketCap instead, set `FACTS_DRIVER=live` and
`CMC_API_KEY`.

## The four calculators

| calculator | plan from chapter 4 |
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

`scripts/parity-check` compares this app at `ch-06` with Calcucrypt itself for the four calculators.
It needs a Calcucrypt checkout, so it is for maintainers.

## Licence

MIT. See [LICENSE](LICENSE).
