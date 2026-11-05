<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * The registration door this test class boots with (purchase-walkthrough BUY-05), or null for the host's own
     * declaration. The door mounts its routes at boot, so it is declared in config the moment configuration loads.
     * Not through the env: once a host's .env has been loaded in this process, Laravel's env repository reloads it
     * over any value a test sets, so a host whose .env says ACCOUNT_REGISTRATION=open would ignore 'closed'.
     */
    protected ?string $accountRegistration = null;

    public function createApplication()
    {
        if ($this->accountRegistration === null) {
            return parent::createApplication();
        }

        $app = require Application::inferBasePath().'/bootstrap/app.php';
        $this->traitsUsedByTest = class_uses_recursive(static::class);

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            $app['config']->set('beam.accounts.doors.registration', $this->accountRegistration);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
