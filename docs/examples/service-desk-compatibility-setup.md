# Service desk: supplied local compatibility setup

Use this chapter with the supplied B21 compatibility preview, its private input snapshots and
retained evidence. It records the staged setup that actually ran on 2026-10-31, followed by a
Member-created request and a different Owner's persisted approval. Obtain that packet before
starting; the shipping lock alone does not contain the working PHP sources.

This is separate from [the cumulative usage and Inspection preview](service-desk-current-preview.md)
and its input manifest. Do not mix their Composer pairs or JavaScript inputs.

## Inputs and containment

The application source is `5e6e053d5f012c31e4f0246ff8e7179de11d83b7`.
Use a new disposable local host and the supplied private package snapshots. Keep its environment,
SQLite database, Composer home/cache, package store and generated output private to that root.
Never run this preparation against a live Herd site or an owner's database.

| Input | Source |
| --- | --- |
| JavaScript Beam (GO122) | `1f6dbbf0e338b76daea4723c08e61bc71e1ea1a2` |
| JavaScript Schemastud | `35dff9a065e66a503d3bc743a1566f0b3200d9f6` |
| JavaScript `splicewire/_resources` | `fd44ce1a32f299be642af4ec84528d154bee0510` |
| PHP `splicewire/laravel-beam-workflows` overlay | `1ad7270f5d5d5689ab19e7f04cc44817a0d3fbb9` |
| PHP `splicewire/laravel-beam-ux` overlay | `7843f9f3a354d0a1fe9a515db765aaa2cb9015d4` |

The frozen JS source cohort is supplied with its matching built package inputs; it is not a request
to rebuild or solve newer packages. The actual candidate used owned copies of Beam and Schemastud
under its sibling input directory. The declared `_resources` cohort is GO122's dependency input;
it is not an additional direct entry in the host's four-package overlay map.

Retain the supplied `composer.json` and private `composer.lock`. Their SHA-256 values are
`cfe10b530fc5a040395d650333151f2c999e879ee6af8d34252d1af9998d3830` and
`edec169d100565e46a3b43fe1bb764805f002f6c9dd2a636f5ac9e497c7cce00` respectively.
The lock still names UX `d1c3efa5e8489feb2ba1d37845e64ea3b41735d9` and Workflows
`b328f2de`; the physical source differences below are intentional and disclosed. Do not represent
the result as a lock-faithful clone or replace the lock with a new dependency solve.

## Prepare JavaScript before setup

Use Node 22 and pnpm 9.15.3. The supplied `pnpm.local.json` maps the exact committed release specs
for `@splicewire/beam-ux`, `@splicewire/beam-inertia`, `@splicewire/beam-resources` and
`@schemastud/blockdoc` to the matching owned source directories. Remap only those local directory
locations when receiving the packet; preserve each release spec. See the existing
[overlay command contract](../development/pnpm-overlay.md).

From the disposable application root, the recorded command was:

```sh
nice -n 10 node scripts/pnpm-overlay.mjs on
```

The run used `.npmrc` with `ignore-scripts=true`. The adapter left `package.json` unchanged and
recorded its local lock visibly. Its successful operation is retained in
`evidence/minimal-cohort-install/pnpm-overlay-on.log` and `.meta`; the resulting pnpm lock SHA-256
was `3d76b2bad63bc7f14bb6e348e5633da1c1bc3565ec0a7fae07a7bf8c19ca6002`.
The older `js-overlay-on.log` records a refused attempt because beam-intake was not a direct
starter dependency. That legacy attempt is not the successful preparation command.

## Run the recorded PHP setup sequence

Use the preview's private `.env`, generated APP_KEY and new local SQLite database. The observed
environment was `APP_ENV=local`, with database-backed sessions, cache and queue, log mail and a
loopback application URL. The setup command runs the starter's existing install, Beam preparation
and JS build scripts. Set `PRIVATE_COMPOSER_HOME` and `PRIVATE_COMPOSER_CACHE` to directories
contained in your disposable root; the values below are placeholders for those owned directories.

```sh
nice -n 10 env XDEBUG_MODE=off NODE_OPTIONS=--max-old-space-size=2048 \
  COMPOSER_HOME="$PRIVATE_COMPOSER_HOME" COMPOSER_CACHE_DIR="$PRIVATE_COMPOSER_CACHE" \
  herd composer setup
```

The observed stages were:

1. The first setup attempt failed because the locked Workflows source lacked
   `ParticipatesInWorkflowTransaction`. Its original `composer-setup.log` and `.rc` remain retained.
2. The private physical `vendor/splicewire/laravel-beam-workflows` binding was changed to the
   supplied Workflows `1ad7270` snapshot. Optimized Composer autoload was regenerated, with output
   in `workflows-autoload-refresh.log`. Retain the source binding supplied with the packet rather
   than editing the package or changing its lock reference.
3. The same setup command was retried and completed successfully. See `composer-setup-retry.log`;
   the retained monitored run reached 1,912,752 KiB peak owned RSS, pressure 1 and zero swap-out
   delta. Package discovery also completed (`workflows-package-discover.log`).
4. The initial UX source then failed the home request because `PageEntryRef::hiddenByPreview()`
   was missing; a diagnostic shim exposed a second missing `PreviewBar` class. Those failures
   remain in the receipt. The final physical `vendor/splicewire/laravel-beam-ux` binding uses the
   complete supplied `7843f9f3` snapshot, which contains both APIs, rather than either diagnostic
   shim. After that binding, package discovery succeeded and home and authenticated Service desk
   requests returned HTTP 200.

The UX snapshot's `PageEntryRef.php` SHA-256 is
`d9f3e030d5175db9da5b793c30a88cafd0e6514e9ad0baf571eacd9204745481`; its `PreviewBar.php`
SHA-256 is `3295afca28f1a659d82d3e32b9ffaefff91e3041ea822796ecbc294fb1f1e451`.
Physical source binding and package discovery are separate from the older lock metadata. The
receipt does not attest a fresh setup invocation with both final overlays already bound.

## Follow the request and retain the receipt

Use the supplied local demo Member session to create a request in the stock Frame form. In a
separate session, use a different same-team Owner to approve it. The recorded create and approval
POSTs both returned 200; reload showed Approved, and persisted creator/approval actor IDs differed.
This proves the local request/approval journey, not the cumulative usage/Inspection walkthrough
or a full application suite.

The supplied evidence root contains `B21-MINIMAL-COMPAT-RECEIPT.md`, the
`minimal-cohort-install/` logs above and `b21-root-api-compat/` source-binding/replay records.
The final replay result JSON SHA-256 is
`1705440153c3052c9833fbbfa4387b45b0d561eeac842a834d0d81e59bfd056f`.
Preserve failed attempts beside the successful retry. Publication, a default locked-clone result
and live documentation delivery remain separate from this working local preview.
