<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Splicewire\Beam\Accounts\Facades\BeamAccounts;

final class RequireMarketplaceRoot
{
    public function handle(Request $request, Closure $next): mixed
    {
        abort_unless($request->user() !== null && BeamAccounts::isRoot($request->user()), 403);

        return $next($request);
    }
}
