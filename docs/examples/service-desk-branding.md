# Save a Theme token and brand the request content

Current authoring compatibility: follow [Theme draft/Release adoption](service-desk-releases.md)
for the selected package pair and fresh/upgrade setup. Theme **Save draft** keeps published tokens
unchanged; explicit **Publish** releases the saved version (or requests review). Earlier slice proof
and candidate identities remain historical. This chapter's resource/access behavior is unchanged.

Continue from [existing Theme entitlement access](service-desk-entitlements.md) at the current
starter candidate, with unchanged Beam UI `ec097daba3c1ace1c482c07467b3eb71bc0ec729`.
The starter maps published Theme accents into the existing Frame console's **content controls**.
The packaged console, form, list, read-only detail and outer layouts remain the same components.
Sidebar, logo, account screens and unrelated widgets retain their own palette.

## A separate fresh local host

Use the prerequisites and **Start a separate fresh host** section of the entitlement chapter,
through its new clone/committed installs, private .env/new entitlement-guide.sqlite, demo-user
seeding opt-out, ordinary installer and explicit ThemeSeeder. Use this chapter's current starter
checkpoint, never a live owner checkout, database or .env. Keep committed locks unchanged.
For rig proof, use the existing scratch ownership/capacity and guarded retention tools.

Follow the [explicit local UI build/overlay recipe](service-request.md#build-the-explicit-local-ui-candidate)
with a new isolated Beam clone at the checkpoint above, pinned build tools, and this new starter
fixture as SERVICE_DESK_ROOT. Only that build recipe comes from the historical resource chapter;
do not check the starter back to its historical private-row checkpoint. Then:

```bash
XDEBUG_MODE=off herd php artisan splicewire:beam:generate:assets --json
XDEBUG_MODE=off pnpm build
XDEBUG_MODE=off herd php artisan splicewire:beam:ux:seed-nav
XDEBUG_MODE=off herd php scripts/service-desk-entitlements.php setup storage/app/private/branding-actors.json
XDEBUG_MODE=off herd php scripts/service-desk-entitlements.php grant storage/app/private/branding-actors.json
XDEBUG_MODE=off herd php artisan serve --host=127.0.0.1 --port=46280 --tries=1 --no-reload
```

These commands replace the entitlement chapter's actor setup/server commands; do not run both
setups. The helper requires fresh local tutorial users/teams and creates A Owner/Admin/Member,
B Owner, teamless and an eligible-A/selected-B actor. Credentials/tokens stay in the mode600 private
file. Read logins locally; never export that file, auth headers or raw mail into evidence.
Only this disposable fixture receives grant/content mutations. No external mail is sent.

The initial production build includes the host adapter. **Later token saves do not require a build.**
The CSS mirror is not an imported runtime stylesheet. Page re-requests fetch the published Theme.

## Use the existing editor and resource

1. Sign in as owner. Open Service requests and create “Branded queue request” using the ordinary
   New service request → Title → Save path. Open the returned list, then its read-only detail;
   there is no detail Save or record edit/delete. In a separate context, member can see that same
   selected-A request. B Owner cannot read it; teamless cannot list/create.
2. In account navigation open **Theme**. The existing **Theme tokens** editor displays Canvas theme
   and Site theme, supplied by the server's schema. In **Site theme**, change **Accent** to `#7c3aed`.
   Keep Accent foreground white; this purple/white pair is legible. Do not change Canvas theme's
   separate Accent. Use the native color field, then **Save draft**. Published controls stay unchanged. Click **Publish** and wait for the Release to show Published (or complete required independent review).
3. Re-request Service requests (ordinary navigation or a page reload). **New service request** now
   uses purple with white text. Open New: the normal Save button uses the same pair. Create another
   request, inspect its list return and read-only detail. Detail inherits the same scoped primary
   tokens but does not acquire a Save button or new write authority.
4. Inspect dark appearance through the existing appearance preference/system setting. The resource
   uses the separate **Dark accent** and **Dark accent foreground** pair from Site theme, rather
   than applying the light purple/white pair to every scheme. Defaults are light accent/dark label
   in dark appearance. The outer sidebar/account palette remains independent in both schemes.
5. An already-open second tab need not update immediately. Re-request its page to receive the new
   shared Theme props. No cross-tab event, instantaneous preview or client cache broadcast is promised.
   The public site's guest Dashboard CTA also consumes Site theme, as a separately labeled witness;
   it is not the evidence that the resource controls changed.

The template is host-global branding. A team grant supplies eligible User feature reach, not a
private branding namespace for that team. Current-team membership still governs shared requests;
roles and entitlement aggregation are unchanged. This is not a plan/subscription feature, physical
tenancy, team switcher or permission-management UI.

## The small consumer seam

`resources/js/service-desk-theme.tsx` reads existing `page.props.theme.site` and supplies light/dark
accent variables inside a wrapper. `resources/js/app.tsx` appends that wrapper only for frame/console
through the supported Inertia layout option, preserving the packaged layout chain and console.
`resources/css/app.css` maps those scoped variables to shadcn primary/primary-foreground and rebinds the Tailwind color
aliases in that same scope; missing
shared values fall back to the host's existing light/dark defaults. The wrapper adds no layout box.

This is a host component-library token mapping, the second layer of the existing branding pattern.
It does not style each button, fork Frame/SchemaForm, deep-import a private page, change the PHP
resolver/save path or recolor every widget. Changes to this adapter's source CSS/JS need the usual
initial type/build; changes to its stored token values need only a page re-request.

## Inspect draft versus explicit publication

Keep safe before/after snapshots of the actual body via EntryBodyEnvelope, resolved Theme, this
Theme entry's published_version, publication head and ordered versions, and its codec mirror.
ThemeEditor uses save-draft to advance the working head while leaving the published pin/mirror
unchanged. Explicit Publish uses a server-owned fixed-version Release of one. After publication,
expect its selected version at the publication pin and its body at the mirror. The older save-body
API retains its existing immediate-publication contract for other callers. Inspect actual IDs and
stored values; HTTP200 or an enabled Save alone does not establish this result.

The form omits the shell namespace; it must survive untouched, as must canvas when editing site.
On the first save, the form can materialize previously implicit site defaults, including fonts,
foreground and dark values. Compare resolved effective values as well as raw body keys: changing one
visible token need not mean the raw body has only one new key.

The configured beam-ux disk roots resources/beam-ux. Default placement for this entry is
`theme/theme/default.css`. Its source must equal the CssBodyCodec decode of the published body;
encoding it back should recover the token object. This is a published **projection**, not runtime
authority. ThemeResolver reads the publication pin's version payload. Theme-kind CSS is not a page
JS artifact; a null compileError does not mean a Vite build ran or imported that mirror.

Save does not make a source commit, push or deploy. The mirror may be untracked in this fresh clone.
The codec's generic “git-tracked RECORD” comment does not change that. Release commits through a
private index, per-version git SHA/failure/retry and explicit file-to-draft reconciliation belong to
the separately selected EP-16 work; do not run a git-recording command for this chapter.

## Keep the authority boundary

Member can still create/read the shared queue but cannot save the Theme. A valid-shaped save-body
POST as member returns403 without changing body, head/pin, versions or mirror. Revoke only the
fixture team's matching manage rows:

```bash
XDEBUG_MODE=off herd php scripts/service-desk-entitlements.php revoke storage/app/private/branding-actors.json
```

After revoke the formerly authorized owner's valid-shaped changed body also returns403 with all
those durable values unchanged. Use normal cookie/CSRF context for body operations. Basic selected-team
request access remains; revocation does not delete published branding. Preserve the helper's unrelated
view grant. No role, request ownership or historical-null-team quarantine is modified here.

Run relevant checks in the new fixture:

```bash
XDEBUG_MODE=off herd php artisan test --filter='ThemeSharedPropTest|ThemeEditorSeatTest|RealmAuthoringGatesTest|EntitlementGuideTest|ServiceRequestTeamTest'
pnpm exec prettier --check resources/js/app.tsx resources/js/service-desk-theme.tsx resources/css/app.css
pnpm exec eslint resources/js/app.tsx resources/js/service-desk-theme.tsx
XDEBUG_MODE=off pnpm build
```

Production build includes generated/client type checks. The browser consumer check additionally
compares actual resource control styles with the server's resolved token pair; a build alone is not
UI proof. Full configured PHPStan retains the two known unrelated schema-token migration errors;
this chapter does not imply full-suite health. Stop only your listener/browser, release capacity
and use existing guarded scratch retention/teardown for private fixtures and captures.
