<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Splicewire\Beam\Accounts\Http\ErrorPages;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // The token-authenticated door (`/api/user`) — see routes/api.php. A host whose account realm
        // can mint personal access tokens needs at least one route that accepts one.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // beam-accounts' camel wire carries the same secrets as the framework's snake defaults (kept while any caller
        // sends them): leave them byte-exact. Their flash twin is in withExceptions below.
        $middleware->trimStrings(except: ['currentPassword', 'passwordConfirmation']);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            // Bind spatie's team scope to the signed-in user's current team for the request.
            // WITHOUT THIS LINE THE HOST AUTHORIZES NOTHING, and does so silently: beam-accounts'
            // roles are team-scoped (`roles.team_id`), so with the registrar's team id left null a
            // user resolves ZERO roles, `BaseModelPolicy::viewAny()` finds no `<alias>.view` token,
            // and every cascade-policed resource is denied to its own team owner. Measured here on
            // 2026-09-05: with the permission rows seeded and the team id set the owner's `viewAny`
            // was true in tinker while the live `/frame/manifest` still rendered ONE nav row.
            //
            // Host-side on purpose, and beam-accounts says so in terms — the package ALIASES this
            // middleware and stops ("Add this to the app's `web` group", Http/Middleware/
            // SetCurrentTeamPermissions.php:12). Pushing it into `web` from the provider would
            // silently overwrite the tenant scope at every tenanted host, where
            // beam-tenancy's PermissionsTenancyBootstrapper.php:56 sets the SAME registrar slot to
            // the tenant key. ~/Herd/schemastud does the equivalent through `frame.middleware`.
            'splicewire.team',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Never flash the camel secrets back into old input on a validation failure (the snake ones are framework defaults).
        $exceptions->dontFlash(['currentPassword', 'passwordConfirmation']);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A browser's 403/404/419/500/503 renders the packaged `error` page (@splicewire/beam-inertia)
        // in the account shell (signed in) or the site shell (guest), with the original status. Every
        // JSON response above is left exactly as it is, and a 500 keeps the debug page while
        // APP_DEBUG is on. See splicewire/laravel-beam-accounts `Http/ErrorPages.php`.
        ErrorPages::register($exceptions);
    })->create();
