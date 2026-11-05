<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\ResponseFactory;
use Splicewire\Beam\Ux\Data\Pages\EntryPageData;
use Splicewire\Beam\Ux\Head\PageHead;

uses(Tests\TestCase::class);

beforeEach(function () {
    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    Schema::create('beam_ux_entries', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('slug');
        $table->string('type');
        $table->string('namespace');
        $table->softDeletes();
    });
    Schema::create('users', function (Blueprint $table) {
        $table->id();
    });
    DB::table('users')->insert([['id' => 1], ['id' => 2], ['id' => 3]]);
    DB::table('beam_ux_entries')->insert([
        ['id' => 'realm-one', 'slug' => 'site', 'type' => 'page', 'namespace' => 'realms'],
        ['id' => 'realm-two', 'slug' => 'account', 'type' => 'page', 'namespace' => 'realms'],
    ]);
    config(['beam.ux.namespace' => 'route-props-test']);
});

function routePropsRequest(string $component, ?string $only, int &$staffEvaluations): Request
{
    $headers = ['HTTP_X_INERTIA' => 'true'];
    if ($only !== null) {
        $headers += ['HTTP_X_INERTIA_PARTIAL_COMPONENT' => $component, 'HTTP_X_INERTIA_PARTIAL_DATA' => $only];
    }
    $request = Request::create('/route-props-test', 'GET', server: $headers);
    app()->instance('request', $request);
    $request->setUserResolver(function () use (&$staffEvaluations) {
        $staffEvaluations++;

        return (new User)->forceFill(['name' => 'Operator', 'email' => 'operator@example.test']);
    });
    $request->setLaravelSession(new Store('route-props-test', new ArraySessionHandler(120)));

    return $request;
}

it('declares each route page and preserves initial populated and missing entry payloads', function (string $route, string $component, string $slug, string $dataClass, bool $seeded) {
    $entry = $seeded ? ['id' => 'page-entry', 'slug' => $slug] : null;
    if ($entry !== null) {
        DB::table('beam_ux_entries')->insert($entry + ['type' => 'page', 'namespace' => 'route-props-test']);
    }
    $staffEvaluations = 0;
    $request = routePropsRequest($component, null, $staffEvaluations);
    $factory = new ResponseFactory;
    Inertia::partialMock()->shouldReceive('render')->once()->andReturnUsing(function ($actualComponent, $props) use ($component, $dataClass, $factory) {
        expect($actualComponent)->toBe($component)->and($props)->toBeInstanceOf($dataClass);

        return $factory->render($actualComponent, $props);
    });
    $counts = 0;
    DB::listen(function ($query) use (&$counts) {
        if (str_contains($query->sql, 'count(*)')) {
            $counts++;
        }
    });
    $response = Route::getRoutes()->getByName($route)->bind($request)->run();
    expect($staffEvaluations)->toBe(0)->and($counts)->toBe(0);
    // The entry ref carries `format` and `artifact` beside `{id, slug}` (G2-BEAM-AUTHOR-ENTRY): a save
    // address alone left `/` unreadable and let the canvas open an mdx entry. Both are null for these
    // rows — the fixture inserts no format and compiles no artifact — and null is the honest answer
    // ("the host did not say" / "never authored"), which is exactly what the client must receive.
    $expected = ['entry' => $entry === null ? null : $entry + ['format' => null, 'artifact' => null]];
    $props = $response->toResponse($request)->getData(true)['props'];
    expect(Arr::except($props, 'head'))->toBe($expected);
    // EP-28 (ADR-0234 d2): `/` also passes the Page Head composed from its home row, and none where no row exists, so
    // the root template keeps its fallback title.
    expect($props['head'])->toBe($entry === null ? [] : PageHead::forPage('home', $request));
    if ($entry !== null) {
        expect($props['head'][0])->toStartWith('<title data-inertia="title">');
    }
    // No page carries a lazy prop or a count any more: `/dashboard` and `/operator` are the realm
    // dashboards' frame-console leaves (realm-dashboards ticket 05) and share no entry, and the counts
    // the operator landing once computed inline now come from each resource's summary provider over
    // `GET /frame/resources/{realm}-dashboard` — see RealmDashboardTest.
    expect($staffEvaluations)->toBe(0);
    expect($counts)->toBe(0);
})->with([
    'site' => ['home', 'site/home', 'home', EntryPageData::class],
])->with(['unseeded' => false, 'seeded' => true]);
