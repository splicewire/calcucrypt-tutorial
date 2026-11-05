<?php

namespace App\Wallet;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Splicewire\Beam\Authorization\ResourceReadPolicy;
use Splicewire\Beam\Particle\ParticleResource;

final class TeamApprovalWalletReadPolicy implements ResourceReadPolicy
{
    public function inspect(?Authenticatable $actor, ParticleResource $resource, Request $request): Response
    {
        return Gate::forUser($actor)->inspect('service-desk.wallet.read');
    }
}
