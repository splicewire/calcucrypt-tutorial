<?php

require getenv('LARAVEL_INSTALLER_AUTOLOADER') ?: __DIR__.'/vendor/autoload.php';

use Laravel\Chisel\Chisel;
use Laravel\Chisel\Question;
use Laravel\Prompts\Support\Logger;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\task;

function chiselRun(array $command, string $label): void
{
    $process = task(
        label: $label,
        keepSummary: true,
        callback: function (Logger $logger) use ($command) {
            $process = new Process($command);
            $process->run(function ($type, $line) use ($logger) {
                $logger->line($line);
            });

            if ($process->isSuccessful()) {
                $logger->success(implode(' ', $command));

                return $process;
            }

            $logger->error(implode(' ', $command));
            $logger->error('Error output: '.trim($process->getErrorOutput()));
            $logger->error('Chisel: Your project may be in a partially-modified state — review the output above before continuing.');

            return $process;
        },
    );

    if (! $process->isSuccessful()) {
        exit($process->getExitCode());
    }
}

function chiselSkipsNode(): bool
{
    return filter_var(
        $_ENV['LARAVEL_INSTALLER_NO_NODE']
            ?? $_SERVER['LARAVEL_INSTALLER_NO_NODE']
            ?? getenv('LARAVEL_INSTALLER_NO_NODE'),
        FILTER_VALIDATE_BOOL,
    );
}

function chiselRemoveNpmPackages(Chisel $c, string ...$packages): void
{
    if (! chiselSkipsNode()) {
        $c->npm()->remove(...$packages);

        return;
    }

    foreach ($packages as $package) {
        $c->file('package.json')->removeLinesContaining('"'.$package.'":');
    }
}

// Frontend defaults live in @splicewire/beam-inertia. Chisel cuts only host feature configuration.

return Chisel::script(__DIR__)
    ->questions([
        Question::multiselect(
            name: 'auth_features',
            label: 'Which authentication features would you like to enable?',
            options: [
                'email-verification' => 'Email verification',
                'registration' => 'Registration',
                '2fa' => 'Two-factor authentication',
                'passkeys' => 'Passkeys',
                'password-confirmation' => 'Password confirmation',
            ],
            default: ['email-verification', 'registration', '2fa', 'passkeys', 'password-confirmation'],
            hint: 'Use space to select, enter to confirm.',
        ),
    ])
    ->selected(
        'auth_features',
        'registration',
        then: function (Chisel $c) {
            $c->files(
                'config/fortify.php',
                'app/Providers/FortifyServiceProvider.php',
                'resources/js/beam.ts',
                'tests/Feature/Auth/AuthEntryPagePropsTest.php',
            )->removeSectionMarkers('registration');
        },
        else: function (Chisel $c) {
            $c->file('config/fortify.php')->removeSection('registration');

            $c->files(
                'app/Providers/FortifyServiceProvider.php',
                'resources/js/beam.ts',
                'tests/Feature/Auth/AuthEntryPagePropsTest.php',
            )->removeSection('registration');

            $c->files(
                'app/Actions/Fortify/CreateNewUser.php',
                'app/Http/Responses/RegisterResponse.php',
                'tests/Feature/Auth/RegistrationTest.php',
            )->delete();
        },
    )
    ->selected(
        'auth_features',
        'email-verification',
        then: function (Chisel $c) {
            $c->files(
                'config/fortify.php',
                'app/Providers/FortifyServiceProvider.php',
                'resources/js/beam.ts',
                'tests/Feature/Auth/AuthEntryPagePropsTest.php',
            )->removeSectionMarkers('email-verification');
        },
        else: function (Chisel $c) {
            $c->php('app/Models/User.php')
                ->removeImport('Illuminate\Contracts\Auth\MustVerifyEmail')
                ->removeInterface('MustVerifyEmail');

            $c->files(
                'config/fortify.php',
                'app/Providers/FortifyServiceProvider.php',
                'resources/js/beam.ts',
                'tests/Feature/Auth/AuthEntryPagePropsTest.php',
            )->removeSection('email-verification');

            $c->files(
                'app/Http/Responses/VerifyEmailResponse.php',
                'tests/Feature/Auth/EmailVerificationTest.php',
                'tests/Feature/Auth/VerificationNotificationTest.php',
            )->delete();
        },
    )
    ->selected(
        'auth_features',
        '2fa',
        then: function (Chisel $c) {
            $c->files(
                'app/Models/User.php',
                'database/factories/UserFactory.php',
                'config/fortify.php',
                'app/Providers/FortifyServiceProvider.php',
                'resources/js/beam.ts',
                'tests/Feature/Auth/AuthEntryPagePropsTest.php',
                'app/Http/Controllers/Settings/SecurityController.php',
            )->removeSectionMarkers('2fa');
        },
        else: function (Chisel $c) {
            $c->php('app/Models/User.php')
                ->removeImport('Laravel\Fortify\TwoFactorAuthenticatable')
                ->removeTrait('TwoFactorAuthenticatable');

            $c->files(
                'app/Models/User.php',
                'database/factories/UserFactory.php',
                'config/fortify.php',
                'app/Providers/FortifyServiceProvider.php',
                'resources/js/beam.ts',
                'tests/Feature/Auth/AuthEntryPagePropsTest.php',
                'app/Http/Controllers/Settings/SecurityController.php',
            )->removeSection('2fa');

            $c->files(...[
                'database/migrations/2025_08_14_170933_add_two_factor_columns_to_users_table.php',
                'tests/Feature/Auth/TwoFactorChallengeTest.php',
                'tests/Feature/Settings/SecurityPagePropsTest.php',
            ])->delete();
        },
    )
    ->selected(
        'auth_features',
        'passkeys',
        then: function (Chisel $c) {
            $c->files(
                'config/fortify.php',
                'app/Providers/FortifyServiceProvider.php',
                'app/Http/Controllers/Settings/SecurityController.php',
                'routes/settings.php',
                'resources/js/beam.ts',
                'tests/Feature/Auth/AuthenticationTest.php',
                'tests/Feature/Settings/SecurityTest.php',
            )->removeSectionMarkers('passkeys');
        },
        else: function (Chisel $c) {
            $c->php('app/Models/User.php')
                ->removeImport('Laravel\Fortify\PasskeyAuthenticatable')
                ->removeImport('Laravel\Fortify\Contracts\PasskeyUser')
                ->removeTrait('PasskeyAuthenticatable')
                ->removeInterface('PasskeyUser');

            $c->files(
                'config/fortify.php',
                'app/Providers/FortifyServiceProvider.php',
                'app/Http/Controllers/Settings/SecurityController.php',
                'routes/settings.php',
                'resources/js/beam.ts',
                'tests/Feature/Auth/AuthenticationTest.php',
                'tests/Feature/Settings/SecurityTest.php',
            )->removeSection('passkeys');

            // The adapter owns this optional UI dependency; disabling it removes affordances, not shared package files.

            $c->files(...[
                'app/Http/Responses/PasskeyLoginResponse.php',
                'tests/Feature/Settings/SecurityPagePropsTest.php',
                'database/migrations/2024_01_01_000000_create_passkeys_table.php',
            ])->delete();
        },
    )
    ->selectedAny(
        'auth_features',
        ['2fa', 'passkeys'],
        then: function (Chisel $c) {
            $c->file('resources/js/beam.ts')
                ->removeSectionMarkers('2fa-or-passkeys');
        },
        else: function (Chisel $c) {
            $c->file('resources/js/beam.ts')
                ->removeSection('2fa-or-passkeys');
        },
    )
    ->selected(
        'auth_features',
        'password-confirmation',
        then: function (Chisel $c) {
            $c->files(
                'app/Providers/FortifyServiceProvider.php',
                'resources/js/beam.ts',
                'tests/Feature/Auth/AuthEntryPagePropsTest.php',
                'routes/settings.php',
                'tests/Feature/Settings/SecurityTest.php',
            )->removeSectionMarkers('password-confirmation');
        },
        else: function (Chisel $c) {
            $c->file('config/fortify.php')
                ->replace("'confirmPassword' => true,", "'confirmPassword' => false,");

            $c->files(
                'app/Providers/FortifyServiceProvider.php',
                'resources/js/beam.ts',
                'tests/Feature/Auth/AuthEntryPagePropsTest.php',
                'routes/settings.php',
                'tests/Feature/Settings/SecurityTest.php',
            )->removeSection('password-confirmation');

            $c->files(
                'tests/Feature/Auth/PasswordConfirmationTest.php',
            )->delete();
        },
    )
    ->apply(function (Chisel $c): void {
        $c->file('eslint.config.js')->replace(
            "// alphabetize: { order: 'asc', caseInsensitive: true },",
            "alphabetize: { order: 'asc', caseInsensitive: true },",
        );

        chiselRun(['composer', 'lint'], 'Composer Lint');

        if (! chiselSkipsNode()) {
            $c->npm()->run('lint');
            $c->npm()->run('format');
        }

        $c->file('composer.json')
            ->removeLinesContaining('"@php artisan install:features --ansi"');

        $c->files(
            'app/Console/Commands/InstallFeaturesCommand.php',
            'chisel.php',
            'chisel-paths.php',
        )->delete();
    });
