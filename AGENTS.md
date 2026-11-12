> You are in **splicewire/calcucrypt-tutorial**: Calcucrypt's calculators, built on
> `splicewire/laravel-beam-starter` one chapter at a time. This repository is the tutorial's code;
> each chapter is one commit and one tag.

## Layout of the history

This revision reconstructs `start` directly on starter commit
`b97ee33bf56799df2afe1c49263b19bebf826515`, preserving the starter's real ancestry. Integration
checkpoints are preparation for that ladder; move chapter refs together after its proof and delivery
coordination.

| tag | what the commit does |
|---|---|
| `start` | The current starter's packaged accounts, realms and codegen; the supplied PHP/TypeScript calculator engine, four calculators and keyless `FixtureConnector`; two Guide sources with supplied UX import/publication and calculator-association adapters. The product read resources and pages follow in ch-01/02. |
| `ch-01` | Render one calculator: `make:particle-resource --read-only --public` declares `calculators`; a bare mount takes `index` and `show` from the declaration; the tool page renders its projection. No policy: a signed-in reader reads through the Public Read. |
| `ch-02` | Add a field and break it on purpose (`generate:assets`, `undeclared-surface --check`), then declare `guides` and render published UX bodies with calculator links/embeds. The supplied importer accepts explicit scalar metadata and `calculators_json`; UX owns publication. The temporary field and hand-written route are not kept. |
| `ch-03` | `make:particle-op` mints `calculators.resolve`; its `rules()` sees the calculator, its handler gets the validated input; the API carries the session; the widget calls the generated client. |
| `ch-04` | Declare `saved-inputs` over package-owned BeamParticle storage with explicit schema identity and actor-owned read/write policy. The normal mount supplies five verbs; verify two actors through both transports. The fresh app needs no SavedInput model, table or SQL migration; calculator association logic stays application-owned. |
| `ch-05` | Lock first: paid calculators carry a declared entitlement key that no plan holds yet, so they are locked for everyone; a locked calculator ships no graph and no resolution, and `resolve` answers 403. |
| `ch-06` | Plans and entitlements: the bundles are declared, and the current team's plan (a `PlanSource`) unlocks them through beam-accounts' resolver. |
| `ch-07` | Sell a plan on the commerce fake rail. |

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
- `scripts/parity-check <calcucrypt-checkout>` (at `ch-07`) compares this app's behaviour with
  Calcucrypt for the four calculators. It needs a Calcucrypt checkout, so it is a maintainer tool;
  readers never need it.

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.
