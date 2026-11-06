> You are in **splicewire/calcucrypt-tutorial**: Calcucrypt's calculators, built on
> `splicewire/laravel-beam-starter` one chapter at a time. This repository is the tutorial's code;
> each chapter is one commit and one tag.

## Layout of the history

`start`'s parent is the starter's own commit (`2f4742e`), so this repo is a real child of the
starter: `git fetch upstream && git merge upstream/main` brings starter fixes down, as for any
starter-based app.

| tag | what the commit does |
|---|---|
| `start` | The starter, plus the calculator engine (PHP and TypeScript, supplied as is), four calculators, two Guides and a keyless `FixtureConnector`. No routes or pages for them yet. |
| `ch-01` | Declare the `calculators` and `guides` particle resources with `make:particle-resource`; mount their API; the pages render their projections. |
| `ch-02` | `make:particle-op` mints `calculators.resolve`; the API carries the session; the widget calls the generated client. |
| `ch-03` | Plans and entitlements: the bundles are declared, paid calculators carry a key, a host resolver grants the current team's plan. |
| `ch-04` | Gate the data: a locked calculator ships no graph and no resolution, and `resolve` answers 403. |
| `ch-05` | Sell a plan on the commerce fake rail. |

**A chapter is a commit, so history is rewritten only by re-tagging.** Change a chapter by amending
its commit and rebasing the later chapters onto it, then move every later tag. Never add a fix-up
commit between two chapter tags: the tutorial's code excerpts are read against `git show ch-NN`
by line range.

**Generated files are generated.** `resources/js/generated/`, `resources/schemas/` and
`.beam/undeclared-surface.json` come from `php artisan splicewire:beam:generate:assets` and
`php artisan splicewire:beam:undeclared-surface`. Never edit them by hand.

## Rules

- No private package and no API key. `composer.lock` and `pnpm-lock.yaml` resolve from public
  sources only, and the default `FACTS_DRIVER=fixture` prices every calculator offline.
- Run PHP through your PHP 8.4 (`composer test`). The suite runs on sqlite `:memory:`.
- `scripts/parity-check <calcucrypt-checkout>` (at `ch-05`) compares this app's behaviour with
  Calcucrypt for the four calculators. It needs a Calcucrypt checkout, so it is a maintainer tool;
  readers never need it.

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.
