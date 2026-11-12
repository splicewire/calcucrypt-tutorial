<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Splicewire\Beam\Entitlements\CanMapBuilder;
use Splicewire\Beam\Ux\Http\Concerns\SharesBeamShell;
use Throwable;

class HandleInertiaRequests extends Middleware
{
    use SharesBeamShell;

    protected $rootView = 'app';

    /**
     * Share the starter's OS capability through the package shell.
     *
     * @return array<string, bool>
     */
    protected function canMap(Request $request): array
    {
        try {
            return app(CanMapBuilder::class)
                ->withFeatureKeys(['os.enter'])
                ->forPrincipal($this->shellPrincipal($request));
        } catch (Throwable) {
            return [];
        }
    }
}
