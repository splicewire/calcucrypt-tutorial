<?php

declare(strict_types=1);

namespace CalcucryptParity;

use App\Calculators\Expression\ExpressionEvaluator;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Rushing\Commerce\Drivers\FakeDriver;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Throwable;

/**
 * Drives one app through the tutorial's behaviour and records what it answers. Uses Laravel's own
 * testing kernel (RefreshDatabase on sqlite :memory:), so a request here is the same request a Pest
 * test makes.
 */
final class ParityProbe extends TestCase
{
    use RefreshDatabase;

    public const SLUGS = ['crypto-profit', 'crypto-converter', 'crypto-position-size', 'crypto-capital-gains'];

    public const GUIDES = ['how-crypto-profit-works', 'crypto-taxes-end-to-end'];

    public const KEYS = ['calculators.crypto-position-size', 'calculators.crypto-capital-gains', 'plans.purchase'];

    /** The second request per calculator: one ref-steering input changed. */
    private const VARIANTS = [
        'crypto-profit' => ['asset' => 'ETH'],
        'crypto-converter' => ['fromCoin' => 'SOL', 'toCoin' => 'BTC'],
        'crypto-position-size' => ['asset' => 'SOL'],
        'crypto-capital-gains' => [],
    ];

    public static string $root;

    public static string $golden;

    public function createApplication()
    {
        $app = require self::$root.'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /** @return array<string, mixed> */
    public function fingerprint(): array
    {
        $this->setUp();

        try {
            $this->withoutVite();
            config(['facts.connectors.coinmarketcap' => ProbeConnector::class]);
            $this->artisan('content:sync')->assertSuccessful();

            $out = [
                'catalogue' => $this->catalogue(),
                'actors' => [],
                'malformed' => $this->malformed(),
                'purchase' => $this->purchase(),
                'golden' => $this->golden(),
            ];

            foreach ($this->actors() as $name => $user) {
                $out['actors'][$name] = $this->asActor($user);
            }

            return self::canonical($out);
        } finally {
            FakeDriver::clearFakes();
            // Laravel's teardown asks PHPUnit's runner for its error handler, which exists only under a
            // runner. The process exits right after the fingerprint, so a failing teardown loses nothing.
            try {
                $this->tearDown();
            } catch (Throwable) {
            }
        }
    }

    /** @return array<string, ?User> guest, a free Member, a trader Member, a pro Owner */
    private function actors(): array
    {
        return [
            'guest' => null,
            'free-member' => $this->member('free'),
            'trader-member' => $this->member('trader'),
            'pro-owner' => $this->owner('pro'),
        ];
    }

    private function owner(string $plan): User
    {
        $owner = User::factory()->create();
        app(TeamProvisioner::class)->createTeamFor($owner, ucfirst($plan).' team')->forceFill(['plan' => $plan])->save();

        return $owner->refresh();
    }

    private function member(string $plan): User
    {
        $team = $this->owner($plan)->currentTeamOrPersonal();
        $member = User::factory()->create();
        app(TeamProvisioner::class)->addMember($member, $team, Role::Member);
        $member->switchTeam($team);

        return $member->refresh();
    }

    private function actor(?User $user): static
    {
        $this->app['auth']->forgetGuards();
        if ($user !== null) {
            $this->actingAs($user->fresh());
        }

        return $this;
    }

    /** @return array<string, mixed> */
    private function catalogue(): array
    {
        $rows = [];
        foreach (self::SLUGS as $slug) {
            $calculator = \App\Models\Calculator::query()->where('slug', $slug)->first();
            $rows[$slug] = $calculator === null ? null : [
                'name' => $calculator->name,
                'computation' => $calculator->computation,
                'entitlement' => $calculator->getAttribute('entitlement'),
                'graph' => sha1(json_encode($calculator->graph, JSON_THROW_ON_ERROR)),
            ];
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function asActor(?User $user): array
    {
        $out = ['index' => [], 'show' => [], 'resolve' => [], 'page' => [], 'guides' => [], 'can' => []];

        $index = $this->actor($user)->getJson('/api/calculators');
        foreach ($index->json('data') ?? [] as $row) {
            if (in_array($row['slug'], self::SLUGS, true)) {
                $out['index'][$row['slug']] = self::pick($row, ['locked', 'requiredPlan']);
            }
        }

        foreach (self::SLUGS as $slug) {
            $show = $this->actor($user)->getJson("/api/calculators/{$slug}");
            $out['show'][$slug] = [
                'status' => $show->status(),
                'body' => self::pick((array) $show->json('data'), ['slug', 'name', 'computation', 'category', 'locked', 'requiredPlan', 'graph']),
                'hasNodes' => str_contains((string) $show->getContent(), '"nodes"'),
            ];

            foreach (['default' => [], 'variant' => self::VARIANTS[$slug]] as $label => $inputs) {
                $out['resolve'][$slug][$label] = self::answer($this->actor($user)->postJson("/api/calculators/{$slug}/resolve", ['inputs' => $inputs]));
            }

            $page = $this->actor($user)->get("/calculators/{$slug}");
            $props = self::props($page);
            $out['page'][$slug] = [
                'status' => $page->status(),
                'component' => $props['component'] ?? null,
                'locked' => $props['props']['spec']['locked'] ?? null,
                'graph' => isset($props['props']['spec']['graph']),
                'resolution' => $props['props']['resolution'] ?? null,
            ];
        }

        foreach (self::GUIDES as $slug) {
            $page = self::props($this->actor($user)->get("/guides/{$slug}"));
            $out['guides'][$slug] = [
                'embeds' => array_map(fn (array $embed): array => [
                    'anchor' => $embed['anchor'] ?? null,
                    'slug' => $embed['spec']['slug'] ?? null,
                    'locked' => $embed['spec']['locked'] ?? null,
                    'resolution' => $embed['resolution'] ?? null,
                ], $page['props']['embeds'] ?? []),
            ];
        }

        foreach (self::KEYS as $key) {
            $out['can'][$key] = Gate::forUser($user)->allows('entitlement:'.$key);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function malformed(): array
    {
        $out = [];
        foreach ([
            'not a map' => ['inputs' => 'ETH'],
            'object value' => ['inputs' => ['asset' => ['symbol' => 'ETH']]],
            'unknown input' => ['inputs' => ['leverage' => 10]],
            'not numeric' => ['inputs' => ['amount' => 'lots']],
            'outside options' => ['inputs' => ['asset' => 'DOGE']],
        ] as $label => $body) {
            $response = $this->actor(null)->postJson('/api/calculators/crypto-profit/resolve', $body);
            $out[$label] = ['status' => $response->status(), 'errors' => array_keys((array) $response->json('errors'))];
        }

        $out['paid, guest, malformed'] = $this->actor(null)->postJson('/api/calculators/crypto-capital-gains/resolve', ['inputs' => 'x'])->status();

        return $out;
    }

    /** @return array<string, mixed> */
    private function purchase(): array
    {
        $plans = [];
        foreach ($this->actor(null)->getJson('/api/plans')->json('data') ?? [] as $plan) {
            $plans[$plan['slug']] = [
                'name' => $plan['name'],
                'price' => $plan['price'],
                'currency' => $plan['currency'],
                'unlocks' => array_values(array_intersect($plan['unlocks'], self::KEYS)),
            ];
        }

        $owner = $this->owner('free');
        $bought = self::answer($this->actor($owner)->postJson('/api/plans/purchase', ['plan' => 'trader']));
        $after = [
            'plan' => $this->planOf($owner),
            'position-size' => $this->actor($owner)->postJson('/api/calculators/crypto-position-size/resolve')->status(),
            'capital-gains' => $this->actor($owner)->postJson('/api/calculators/crypto-capital-gains/resolve')->status(),
        ];

        $member = $this->member('free');
        $memberTry = $this->actor($member)->postJson('/api/plans/purchase', ['plan' => 'trader'])->status();

        $decliner = $this->owner('free');
        FakeDriver::fakeDecline();
        $declined = self::answer($this->actor($decliner)->postJson('/api/plans/purchase', ['plan' => 'trader']));
        FakeDriver::clearFakes();

        return [
            'plans' => $plans,
            'owner buys trader' => $bought,
            'owner after' => $after,
            'member buys' => ['status' => $memberTry, 'plan' => $this->planOf($member)],
            'guest buys' => $this->actor(null)->postJson('/api/plans/purchase', ['plan' => 'trader'])->status(),
            'decline' => ['answer' => $declined, 'plan' => $this->planOf($decliner)],
            'free is not for sale' => $this->actor($this->owner('free'))->postJson('/api/plans/purchase', ['plan' => 'free'])->status(),
        ];
    }

    /** @return array<int, mixed> each golden vector's result from this app's PHP evaluator */
    private function golden(): array
    {
        $cases = json_decode((string) file_get_contents(self::$golden), true, flags: JSON_THROW_ON_ERROR)['cases'];
        $evaluator = new ExpressionEvaluator;

        return array_map(function (array $case) use ($evaluator): mixed {
            try {
                return $evaluator->evaluate($case['expr'], $case['env']);
            } catch (Throwable $e) {
                return 'throws '.class_basename($e);
            }
        }, $cases);
    }

    private function planOf(User $user): ?string
    {
        return Team::query()->find($user->fresh()->currentTeamOrPersonal()?->getKey())?->getAttribute('plan');
    }

    /** @return array<string, mixed> */
    private static function answer(TestResponse $response): array
    {
        $json = $response->json();

        return [
            'status' => $response->status(),
            'data' => is_array($json) ? ($json['data'] ?? null) : null,
            'errors' => is_array($json) && isset($json['errors']) ? array_keys($json['errors']) : null,
        ];
    }

    /** @return array<string, mixed> the Inertia page object of an HTML response */
    private static function props(TestResponse $response): array
    {
        if ($response->status() !== 200) {
            return [];
        }

        try {
            return (array) $response->viewData('page');
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private static function pick(array $row, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;
            $out[$key] = $key === 'graph' && $value !== null ? sha1(json_encode($value, JSON_THROW_ON_ERROR)) : $value;
        }

        return $out;
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::canonical(...), $value);
    }
}
