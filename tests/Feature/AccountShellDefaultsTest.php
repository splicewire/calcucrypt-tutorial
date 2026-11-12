<?php

namespace Tests\Feature;

use App\Models\User;
use Splicewire\Beam\Accounts\Contracts\AccountShellProvider;
use Tests\TestCase;

class AccountShellDefaultsTest extends TestCase
{
    public function test_the_account_shell_uses_identity_without_inventing_plan_or_metrics(): void
    {
        $provider = app(AccountShellProvider::class);
        $this->assertNull($provider->shellFor(null));

        $shell = $provider->shellFor((new User)->forceFill(['email' => 'demo@example.test', 'name' => 'Demo User']));

        $this->assertSame('demo@example.test', $shell->account->email);
        $this->assertSame('@demo', $shell->profile->handle);
        $this->assertSame('DU', $shell->profile->avatar);
        $this->assertNull($shell->plan);
        $this->assertSame([], $shell->profile->metrics);
    }
}
