<?php

declare(strict_types=1);

use App\Http\Controllers\Settings\SecurityController;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\ResponseFactory;
use Laravel\Chisel\Filesystem\File;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;
use Opis\JsonSchema\Validator;
use Schemastud\DataSchemas\Generators\JsonSchemaGenerator;
use Splicewire\Beam\Accounts\Data\Pages\SecurityPageData;
use Splicewire\Beam\Accounts\Data\Pages\SecurityPasskeyData;

uses(Tests\TestCase::class);

beforeEach(function () {
    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    Schema::create('passkeys', function (Blueprint $table) {
        $table->id();
        $table->integer('user_id');
        $table->string('name');
        $table->json('credential');
        $table->timestamp('created_at');
        $table->timestamp('last_used_at')->nullable();
    });
    $this->travelTo(now()->setDate(2026, 9, 8)->startOfDay());
});

it('declares the whole security body and preserves feature flags and passkey rows', function (bool $twoFactor, bool $passkeys, bool $enabled, bool $confirm) {
    config(['fortify.features' => array_values(array_filter([
        $twoFactor ? Features::twoFactorAuthentication(['confirm' => $confirm]) : null,
        $passkeys ? Features::passkeys() : null,
    ]))]);
    DB::table('passkeys')->insert([
        ['id' => 7, 'user_id' => 1, 'name' => 'Older', 'credential' => '{}', 'created_at' => now()->subDays(2), 'last_used_at' => null],
        ['id' => 9, 'user_id' => 1, 'name' => 'Newer', 'credential' => '{"aaguid":"bada5566-a7aa-401f-bd96-45619a55120d"}', 'created_at' => now()->subDay(), 'last_used_at' => now()->subHour()],
        ['id' => 10, 'user_id' => 2, 'name' => 'Other user', 'credential' => '{}', 'created_at' => now(), 'last_used_at' => null],
    ]);
    $request = securityPropsRequest($enabled);
    $factory = new ResponseFactory;
    Inertia::partialMock()->shouldReceive('render')->once()->andReturnUsing(function ($component, $props) use ($factory) {
        expect($component)->toBe('settings/security');
        expect($props)->toBeInstanceOf(SecurityPageData::class);
        foreach ($props->passkeys as $passkey) {
            expect($passkey)->toBeInstanceOf(SecurityPasskeyData::class);
        }

        return $factory->render($component, $props);
    });

    $props = (new SecurityController)->edit($request)->toResponse($request)->getData(true)['props'];
    $expected = [
        'canManageTwoFactor' => $twoFactor,
        'canManagePasskeys' => $passkeys,
        'passkeys' => $passkeys ? [
            ['id' => 9, 'name' => 'Newer', 'authenticator' => '1Password', 'createdAtDiff' => '1 day ago', 'lastUsedAtDiff' => '1 hour ago'],
            ['id' => 7, 'name' => 'Older', 'authenticator' => null, 'createdAtDiff' => '2 days ago', 'lastUsedAtDiff' => null],
        ] : [],
        'passwordRules' => Password::defaults()->toPasswordRulesString(),
    ];
    if ($twoFactor) {
        $expected['twoFactorEnabled'] = $enabled;
        $expected['requiresConfirmation'] = $confirm;
    }
    expect($props)->toBe($expected);
    expect($request->stateChecks)->toBe($twoFactor ? 1 : 0);
})->with([
    'features disabled' => [false, false, false, true],
    '2fa only, not enrolled' => [true, false, false, true],
    'passkeys only' => [false, true, false, true],
    'both, enrolled' => [true, true, true, true],
    'both, confirmation disabled' => [true, true, false, false],
]);

it('projects passkeys as declared rows and rejects malformed items', function () {
    $schema = (new JsonSchemaGenerator)->generate(new ReflectionClass(SecurityPageData::class));
    expect($schema['properties']['passkeys']['items'])->toBe(['$ref' => '#/$defs/SecurityPasskeyData']);
    $document = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
    $validator = new Validator;
    $props = ['canManageTwoFactor' => false, 'canManagePasskeys' => true, 'passwordRules' => 'minlength: 8;', 'passkeys' => []];
    expect($validator->validate((object) $props, $document)->isValid())->toBeTrue();
    $row = (object) ['id' => 9, 'name' => 'Key', 'authenticator' => null, 'createdAtDiff' => '1 day ago', 'lastUsedAtDiff' => null];
    $props['passkeys'] = [$row];
    expect($validator->validate((object) $props, $document)->isValid())->toBeTrue();
    $props['passkeys'] = ['bad'];
    expect($validator->validate((object) $props, $document)->isValid())->toBeFalse();
    $row->id = 'bad';
    $props['passkeys'] = [$row];
    expect($validator->validate((object) $props, $document)->isValid())->toBeFalse();
});

it('keeps security partial reloads selective', function () {
    config(['fortify.features' => []]);
    $request = securityPropsRequest(false, true);
    $factory = new ResponseFactory;
    $evaluations = 0;
    $factory->share('unrelated', function () use (&$evaluations) {
        $evaluations++;

        return 'unused';
    });
    Inertia::swap($factory);

    $props = (new SecurityController)->edit($request)->toResponse($request)->getData(true)['props'];

    expect($props)->toBe(['passwordRules' => Password::defaults()->toPasswordRulesString()]);
    expect($evaluations)->toBe(0);
});

it('preserves package defaults after actual Chisel feature removal', function (array $removed) {
    config(['fortify.features' => [Features::twoFactorAuthentication(['confirm' => true]), Features::passkeys()]]);
    if (! str_contains(file_get_contents(app_path('Http/Controllers/Settings/SecurityController.php')), '/* @chisel-')) {
        $this->markTestSkipped('Scaffold markers were already consumed by Chisel.');
    }
    $directory = sys_get_temp_dir().'/security-chisel-'.bin2hex(random_bytes(6));
    mkdir($directory);
    $name = 'ChiselledSecurity'.bin2hex(random_bytes(6));
    $source = str_replace('class SecurityController ', 'class '.$name.' ', file_get_contents(app_path('Http/Controllers/Settings/SecurityController.php')));
    file_put_contents($directory.'/Controller.php', $source);
    try {
        $files = new File($directory);
        foreach (['2fa', 'passkeys'] as $feature) {
            foreach (['Controller.php'] as $file) {
                if (in_array($feature, $removed, true)) {
                    $files->removeSection($file, $feature);
                } else {
                    $files->removeSectionMarkers($file, $feature);
                }
            }
        }
        require $directory.'/Controller.php';
        $class = 'App\\Http\\Controllers\\Settings\\'.$name;
        Inertia::swap(new ResponseFactory);
        $request = securityPropsRequest(false);
        $props = (new $class)->edit($request)->toResponse($request)->getData(true)['props'];
        expect($props)->toHaveKey('passwordRules');
        if (in_array('2fa', $removed, true)) {
            expect($props['canManageTwoFactor'])->toBeFalse();
            expect($props)->not->toHaveKeys(['twoFactorEnabled', 'requiresConfirmation']);
            expect($request->stateChecks)->toBe(0);
        } else {
            expect($props['canManageTwoFactor'])->toBeTrue();
            expect($props['twoFactorEnabled'])->toBeFalse();
        }
        if (in_array('passkeys', $removed, true)) {
            expect($props['canManagePasskeys'])->toBeFalse();
            expect($props['passkeys'])->toBe([]);
        } else {
            expect($props['canManagePasskeys'])->toBeTrue();
            expect($props['passkeys'])->toBe([]);
        }
    } finally {
        unlink($directory.'/Controller.php');
        rmdir($directory);
    }
})->with([
    'keep both' => [[]],
    'remove 2fa' => [['2fa']],
    'remove passkeys' => [['passkeys']],
    'remove both' => [['2fa', 'passkeys']],
]);

function securityPropsRequest(bool $enabled, bool $partial = false): SecurityPropsRequest
{
    $request = SecurityPropsRequest::create('/settings/security', 'GET', server: [
        'HTTP_X_INERTIA' => 'true',
        ...($partial ? ['HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'settings/security', 'HTTP_X_INERTIA_PARTIAL_DATA' => 'passwordRules'] : []),
    ]);
    $user = new SecurityPropsUser;
    $user->id = 1;
    $user->enrolled = $enabled;
    $request->setUserResolver(fn () => $user);

    return $request;
}

class SecurityPropsRequest extends TwoFactorAuthenticationRequest
{
    public int $stateChecks = 0;

    public function ensureStateIsValid(): void
    {
        $this->stateChecks++;
    }
}

class SecurityPropsUser extends User
{
    public bool $enrolled = false;

    public function hasEnabledTwoFactorAuthentication(): bool
    {
        return $this->enrolled;
    }

    public function passkeys(): HasMany
    {
        return $this->hasMany(Passkey::class, 'user_id');
    }
}
