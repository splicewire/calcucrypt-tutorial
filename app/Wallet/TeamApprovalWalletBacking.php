<?php

namespace App\Wallet;

use Illuminate\Validation\ValidationException;
use Splicewire\Beam\Particle\Backing\CollectionBacking;
use Splicewire\Beam\Particle\Backing\ResolvedRecord;
use Splicewire\Beam\Particle\Backing\ResolvesRecord;

final class TeamApprovalWalletBacking extends CollectionBacking implements ResolvesRecord
{
    protected function rows(array $filters): iterable
    {
        app(TeamWalletContext::class)->requireTeam(request()->user());
        if ($filters !== []) {
            throw ValidationException::withMessages(['filter' => 'The selected team wallet has no filters.']);
        }

        yield app(TeamApprovalWallet::class)->read(request()->user());
    }

    public function resolve(string $id, array $filters): ?ResolvedRecord
    {
        unset($filters['source']);
        foreach ($this->rows($filters) as $row) {
            if ($row->id === $id) {
                return new ResolvedRecord($row);
            }
        }

        return null;
    }
}
