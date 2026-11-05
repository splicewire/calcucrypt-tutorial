# Local Git-release development

`pnpm.local.json` is the JavaScript counterpart of `composer.local.json`: local
checkout paths beside the consumer, ignored by Git. Copy `pnpm.local.json.dist`,
replace the example release with the **exact spec from committed package.json**,
and edit each path. The local package must declare the configured package name.

```sh
scripts/pnpm-overlay on
pnpm run build
# Run the consumer's declared tests.
scripts/pnpm-overlay off
```

The tracked adapter generates an ignored `.pnpmfile.cjs` whose `readPackage` hook
reads this map. It rewrites matching full-SHA Git dependency specs, including
transitive and optional dependencies, to local `link:` paths. It does not rewrite
peer compatibility ranges or unrelated dependencies. It refuses a mapped package
encountered at a different spec, including an npm version. A mapped package must
also be pinned directly at the consumer root, as required by the release design.

Public specs use `github:org/repo#<40-character-sha>&path:/package`; private specs
use `git+https://github.com/org/repo.git#<40-character-sha>&path:/package`.
Root-package Git releases may omit `&path:`. Release selection/attestation and the
complete first-party peer closure belong to the canonicalizer; this adapter does
not certify a release or migrate the consumer's dependencies.

The command requires pnpm 9.15.3 and clean, tracked canonical manifest/lock inputs.
It refuses hidden index flags, staged input changes, an existing hook, and
first-party `pnpm.overrides` (pnpm applies those after `readPackage`, defeating
local links). The current npm starter has those overrides: the Git-release mode
will refuse until the separately reviewed migration removes them. Do not remove
an override merely to make this command pass.

`on` leaves package.json byte-identical and never sets Git index flags. The working
lock changes visibly, including pnpm's hook checksum; **do not commit that lock**.
A private record under Git's worktree-specific metadata stores the original bytes
and the install's resulting lock. `off` checks the manifest, index/committed
baseline, map, hook implementation, hook, pnpm configuration, and working lock for
drift, restores the original lock byte-for-byte, removes only its own generated
hook, then runs `pnpm install --frozen-lockfile --ignore-pnpmfile`. It deletes its
record only after that succeeds. It neither regenerates nor selects a new release.

If drift is reported, preserve the edits and reconcile them explicitly before
retrying; there is no discard/accept-drift switch. If enabling fails, its resulting
lock and guarded record remain for `off` recovery. If the canonical reinstall
fails, the canonical lock is already restored and the record remains retryable
with `off`. `status` validates the same guards without installing anything.

## Coexistence with the npm rollback adapter

`scripts/js-overlay` and `js.local.json` remain the legacy npm rollback mechanism
until the integrator's retirement window closes. They are not aliases. The legacy
mode rewrites and hides manifests, while the Git-release adapter does neither.
Each refuses to enable over the other; turn the active mode off first. The new
adapter never clears legacy skip-worktree bits or deletes another hook.

Fast tests: `node --test scripts/tests/pnpm-overlay.test.mjs`. These use local
fixture Git repositories and a pnpm process stub to prove state/guard transitions;
they do not claim real resolution or consumer build compatibility. A separate
real pnpm enable/build/test/disable proof must establish those on exact Git pins.
