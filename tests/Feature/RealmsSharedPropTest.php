<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * ux-walkthrough UX-05 (M2): every page shares the host's IA as `realms` (laravel-beam `HostIa`, HostRealmsData), so
 * a renderer reads realm labels, homes, the current realm and Back from one payload and derives none of them.
 */
class RealmsSharedPropTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_signed_in_member_on_the_dashboard_is_in_the_app_realm(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('realms.current', 'tenant')
            ->where('realms.back', null)
            ->where('realms.realms', fn ($realms) => collect($realms)->contains(
                fn ($r) => $r['key'] === 'tenant' && $r['label'] === 'App' && $r['href'] === route('dashboard', [], false)
            ))
        );
    }

    public function test_a_guest_on_the_home_page_is_on_the_site(): void
    {
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('realms.current', 'site')
            ->where('realms.back', null)
        );
    }
}
