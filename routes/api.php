<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
| — the session `web` guard by default — BEFORE it looks at the Authorization header, so a signed-in
| browser reaches this route with no token at all. That is Sanctum's SPA-cookie design and it is
| correct; it just means a test that wants to prove a token works (or that a REVOKED token is
| refused) must issue its request from a context carrying no session cookie, or it will be measuring
| the session instead.
|
| Registered in `bootstrap/app.php` under the `api` prefix, so these live at `/api/*` with no CSRF
| and no session middleware of their own.
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user()->only(['id', 'name', 'email']);
})->name('api.user');
