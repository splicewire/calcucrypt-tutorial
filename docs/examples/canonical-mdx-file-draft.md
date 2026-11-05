# Take an MDX file edit as a draft

Use the Release’s **Files** comparison for an already published central Page authored as MDX. This example requires the compatible Files package/client, a working native Node MDX compiler, a contained local mirror and an author who can edit every addressed Release member. It does not register a new file. Restore, Take and reviewed Publish each require their own explicit intent.

The supported header has exactly two unchanged fields, in the published order:

```mdx
---
title: Help
navOrder: 2
---

# Your edited body
```

Keep the exact published header bytes. `title` is 1–120 ASCII letters, digits, spaces, dots, underscores or hyphens, starting with a letter or digit. `navOrder` is zero or a decimal of at most six digits without leading zeroes. The complete file must be UTF-8, use LF newlines, fit within 1 MiB and have nonblank content. Quotes, comments, duplicate or extra fields, aliases, CRLF and changed header values are refused. Native roundtrip equality verifies preservation; it does not validate MDX syntax or make code safe to execute.

1. Edit only the body of the existing published mirror file.
2. Open its Release and **Files**, then inspect **Current published file**. The historical Release member stays fixed while this comparison uses the entry’s current publication pin.
3. Select **Take file as draft** and review the captured working head, published pin and file hash. **Cancel** writes nothing and returns focus. Reopen to give explicit consent.
4. Confirm the take. It creates a new working version, replacing any inspected pending draft. Earlier versions remain in history, and pending page settings remain with the new draft. The published pin, guest content, local file and Git stay unchanged. Review and publication are separate ordinary actions.

A changed head, pin, file or placement refuses the take. Reload the comparison and give consent again after inspecting the new state; a submit never substitutes fresh tokens for the ones you confirmed. Missing or unsupported files cannot initialize publication. For an already-published canonical Page/MDX, Restore creates only the current published file when absent; it never overwrites a different or appearing file. Theme missing-file Restore keeps its existing behavior.

Compilation can fail after the new draft has been retained. **Draft retained; compilation failed** reports the actual new working head and unchanged published pin. Review the draft before publication. The warning is safe response feedback, not saved diagnostic history; a later comparison without that diagnostic does not prove the syntax has been repaired. The MDX result does not link to the Theme editor.

This is a deliberate source-authoring operation. It retains the existing code-profile and all-member authority checks. Filesystem checks observe changes before writing the database; they do not provide atomic exclusion against an external editor. Publication, batch import, TSX, metadata edits and arbitrary YAML are outside this example.

## Restore a missing file, then review a fixed saved version

For a missing published canonical MDX file, inspect the current pin and choose **Restore missing published file**. Cancel writes nothing and returns focus. Confirm with the captured head/pin/absence/placement. Restore writes the current published pin even when Files addresses an older Release member; pending drafts/history and Git remain unchanged. Any appearing file blocks creation. A partial-write failure preserves its file for explicit inspection or moving before a new attempt; it is not a successful Restore.

The optional teaching page is off by default. Set `BEAM_CANONICAL_MDX_REVIEW_ENABLED=true` only in an explicitly selected isolated host. It mounts `/account/canonical-mdx/{entryId}/review`, checks actual author/edit authority before loading saved version choices and shows only Releases whose members are this entry. This is a HOST-WIDE opt-in: it sets native review_required=true and allow_self_approval=false for ALL Releases in this host, not only this page or MDX entries. The ordinary backend permissions and Release workflow remain authoritative. This is a thin composition of the shipped ReleasePanel and transport, not a source canvas editor.

After **Take file as draft** returns a saved version, open the per-ID review page, reload saved versions, create a Release and add that exact saved ref. Request review; a different authorized author opens the same page, selects the existing Release and approves. Publication without required review and self-approval refuse. A newer working draft does not replace the selected member. If the published pin moves, explicitly Rebase the member (retaining its selected version) and obtain a new review.

Publish the approved fixed member once. Ordinary publication intentionally writes that selected version's mirror, moves the public pin and records the frozen local bytes; it has no last-minute submitted external-file hash check. The existing receipt/Files/history UI reports its actual local SHA or truthful recording failure. Unrelated staged/unstaged/untracked files and the caller index are not included in its private-index commit. Recording failure does not undo committed publication; Commit now retries the frozen receipt instead of republishing/remirroring. No push, generic overwrite recovery or atomic database/files/Git guarantee follows.
