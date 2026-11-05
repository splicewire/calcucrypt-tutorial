> You are in **splicewire/calcucrypt-tutorial**: Calcucrypt's calculators, rebuilt on
> `splicewire/laravel-beam-starter` one chapter at a time. This repository is the tutorial's code;
> each chapter is one commit and one tag.

## Layout of the history

| tag | what the commit does |
|---|---|
| `start` | The starter, plus Calcucrypt's calculator engine (PHP and TypeScript, supplied as is), four calculators, two Guides, a keyless `FixtureConnector`, and the hand-rolled controller the tutorial replaces. |
| `ch-02` | Declare the `calculators` and `guides` particle resources; the pages render their projections. |
| `ch-03` | `resolve` becomes the declared, validated `calculators.resolve` operation; the widget calls the generated client. |
| `ch-04` | Team plans: paid calculators carry an entitlement key, and a host resolver grants the current team's plan. |
| `ch-05` | Gate the data: a locked calculator ships no graph and no resolution, and `resolve` answers 403. |
| `ch-06` | Sell a plan on the commerce fake rail. |

**A chapter is a commit, so history is rewritten only by re-tagging.** Change a chapter by amending
its commit and rebasing the later chapters onto it, then move every later tag. Never add a fix-up
commit between two chapter tags: the tutorial's code blocks are read against `git show ch-NN`.

## Rules

- No private package and no API key. `composer.lock` and `pnpm-lock.yaml` resolve from public
  sources only, and the default `FACTS_DRIVER=fixture` prices every calculator offline.
- Run PHP through your PHP 8.4 (`composer test`). The suite runs on sqlite `:memory:`.
- `scripts/parity-check` compares this app at `ch-06` with Calcucrypt for the four calculators. It
  needs a Calcucrypt checkout, so it is a maintainer tool; readers never need it.

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.
