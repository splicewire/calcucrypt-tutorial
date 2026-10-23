<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\CommittedApprovals;
use App\Wallet\ApprovalWalletMeter;
use App\Wallet\CommerceUsageMeter;
use App\Wallet\TeamApprovalWallet;
use App\Wallet\TeamWalletContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Rushing\Commerce\Events\PurchaseCompleted;
use Rushing\Commerce\Wallets;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Splicewire\Beam\Workflows\Control\WorkflowActuator;
use Tests\TestCase;

class TeamApprovalWalletTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';
        $this->traitsUsedByTest = class_uses_recursive(static::class);
        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            $app['config']->set(['team-wallet.enabled' => true, 'team-wallet.sandbox' => true,
                'commerce.driver' => 'fake', 'commerce.currency' => 'USD', 'commerce.credit.unit' => 'usd']);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        if (! class_exists(Wallets::class)) {
            $this->markTestSkipped('Optional Commerce composition is absent.');
        }
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName(), 'Refuse tests against a served DB.');
        $this->withoutVite();
        // Native published shared migration is a prerequisite, never a fabricated test schema.
        $paths = glob(database_path('migrations/shared/*_create_commerce_credit_entries_table.php'));
        $this->assertCount(1, $paths, 'Publish exact Commerce credit stub before this optional suite.');
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertExitCode(0);
        $this->seed(\Database\Seeders\ServiceRequestApprovalSeeder::class);
    }

    private function owner(): User
    {
        $user = User::factory()->create();
        app(TeamProvisioner::class)->createTeamFor($user, 'Wallet team');

        return $user;
    }

    private function pending(User $user): ServiceRequest
    {
        $row = new ServiceRequest(['title' => 'Wallet approval', 'status' => 'open']);
        $row->user_id = $user->id;
        $row->team_id = $user->current_team_id;
        $row->save();

        return $row;
    }

    private function summary(string $credit, int $count, string $usage, string $balance): void
    {
        $this->getJson('/frame/resources/team-approval-wallet')->assertOk()
            ->assertJsonPath('data.0.creditedUsd', $credit)->assertJsonPath('data.0.approvalCount', $count)
            ->assertJsonPath('data.0.usageUsd', $usage)->assertJsonPath('data.0.balanceUsd', $balance);
    }

    private function state(): array
    {
        return collect(['commerce_credit_entries', 'service_requests', 'workflow_transition_facts'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    public function test_real_funding_committed_approval_repeat_and_unfunded_negative_balance(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $this->summary('$0.00', 0, '$0.00', '$0.00');
        $this->getJson('/frame/resources/team-approval-wallet/schema?purpose=read')->assertOk();
        $this->postJson('/team-approval-wallet/top-up', ['amount_usd' => 2])->assertOk()
            ->assertJsonPath('data.captured', true)->assertJsonPath('data.driver', 'fake')
            ->assertJsonPath('data.wallet.balanceUsd', '$2.00');
        $credit = DB::table('commerce_credit_entries')->sole();
        $this->assertSame('service-desk-team:'.$owner->current_team_id, $credit->party_id);
        $this->assertSame('usd', $credit->unit);
        $this->assertStringStartsWith('topup:', $credit->reason);
        $this->assertNull($credit->purchase_id);
        $row = $this->pending($owner);
        $this->postJson('/service-requests/'.$row->id.'/approve')->assertOk();
        $this->summary('$2.00', 1, '$0.25', '$1.75');
        $before = $this->state();
        $this->postJson('/service-requests/'.$row->id.'/approve')->assertConflict();
        $this->getJson('/frame/resources/team-approval-wallet/records/approvals')->assertOk();
        $this->assertSame($before, $this->state());
        $other = $this->owner();
        $this->actingAs($other);
        $this->postJson('/service-requests/'.$this->pending($other)->id.'/approve')->assertOk();
        $this->summary('$0.00', 1, '$0.25', '-$0.25');
    }

    public function test_fresh_membership_owner_only_funding_and_same_service_a_b_a(): void
    {
        $a = $this->owner();
        $b = $this->owner();
        $teamA = $a->currentTeam;
        $this->actingAs($a)->postJson('/team-approval-wallet/top-up', ['amount_usd' => 2])->assertOk();
        $service = app(TeamApprovalWallet::class);
        app(TeamProvisioner::class)->addMember($a, $b->currentTeam, Role::Member);
        $this->assertSame('$2.00', $service->read($a)->balanceUsd);
        $a->switchTeam($b->currentTeam);
        $this->assertSame('$0.00', $service->read($a)->balanceUsd);
        $before = $this->state();
        $this->postJson('/team-approval-wallet/top-up', ['amount_usd' => 2])->assertForbidden();
        $a->switchTeam($teamA);
        $this->assertSame('$2.00', $service->read($a)->balanceUsd);
        foreach ([Role::Member, Role::Admin] as $role) {
            $actor = User::factory()->create();
            app(TeamProvisioner::class)->addMember($actor, $teamA, $role);
            $actor->switchTeam($teamA);
            $this->actingAs($actor);
            $this->getJson('/frame/resources/team-approval-wallet')->assertOk();
            $this->postJson('/team-approval-wallet/top-up', ['amount_usd' => 1])->assertForbidden();
        }
        $a->load('currentTeam');
        $teamA->removeMember($a);
        $this->actingAs($a);
        $this->getJson('/frame/resources/team-approval-wallet')->assertForbidden();
        $this->postJson('/team-approval-wallet/top-up', ['amount_usd' => 1])->assertForbidden();
        $this->actingAs(User::factory()->create());
        $this->getJson('/frame/resources/team-approval-wallet')->assertForbidden();
        $this->assertSame($before, $this->state());
    }

    public function test_forged_fields_decline_and_configured_rail_refuse_without_mutation(): void
    {
        $a = $this->owner();
        $this->actingAs($a);
        $before = $this->state();
        foreach (['party', 'team', 'beneficiary', 'unit', 'driver', 'tariff', 'actor'] as $key) {
            $this->postJson('/team-approval-wallet/top-up', ['amount_usd' => 2, $key => 'forged'])->assertUnprocessable();
        }
        foreach ([0, 10001, 1.005, 'invalid', []] as $amount) {
            $this->postJson('/team-approval-wallet/top-up', ['amount_usd' => $amount])->assertUnprocessable();
        }
        $events = 0;
        Event::listen(PurchaseCompleted::class, function () use (&$events): void {
            $events++;
        });
        $this->postJson('/team-approval-wallet/top-up', ['amount_usd' => 666.02])->assertStatus(402)->assertJsonPath('data.captured', false);
        $this->assertSame(0, $events);
        config(['commerce.driver' => 'stripe']);
        $this->postJson('/team-approval-wallet/top-up', ['amount_usd' => 2])->assertForbidden();
        config(['commerce.driver' => 'fake', 'team-wallet.sandbox' => false]);
        $this->postJson('/team-approval-wallet/top-up', ['amount_usd' => 2])->assertForbidden();
        $this->assertSame($before, $this->state());
    }

    public function test_exact_shared_fact_meter_and_rollback_no_default_zero(): void
    {
        $a = $this->owner();
        $row = $this->pending($a);
        $party = app(TeamWalletContext::class)->party($a->currentTeam);
        $meter = new CommerceUsageMeter(new ApprovalWalletMeter($party, (string) $a->current_team_id, app(CommittedApprovals::class)));
        app(WorkflowActuator::class)->transition($row, 'approve');
        $this->assertSame(0.25, $meter->debitedFor($party, 'usd'));
        $this->actingAs($a)->getJson('/frame/resources/service-request-usage')->assertOk()->assertJsonPath('data.0.quantity', 1);
        DB::beginTransaction();
        app(WorkflowActuator::class)->transition($this->pending($a), 'approve');
        DB::rollBack();
        $this->assertSame(0.25, $meter->debitedFor($party, 'usd'));
        foreach (['workflow_transition_facts', 'commerce_credit_entries'] as $table) {
            Schema::rename($table, $table.'_unavailable');
            try {
                $this->getJson('/frame/resources/team-approval-wallet')->assertStatus(500)->assertJsonMissingPath('data');
            } finally {
                Schema::rename($table.'_unavailable', $table);
            }
        }
        $this->summary('$0.00', 1, '$0.25', '-$0.25');
        foreach ([['foreign', 'usd'], [$party, 'eur']] as [$requestedParty, $unit]) {
            try {
                $meter->debitedFor($requestedParty, $unit);
                $this->fail('Unsupported scope or unit was accepted.');
            } catch (\InvalidArgumentException $error) {
                $this->assertSame('Unsupported approval wallet party or unit.', $error->getMessage());
            }
        }
    }

    public function test_same_captured_purchase_replay_no_second_credit_new_purchase_different(): void
    {
        $a = $this->owner();
        $captured = null;
        Event::listen(PurchaseCompleted::class, function ($event) use (&$captured): void {
            $captured = $event->purchase;
        });
        $this->actingAs($a)->postJson('/team-approval-wallet/top-up', ['amount_usd' => 2])->assertOk();
        $this->assertNotNull($captured);
        event(new PurchaseCompleted($captured));
        $this->assertSame(1, DB::table('commerce_credit_entries')->count());
        $originalRef = $captured->payment->providerRef;
        $this->postJson('/team-approval-wallet/top-up', ['amount_usd' => 1])->assertOk();
        $this->assertNotSame($originalRef, $captured->payment->providerRef);
        $this->summary('$3.00', 0, '$0.00', '$3.00');
    }

    public function test_listener_write_failure_is_unknown_not_payment_rollback_or_fake_zero(): void
    {
        $a = $this->owner();
        $this->actingAs($a);
        $observed = null;
        \Rushing\Commerce\Models\CreditEntry::creating(function ($credit) use (&$observed): void {
            $observed = $credit->getAttributes();
            throw new \RuntimeException('Isolated listener write failure after succeeded fake payment.');
        });
        $this->postJson('/team-approval-wallet/top-up', ['amount_usd' => 2])->assertStatus(500);
        // This creating event is reached from the real funding listener, which runs only after captured payment.
        $this->assertSame('service-desk-team:'.$a->current_team_id, $observed['party_id']);
        $this->assertSame('usd', $observed['unit']);
        $this->assertSame(2.0, $observed['amount']);
        $this->assertStringStartsWith('topup:', $observed['reason']);
        $this->assertSame(0, DB::table('commerce_credit_entries')->count());
        $this->summary('$0.00', 0, '$0.00', '$0.00');
        // No automatic repeat was attempted; real fake rail capture may precede listener failure.
    }

    public function test_invalid_currency_and_unit_never_become_zero_and_production_cannot_fund(): void
    {
        $a = $this->owner();
        $this->actingAs($a);
        foreach ([['commerce.currency' => 'EUR'], ['commerce.currency' => 'USD', 'commerce.credit.unit' => 'approvals']] as $bad) {
            config($bad);
            $this->getJson('/frame/resources/team-approval-wallet')->assertStatus(500)->assertJsonMissingPath('data');
        }
        config(['commerce.credit.unit' => 'usd']);
        $this->app->instance('env', 'production');
        $before = $this->state();
        try {
            \App\Wallet\FundTeamApprovalWallet::handle(null, \Illuminate\Http\Request::create('/team-approval-wallet/top-up', 'POST', ['amount_usd' => 2]), $a);
            $this->fail('Production sandbox funding must refuse.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->assertSame($before, $this->state());
    }

    public function test_guest_write_paths_and_unauthorized_reads_precede_wallet_sources(): void
    {
        $this->getJson('/frame/resources/team-approval-wallet')->assertUnauthorized();
        $this->postJson('/team-approval-wallet/top-up', ['amount_usd' => 2])->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'commerce_credit_entries') || str_contains($query->sql, 'workflow_transition_facts')) {
                $queries++;
            }
        });
        $this->getJson('/frame/resources/team-approval-wallet')->assertForbidden();
        $this->postJson('/team-approval-wallet/top-up', ['amount_usd' => 2])->assertForbidden();
        $this->assertSame(0, $queries);
        $a = $this->owner();
        $this->actingAs($a);
        $before = $this->state();
        $this->postJson('/frame/resources/team-approval-wallet', ['creditedUsd' => 999])->assertForbidden();
        $this->putJson('/frame/resources/team-approval-wallet/records/approvals', ['creditedUsd' => 999])->assertForbidden();
        $this->deleteJson('/frame/resources/team-approval-wallet/records/approvals')->assertForbidden();
        $this->assertSame($before, $this->state());
    }

    public function test_stock_console_href_is_registered_beside_the_api(): void
    {
        $this->actingAs($this->owner());
        $this->assertSame('/team-approval-wallet', app(\Splicewire\Beam\Ux\Frame\RouteContextProjector::class)
            ->hrefs('tenant')['team-approval-wallet.index'] ?? null);
        $this->get('/team-approval-wallet')->assertOk();
    }
}
