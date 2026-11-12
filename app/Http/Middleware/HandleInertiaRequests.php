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
     * Include the product's declared calculator keys in the package's shared can-map.
     *
     * @return array<string, bool>
     */
    protected function canMap(Request $request): array
    {
        try {
            $keys = array_values(array_filter((array) config('beam.core.entitlements.keys', []), is_string(...)));

            return app(CanMapBuilder::class)
                ->withFeatureKeys(['os.enter', ...$keys])
                ->forPrincipal($this->shellPrincipal($request));
        } catch (Throwable) {
            return [];
        }
    }
}
