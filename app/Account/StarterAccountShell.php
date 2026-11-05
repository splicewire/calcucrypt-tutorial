<?php

namespace App\Account;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Splicewire\Beam\Accounts\Contracts\AccountShellProvider;
use Splicewire\Beam\Accounts\Data\AccountData;
use Splicewire\Beam\Accounts\Data\AccountShellData;
use Splicewire\Beam\Accounts\Data\MetricData;
use Splicewire\Beam\Accounts\Data\PlanData;
use Splicewire\Beam\Accounts\Data\ProfileData;

/**
 * The starter's account-shell provider — fills the generic beam-accounts SHAPE
 * ({@see AccountShellData}) with neutral demo values so the packaged `<AccountShell>` renders a plan
 * chip + public profile OOTB. A real host swaps these for its own product data; the SHAPE and the
 * front-end render do not change.
 *
 * beam-accounts binds {@see \Splicewire\Beam\Accounts\Support\NullAccountShellProvider} by default
 * (returns null → the shell degrades gracefully). Binding this one is the host's "wrote only config"
 * step to prove the account realm renders.
 */
class StarterAccountShell implements AccountShellProvider
{
    public function shellFor(?Authenticatable $user): ?AccountShellData
    {
        if ($user === null) {
            return null;
        }

        $email = (string) ($user->getAttribute('email') ?? '');
        $name = (string) ($user->getAttribute('name') ?? $email);
        $handle = '@'.strtok($email !== '' ? $email : 'you', '@');

        return new AccountShellData(
            plan: new PlanData(
                tier: 'free',
                label: 'Free',
                credits: null,
                max: null,
            ),
            profile: new ProfileData(
                handle: $handle,
                avatar: $this->initials($name),
                metrics: self::metrics(),
            ),
            account: new AccountData(
                email: $email,
                paymentMethodLabel: null,
            ),
            upsells: [],
        );
    }

    /**
     * The profile's headline metrics: demo values (a fresh account is a team of one with no projects). Each label is
     * pluralised by its own count, so the shell never reads "1 MEMBERS" (launch ticket 05 item 6).
     *
     * @return list<MetricData>
     */
    public static function metrics(): array
    {
        $projects = 0;
        $members = 1;

        return [
            new MetricData(label: Str::plural('PROJECT', $projects), value: (string) $projects),
            new MetricData(label: Str::plural('MEMBER', $members), value: (string) $members),
        ];
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = array_map(fn (string $p): string => mb_strtoupper(mb_substr($p, 0, 1)), array_filter($parts));

        return implode('', array_slice($letters, 0, 2)) ?: '·';
    }
}
