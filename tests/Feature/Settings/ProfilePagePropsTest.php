<?php

declare(strict_types=1);

use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Auth\MustVerifyEmail as VerifiesEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\ResponseFactory;
use Splicewire\Beam\Accounts\Data\Pages\ProfilePageData;

uses(Tests\TestCase::class);

beforeEach(function () {
    // This controller only reads the entry reference. Keep the fixture at that seam;
    // no estate migrations, tenancy or shared database are needed.
    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    Schema::create('beam_ux_entries', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('slug');
        $table->string('type');
        $table->string('namespace');
        $table->softDeletes();
    });
    config(['beam.ux.namespace' => 'profile-props-test']);
});

it('declares the complete profile props while preserving the rendered payload', function (bool $hasEntry, bool $verifiesEmail, ?string $status) {
    $entry = $hasEntry ? ['id' => '019a1111-1111-7111-8111-111111111111', 'slug' => 'settings-profile'] : null;
    if ($entry !== null) {
        DB::table('beam_ux_entries')->insert($entry + ['type' => 'page', 'namespace' => 'profile-props-test']);
    }
    $request = Request::create('/settings/profile', 'GET', server: ['HTTP_X_INERTIA' => 'true']);
    $request->setUserResolver(fn () => $verifiesEmail
        ? new class extends User implements MustVerifyEmail
        {
            use VerifiesEmail;
        }
        : new User);
    $session = new Store('profile-props-test', new ArraySessionHandler(120));
    $session->put('status', $status);
    $request->setLaravelSession($session);

    $factory = new ResponseFactory;
    Inertia::partialMock()->shouldReceive('render')->once()->andReturnUsing(function ($component, $props) use ($factory) {
        expect($component)->toBe('settings/profile');
        // Removing the Data construction must fail even if its serialized array looks identical.
        expect($props)->toBeInstanceOf(ProfilePageData::class);

        return $factory->render($component, $props);
    });

    $response = (new ProfileController)->edit($request)->toResponse($request);

    // `entry` carries `format` and `artifact` beside `{id, slug}` (G2-BEAM-AUTHOR-ENTRY); both null for
    // this fixture row, and null is the honest answer for each.
    expect($response->getData(true)['props'])->toBe([
        'entry' => $entry === null ? null : $entry + ['format' => null, 'artifact' => null],
        'mustVerifyEmail' => $verifiesEmail,
        'status' => $status,
    ]);
})->with([
    'unseeded, no verification or status' => [false, false, null],
    'seeded, verification and status' => [true, true, 'verification-link-sent'],
]);

it('keeps partial reloads selective without evaluating unrelated shared props', function () {
    $request = Request::create('/settings/profile', 'GET', server: [
        'HTTP_X_INERTIA' => 'true',
        'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'settings/profile',
        'HTTP_X_INERTIA_PARTIAL_DATA' => 'status',
    ]);
    $request->setUserResolver(fn () => new User);
    $session = new Store('profile-props-test', new ArraySessionHandler(120));
    $session->put('status', 'verification-link-sent');
    $request->setLaravelSession($session);
    $evaluations = 0;
    $factory = new ResponseFactory;
    $factory->share('unrelated', function () use (&$evaluations) {
        $evaluations++;

        return 'should not be read';
    });
    Inertia::swap($factory);

    $response = (new ProfileController)->edit($request)->toResponse($request);

    expect($response->getData(true)['props'])->toBe(['status' => 'verification-link-sent']);
    expect($evaluations)->toBe(0);
});
