<?php

namespace App\Http\Middleware;

use App\Marketplace\TeamMarketplaceContext;
use Closure;
use Illuminate\Http\Request;

final class RequireMarketplaceTeam
{
    public function handle(Request $request, Closure $next): mixed
    {
        abort_unless(app(TeamMarketplaceContext::class)->team($request->user()) !== null, 403);

        return $next($request);
    }
}
