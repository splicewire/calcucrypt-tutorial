# Converting an existing Saved Inputs store

Fresh tutorial installs use the package's particle table. They create no `saved_inputs` table or application storage model. Existing installations retain their old table when the old creation migration leaves the tutorial source.

Conversion is an explicit maintenance operation before the new resource accepts writes. Stop legacy writers and keep the new application in maintenance mode until verification finishes. Take a database backup through the installation's supported backup tooling, then run from the intended isolated candidate first:

```sh
XDEBUG_MODE=off herd php artisan calcucrypt:migrate-saved-inputs
XDEBUG_MODE=off herd php artisan down
XDEBUG_MODE=off herd php artisan calcucrypt:migrate-saved-inputs --apply
```

The default is read-only preflight: no record, schema file, event, revision or snapshot is created. Its JSON result distinguishes a missing source table (`sourcePresent: false`) from an empty source table. `wouldCreate`, `created` and `skipped` count Saved Input records; `examined` counts legacy rows.

Apply requires maintenance mode. It validates the full source first, then uses the package's canonical writer in one transaction on the same connection as the source, users and calculators. It preserves UUIDs, owner identity, original creation/update timestamps and calculator references. Existing-but-unpublished calculators remain readable; new public writes still require a published calculator. Unknown calculator input names or old enum-like strings are preserved; invalid JSON, missing owners/calculators, invalid names/timestamps, nonfinite numbers, boolean/null/nested/list values and conflicting target UUIDs fail without partial record conversion. Fix or deliberately archive such source rows before retrying.

No target is overwritten. An exact existing schema/owner/payload/timestamp match is skipped. Reruns are supported only within this pre-cutover window: after users can edit or delete particles, the old table is an archive, not an import source. No conversion ledger or synchronization process is installed, and a rerun after reopening writes could conflict with edits or recreate deleted content. The command never drops or edits the source table.

Imported records have no fabricated historical snapshots or revisions, and the import suppresses `BeamParticlePersisted` notifications/indexing events. Normal future lifecycle operations remain package-owned. Apply can register the declared storage schema on disk outside SQL; that declaration artifact is not claimed transactional. The read-only preflight does not register it.

Before reopening writes, verify counts, owner isolation, calculator names and saved timestamps using the isolated proof/approved installation procedure. Then leave maintenance mode with:

```sh
XDEBUG_MODE=off herd php artisan up
```

The payload keeps a stable calculator UUID; the declared read projection resolves its current slug, so renames follow normally. Application model deletion removes the corresponding Saved Inputs when a calculator or owner is deleted. Direct bulk SQL bypasses these model events; generic JSON storage does not provide the retired table's database foreign-key cascade. Use the application deletion path for these associations.

The wire fields remain `id`, `calculator`, `name`, `inputs`, `savedAt`. The explicit version-2 storage schema corrects the old generated nullable/required input declaration to express omission separately from invalid null. The declared numeric/string map is emitted as an object on both transports, including an empty map after update; the old array-cast projection emitted `[]` for that case. This presentation correction preserves field names and scalar values.

Direct bulk SQL deletion of a calculator bypasses the normal association hooks and can leave Saved Inputs referencing a missing calculator. Reads fail closed: its owner's entire Saved Inputs list and that record's detail return404 through REST and Frame until the orphan is deliberately removed or repaired. Normal application model deletes remove all matching rows, including populations larger than one deletion chunk. Array construction of SavedInputData is reserved for stored particle payload plus trusted envelope; callers with wire data must use its ordinary constructor instead.

Legacy numeric-only object keys that decode as PHP lists (for example `{"0":"x"}`) are refused rather than silently reinterpreted as named maps. Preflight identifies the source UUID so the operator can deliberately repair or archive that row. Rerun payload comparison sorts object keys recursively and preserves scalar types; database key ordering is immaterial, while changed values still conflict.
