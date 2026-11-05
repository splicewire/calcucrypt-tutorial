> You are in **splicewire/laravel-beam-starter** — the Laravel + React starter kit for scaffolding new beam-tier applications.

A Laravel starter kit (Inertia + React 19 + TypeScript + Tailwind + shadcn/ui) that scaffolds new
applications on the `splicewire/laravel-beam` stack, installed via the beam-tier installer
(`composer setup` → `splicewire:beam:install`).

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.

## Starter lineage — you are the head of the chain

```
laravel-beam-starter  →  laravel-satellite-starter  →  laravel-tower-starter
```

This is real git ancestry, not a convention: each child's history sits on top of its parent's, so a
child propagates with `git merge upstream/main`. It was not always so — the children were originally
squashed scaffolds whose fork lineage existed only in their first commit message. It was restored by
replaying each child onto its parent (beam-docs-satellite ticket 12).

**Propagation is strictly downstream. Nothing merges upward.** A fix that applies beyond its own
tier lands *here* first and rides down. Authoring it on satellite or tower strands it: this repo can
never merge it back, and the next catch-up merge will fight it.

Downstream repos each carry `upstream` pointing at their parent, and run:

```bash
git fetch upstream && git merge upstream/main
```

A catch-up merge **takes everything**, including files for packages that tier doesn't require —
packages gate behaviour, so an inert file is not a wrong file. Rejecting content to keep a tier
"clean" is what makes every later merge re-propose the same files. The genuine tier deltas are each
repo's `composer.json`, `composer.local.json.dist`, `AGENTS.md`, `README.md`, and its own tier code.
