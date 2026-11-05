<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ux-walkthrough UX-03b (integrator 07:45Z): this starter's shell showed "Laravel" and the Laravel starter kit's logo,
 * because the brand fell back to `config('app.name')` and live `.env` files say APP_NAME=Laravel. The brand is
 * declared in committed config (`config/beam/brand.php`, env optional), so an `APP_NAME=Laravel` deployment still
 * shows its own name, in the shared `brand` prop and in the server-rendered `<title>`.
 */
class ShellBrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shell_carries_the_declared_brand_even_with_app_name_laravel(): void
    {
        config(['app.name' => 'Laravel']);

        $response = $this->get('/login');

        $response->assertOk();
        $this->assertSame('Beam', $response->viewData('page')['props']['brand']['name'] ?? null);
        $response->assertSee('<title>Beam</title>', false);
        $response->assertDontSee('<title>Laravel</title>', false);
    }
}
