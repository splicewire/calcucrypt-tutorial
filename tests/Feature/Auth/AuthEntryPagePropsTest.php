<?php

declare(strict_types=1);

use App\Providers\FortifyServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\ResponseFactory;
use Laravel\Fortify\Contracts\ConfirmPasswordViewResponse;
use Laravel\Fortify\Contracts\LoginViewResponse;
use Laravel\Fortify\Contracts\RegisterViewResponse;
use Laravel\Fortify\Contracts\RequestPasswordResetLinkViewResponse;
use Laravel\Fortify\Contracts\ResetPasswordViewResponse;
use Laravel\Fortify\Contracts\TwoFactorChallengeViewResponse;
use Laravel\Fortify\Contracts\VerifyEmailViewResponse;
use Laravel\Fortify\Features;
use Opis\JsonSchema\Validator;
use Schemastud\DataSchemas\Generators\JsonSchemaGenerator;
use Splicewire\Beam\Accounts\Actions\DemoLoginLinks;
use Splicewire\Beam\Accounts\Data\Pages\AuthEntryPageData;
use Splicewire\Beam\Accounts\Data\Pages\DemoAccountLinkData;
use Splicewire\Beam\Accounts\Data\Pages\ResetPasswordPageData;
use Splicewire\Beam\Storage\StorageDriver;
use Splicewire\Beam\Storage\StorageItem;
use Splicewire\Beam\Ux\Storage\StorageDriverResolver;
use Splicewire\Beam\Ux\Support\EntryBody;

uses(Tests\TestCase::class);

beforeEach(function () {
    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    Schema::create('beam_ux_entries', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('slug');
        $table->string('type');
        $table->string('namespace');
        $table->string('particle_id')->nullable();
        $table->softDeletes();
    });
    config(['beam.ux.namespace' => 'auth-props-test']);
});

dataset('auth entry views', [
    'login' => ['login', LoginViewResponse::class],
    'reset' => ['reset-password', ResetPasswordViewResponse::class],
    'forgot' => ['forgot-password', RequestPasswordResetLinkViewResponse::class],
    /* @chisel-email-verification */
    'verify' => ['verify-email', VerifyEmailViewResponse::class],
    /* @end-chisel-email-verification */
    /* @chisel-registration */
    'register' => ['register', RegisterViewResponse::class],
    /* @end-chisel-registration */
    /* @chisel-2fa */
    '2fa' => ['two-factor-challenge', TwoFactorChallengeViewResponse::class],
    /* @end-chisel-2fa */
    /* @chisel-password-confirmation */
    'confirm' => ['confirm-password', ConfirmPasswordViewResponse::class],
    /* @end-chisel-password-confirmation */
]);

it('declares each Fortify view as whole props with unchanged JSON', function (string $slug, string $contract, bool $seeded) {
    config(['fortify.features' => $seeded ? [Features::resetPasswords()] : []]);
    $body = $seeded ? authPropsBody() : null;
    $entry = $seeded ? ['id' => '019a1111-1111-7111-8111-111111111111', 'slug' => $slug] : null;
    if ($entry !== null) {
        DB::table('beam_ux_entries')->insert($entry + ['type' => 'page', 'namespace' => 'auth-props-test', 'particle_id' => 'auth-particle']);
    }
    $driver = Mockery::mock(StorageDriver::class);
    $drivers = Mockery::mock(StorageDriverResolver::class);
    if ($seeded) {
        $drivers->shouldReceive('resolve')->once()->andReturn($driver);
        $driver->shouldReceive('read')->with('auth-particle')->once()->andReturn(new StorageItem('auth-particle', $body));
    } else {
        $drivers->shouldNotReceive('resolve');
    }
    $demo = $seeded ? [['key' => 'owner', 'label' => 'Demo owner', 'url' => '/users/abc/login-as?expires=1800000000&signature=fixture']] : [];
    $links = Mockery::mock(DemoLoginLinks::class);
    if ($slug === 'login') {
        // Nonsequential keys exercise the provider's existing array_values normalization.
        $links->shouldReceive('all')->once()->andReturn($seeded ? [4 => $demo[0]] : []);
    } else {
        $links->shouldNotReceive('all');
    }
    app()->instance(DemoLoginLinks::class, $links);
    (new FortifyServiceProvider(app()))->boot(new EntryBody($drivers));
    $request = authPropsRequest($seeded);
    $factory = new ResponseFactory;
    Inertia::partialMock()->shouldReceive('render')->once()->andReturnUsing(function ($component, $props) use ($factory, $slug) {
        expect($component)->toBe('auth/entry');
        expect($props)->toBeInstanceOf($slug === 'reset-password' ? ResetPasswordPageData::class : AuthEntryPageData::class);
        if ($slug === 'login') {
            foreach ($props->demoAccounts as $link) {
                expect($link)->toBeInstanceOf(DemoAccountLinkData::class);
            }
        }

        return $factory->render($component, $props);
    });

    $props = app($contract)->toResponse($request)->getData(true)['props'];
    // `entry` carries `format` and `artifact` beside `{id, slug}` (G2-BEAM-AUTHOR-ENTRY). Both null for
    // these fixture rows: no format column value and no compiled artifact, and null is the honest
    // answer for each ("the host did not say" / "never authored").
    $expectedEntry = $entry === null ? null : $entry + ['format' => null, 'artifact' => null];
    $expected = ['slug' => $slug, 'entry' => $expectedEntry, 'body' => $body];
    if ($slug === 'login') {
        $expected += ['canResetPassword' => $seeded, 'status' => $seeded ? 'sent' : null, 'demoAccounts' => $demo];
    } elseif ($slug === 'reset-password') {
        $expected += ['email' => $seeded ? 'person@example.test' : null, 'token' => $seeded ? 'reset-token' : null, 'passwordRules' => Password::defaults()->toPasswordRulesString()];
    } elseif (in_array($slug, ['forgot-password', 'verify-email'], true)) {
        $expected['status'] = $seeded ? 'sent' : null;
    } elseif ($slug === 'register') {
        $expected['passwordRules'] = Password::defaults()->toPasswordRulesString();
    }
    expect($props)->toBe($expected);
    $schema = (new JsonSchemaGenerator)->generate(new ReflectionClass($slug === 'reset-password' ? ResetPasswordPageData::class : AuthEntryPageData::class));
    $document = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
    expect((new Validator)->validate(json_decode(json_encode($props, JSON_THROW_ON_ERROR)), $document)->isValid())->toBeTrue();
})->with('auth entry views')->with(['unseeded' => false, 'seeded' => true]);

it('preserves unvalidated reset email query input', function (mixed $email) {
    $drivers = Mockery::mock(StorageDriverResolver::class);
    $drivers->shouldNotReceive('resolve');
    (new FortifyServiceProvider(app()))->boot(new EntryBody($drivers));
    $request = authPropsRequest(true);
    $request->query->set('email', $email);
    Inertia::swap(new ResponseFactory);
    $props = app(ResetPasswordViewResponse::class)->toResponse($request)->getData(true)['props'];
    expect($props)->toBe(['slug' => 'reset-password', 'entry' => null, 'body' => null, 'email' => $email, 'token' => 'reset-token', 'passwordRules' => Password::defaults()->toPasswordRulesString()]);
    $schema = (new JsonSchemaGenerator)->generate(new ReflectionClass(ResetPasswordPageData::class));
    expect((new Validator)->validate(json_decode(json_encode($props, JSON_THROW_ON_ERROR)), json_decode(json_encode($schema, JSON_THROW_ON_ERROR)))->isValid())->toBeTrue();
})->with(['null' => [null], 'string' => ['person@example.test'], 'list' => [['first', 'second']], 'map' => [['nested' => ['email' => 'person@example.test']]]]);

it('validates nullable JsonDoc lists and declared demo rows without flattening nested content', function () {
    $schema = (new JsonSchemaGenerator)->generate(new ReflectionClass(AuthEntryPageData::class));
    expect($schema['properties']['body']['items'])->toBe(['type' => 'object']);
    expect($schema['properties']['demoAccounts']['items'])->toBe(['$ref' => '#/$defs/DemoAccountLinkData']);
    $validator = new Validator;
    $document = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
    foreach ([null, [], authPropsBody()] as $body) {
        $props = ['slug' => 'login', 'entry' => null, 'body' => $body];
        expect($validator->validate(json_decode(json_encode($props, JSON_THROW_ON_ERROR)), $document)->isValid())->toBeTrue();
    }
    foreach ([['body' => ['bad']], ['body' => (object) ['unexpected' => true]], ['demoAccounts' => ['bad']], ['demoAccounts' => [(object) ['key' => 'owner', 'label' => 'Owner', 'url' => 42]]], ['entry' => (object) ['id' => 42, 'slug' => 'login']]] as $invalid) {
        $props = array_replace(['slug' => 'login', 'entry' => null, 'body' => null], $invalid);
        expect($validator->validate((object) $props, $document)->isValid())->toBeFalse();
    }
});

it('keeps auth partial reloads selective with absent props still absent', function () {
    $drivers = Mockery::mock(StorageDriverResolver::class);
    $drivers->shouldNotReceive('resolve');
    $links = Mockery::mock(DemoLoginLinks::class);
    $links->shouldReceive('all')->once()->andReturn([]);
    app()->instance(DemoLoginLinks::class, $links);
    (new FortifyServiceProvider(app()))->boot(new EntryBody($drivers));
    $request = authPropsRequest(true);
    $request->headers->set('X-Inertia-Partial-Component', 'auth/entry');
    $request->headers->set('X-Inertia-Partial-Data', 'status,email');
    $evaluations = 0;
    $factory = new ResponseFactory;
    $factory->share('unrelated', function () use (&$evaluations) {
        $evaluations++;

        return 'unused';
    });
    Inertia::swap($factory);
    expect(app(LoginViewResponse::class)->toResponse($request)->getData(true)['props'])->toBe(['status' => 'sent']);
    expect($evaluations)->toBe(0);
});

function authPropsRequest(bool $populated): Request
{
    $request = Request::create('/reset-password/reset-token', 'GET', $populated ? ['email' => 'person@example.test'] : [], server: ['HTTP_X_INERTIA' => 'true']);
    if ($populated) {
        $route = new Route('GET', 'reset-password/{token}', fn () => null);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
    }
    $session = new Store('auth-props-test', new ArraySessionHandler(120));
    $session->put('status', $populated ? 'sent' : null);
    $request->setLaravelSession($session);

    return $request;
}

function authPropsBody(): array
{
    return [['kind' => 'block', 'name' => 'AuthLogin', 'isComponent' => true, 'dynamic' => false, 'props' => [['name' => 'options', 'kind' => 'expression', 'value' => ['nested' => ['enabled' => false, 'count' => 0, 'label' => null]]]], 'children' => [['kind' => 'text', 'value' => 'Sign in']]]];
}
