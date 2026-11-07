<?php

// tutorial-dx 05: the starter ships discovery and the session. With `beam.core.particle.discover_paths` empty, a
// #[ParticleOp] dropped into app/Data was never registered, so the tutorial's `->ops(['resolve'])` mounted a route
// whose request 500'd ("No particle operation [resolve] on resource [calculators]"). Without `statefulApi()`, the
// `api` group ran no session middleware, so a signed-in browser was a stranger to every `auth:sanctum` route.

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\EncryptedStore;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Splicewire\Beam\Particle\ParticleOperationRegistry;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('registers a #[ParticleOp] declared under app/Data at boot, so a bare ->ops([name]) resolves', function () {
    $class = 'Tdx05ProbeOperation'.bin2hex(random_bytes(4));
    $file = app_path("Data/{$class}.php");
    file_put_contents($file, <<<PHP
        <?php

        namespace App\\Data;

        use Splicewire\\Beam\\Particle\\Attributes\\ParticleOp;
        use Splicewire\\Beam\\Particle\\OperationKind;

        #[ParticleOp(resource: 'tdx05-probes', name: 'resolve', kind: OperationKind::Task, ability: false)]
        class {$class}
        {
            public static function handle(): mixed
            {
                return null;
            }
        }
        PHP);

    try {
        $this->refreshApplication(); // discovery runs at boot, so the class must exist before it

        expect(app(ParticleOperationRegistry::class)->has('tdx05-probes', 'resolve'))->toBeTrue();
    } finally {
        unlink($file);
    }
});

/**
 * A browser session for `$user`, written through the session HANDLER by a separate store, so the app's own session
 * store stays empty: only StartSession, reading the cookie, can load it. Built as the app's session config builds its
 * own store (this host serializes sessions as json), or the app could not read it back.
 */
function browserSessionFor(User $user): string
{
    $handler = app('session')->driver()->getHandler();
    $serialization = config('session.serialization', 'php');
    $store = config('session.encrypt')
        ? new EncryptedStore(config('session.cookie'), $handler, app('encrypter'), serialization: $serialization)
        : new Store(config('session.cookie'), $handler, serialization: $serialization);
    $store->start();
    $store->put(Auth::guard('web')->getName(), $user->getAuthIdentifier());
    $store->save();

    return $store->getId();
}

it('lets a signed-in first-party browser reach an auth:sanctum api route with its session and no token', function () {
    $user = User::factory()->create();

    // withCredentials(): a JSON test request sends cookies only when asked, as a browser fetch does.
    $this->withCredentials()
        ->withCookie(config('session.cookie'), browserSessionFor($user))
        ->withHeader('Referer', config('app.url').'/dashboard')
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});

it('still refuses the same session cookie from a request that is not first-party (no stateful Referer or Origin)', function () {
    $user = User::factory()->create();

    // withCredentials(): a JSON test request sends cookies only when asked, as a browser fetch does.
    $this->withCredentials()
        ->withCookie(config('session.cookie'), browserSessionFor($user))
        ->getJson('/api/user')
        ->assertUnauthorized();
});

it('still refuses a request with neither a session nor a token', function () {
    $this->withHeader('Referer', config('app.url').'/dashboard')
        ->getJson('/api/user')
        ->assertUnauthorized();
});
