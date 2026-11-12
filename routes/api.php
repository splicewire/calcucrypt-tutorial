<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Splicewire\Beam\Facades\Particle;

/*
|--------------------------------------------------------------------------
| The token-authenticated door
|--------------------------------------------------------------------------
|
| This starter's account realm can MINT personal access tokens (`/account/tokens`, backed by
| `splicewire/laravel-beam-accounts`' account-tier surface). A credential a host can issue and cannot
| spend is not a credential, so there has to be at least one route that accepts it — this is it, and
| it is deliberately the smallest one: "who am I, according to this bearer".
|
| ⚠️ **`auth:sanctum` is not bearer-only.** `Sanctum\Guard::__invoke()` tries `config('sanctum.guard')`
| — the session `web` guard by default — BEFORE it looks at the Authorization header. Because
| `bootstrap/app.php` calls `statefulApi()`, a request from this host's own frontend (a Referer or
| Origin in `sanctum.stateful`) starts the session, so a signed-in browser reaches this route with no
| token at all, and its writes must carry the CSRF token. That is Sanctum's SPA-cookie design. Any
| other caller gets no session here and must present a bearer token. So a test that wants to prove a
| token works (or that a REVOKED token is refused) must send no first-party Referer or Origin, or it
| will be measuring the session instead. Without `statefulApi()` none of this holds: the `api` group
| starts no session, and a signed-in browser is a stranger here (tests/Feature/StarterDefaultsTest.php).
|
| Registered in `bootstrap/app.php` under the `api` prefix, so these live at `/api/*`.
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user()->only(['id', 'name', 'email']);
})->name('api.user');

// Calcucrypt's public read API. The declarations on CalculatorSpecData and GuideData say what each
// resource is and who may read it, and a bare mount takes its verbs from them: a read-only resource
// mounts `index` and `show` and no write route. The `api.` name stem keeps them clear of the pages'
// own `calculators.*` / `guides.*` route names, and a public mount advertises no webhook event
// vocabulary.
//
// `resolve` is the ResolveCalculator operation: `POST /api/calculators/{slug}/resolve`, named
// `api.calculators.resolve`. The widget calls it through the generated client, with the session
// (bootstrap/app.php's `statefulApi()`), so the policy sees who is signed in.
Particle::mount('calculators', 'calculators')
    ->names('api.calculators')
    ->ops(['resolve'])
    ->hookEvents(false);

Particle::mount('guides', 'guides')
    ->names('api.guides')
    ->hookEvents(false);

// A reader's saved inputs: the generator's defaults made this resource writable, so the bare mount takes all
// five verbs (index, show, store, update, destroy). The calculator page calls them with the session, and
// The package-owned backing and explicit policy isolate each reader and schema.
Particle::mount('saved-inputs', 'saved-inputs')
    ->names('api.saved-inputs')
    ->hookEvents(false);

// The plans on sale (PlanOfferData, read from config/plans.php) and the PurchasePlan operation:
// `GET /api/plans` and `POST /api/plans/purchase`. The op has no subject, so its URL has no `{id}`;
// the resource is read-only and not showable, so the bare mount takes only the list, and nothing
// can swallow the op's URL.
Particle::mount('plans', 'plans')
    ->names('api.plans')
    ->ops(['purchase'])
    ->hookEvents(false);
