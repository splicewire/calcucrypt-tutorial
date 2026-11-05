# Documentation and Scalar publication

`splicewire/laravel-beam-docs` and `@splicewire/beam-docs` own documentation. Generic entries and MDX remain in Beam UX. Run the normal host/shared migrations when upgrading: the additive requirements migration precedes docs-root adoption, and publication history lives in the host database.

`composer docs` generates the public reference once. The embedded Scalar reference and publication service read that same configured artifact. SDK generation continues to select its separate Scribe configuration explicitly.

Set `BEAM_DOCS_VISIBILITY=private` to restrict the entire documentation subtree to authenticated readers. Existing entry restrictions still apply. `BEAM_DOCS_ENABLED=false` disables docs while retaining authored entries. After adopting the docs-root requirement, removing the PHP and frontend docs packages leaves retained docs inaccessible; generic content continues working. Remove the docs imports/configuration from `resources/js/app.tsx` with the frontend package.

Authorize publishing through `beam-docs.publish`. The operator page is `/beam/docs/manage`; it accepts an explicit release version, reports queued/running/succeeded/failed state, and retries the captured artifact after failures. The starter delegates publishing to `ux.operator.author`; the flagship's Root gate also authorizes its operators.

## Release automation

On the prepared application host, with the same database/configuration used by its queue workers:

```sh
scripts/publish-docs.sh "$RELEASE_VERSION"
```

This runs generation and then the same publication service synchronously; either failure exits nonzero. It is a release-pipeline entry point, not a second publisher. Invoke it after migrations, once per release. Keep its JSON result as the release job's output; the durable attempt and snapshot are retained in the host database and visible to the operator UI. CI must execute it in the deployed host context; a disposable build database would lose that shared history.

Provision the Scalar destination and server-side `SCALAR_API_KEY`; set `BEAM_DOCS_SCALAR_ENABLED=true`, `BEAM_DOCS_SCALAR_NAMESPACE`, and `BEAM_DOCS_SCALAR_SLUG`. No destination or token is committed. The worker needs Node 24+ and the pinned `@scalar/cli@2.1.0` installed by the frontend manifest. Set `beam.docs.scalar.executable` if the executable is elsewhere. The release script accepts `BEAM_DOCS_PHP` for the PHP executable path.

A private upload requires a private Registry destination; an existing destination with different visibility is refused. Configure queue retry intervals above the job's 180-second timeout. `beam.docs.scalar.show_link=true` enables the conditional Registry link after a successful current-destination publication. See the installed docs package's `docs/scalar-publishing.md` for protocol and recovery details.
