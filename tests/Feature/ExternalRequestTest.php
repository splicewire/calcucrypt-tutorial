<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalRequestTest extends TestCase
{
    use RefreshDatabase;

    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('e', 32))]);
        $this->withoutVite();
        $this->fixture = storage_path('app/private/external-test-'.bin2hex(random_bytes(8)).'.json');
        config(['external-requests.fixture_file' => $this->fixture]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        if (is_file($this->fixture)) {
            unlink($this->fixture);
        }
        parent::tearDown();
    }

    private function source(User $a, User $b): void
    {
        file_put_contents($this->fixture, json_encode(['baseUrl' => 'http://127.0.0.1:46590', 'token' => str_repeat('a', 48), 'sources' => [
            (string) $a->current_team_id => ['id' => 'fixture-desk-a', 'context' => 'a'],
            (string) $b->current_team_id => ['id' => 'fixture-desk-b', 'context' => 'b'],
        ]], JSON_THROW_ON_ERROR));
        chmod($this->fixture, 0600);
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(fn ($request) => Http::response(['data' => array_map(fn ($i) => [
            'externalId' => 'R'.$i, 'title' => $request['source'].' request '.$i,
            'status' => $i === 2 ? 'closed' : 'open', 'updatedAt' => '2026-10-21T00:00:0'.$i.'Z', 'privateValue' => 'not-projected',
        ], $request['source'] === 'a' ? [1, 2, 3] : [1])], 200));
    }

    public function test_declared_frame_and_rest_read_projection_pages_filters_and_isolates_sources(): void
    {
        $a = User::factory()->teamMember()->create();
        $b = User::factory()->teamMember()->create();
        $this->source($a, $b);
        $this->actingAs($a);
        $first = $this->getJson('/frame/resources/external-requests?per_page=2')->assertOk()->assertJsonMissingPath('total')->json();
        $this->assertSame(['fixture-desk-a:R3', 'fixture-desk-a:R2'], array_column($first['data'], 'id'));
        $second = $this->getJson('/frame/resources/external-requests?per_page=2&cursor='.urlencode($first['nextCursor']))->assertOk()->json();
        $this->assertSame(['fixture-desk-a:R1'], array_column($second['data'], 'id'));
        $this->assertNull($second['nextCursor']);
        $this->getJson('/frame/resources/external-requests?filter[status]=closed')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.externalId', 'R2');
        $this->getJson('/frame/resources/external-requests/records/fixture-desk-a:R1')->assertOk()->assertJsonPath('data.source', 'fixture-desk-a')->assertDontSee('not-projected');
        // No total is reported: absent (beam's ArrayResponseEnvelope) or null (ResponseBodyEnvelope); json() reads both as null.
        $this->assertNull($this->getJson('/api/external-requests?perPage=2')->assertOk()->assertJsonPath('limit', 2)->assertJsonCount(2, 'data')->json('total'));
        $this->getJson('/api/external-requests/fixture-desk-a:R1')->assertNotFound();
        $this->actingAs($b);
        $this->getJson('/frame/resources/external-requests')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 'fixture-desk-b:R1');
        $this->getJson('/frame/resources/external-requests/records/fixture-desk-a:R1')->assertNotFound();
        $this->actingAs($a);
        $this->getJson('/frame/resources/external-requests')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/frame/resources/external-requests/records/fixture-desk-a:missing')->assertNotFound();
        $this->getJson('/frame/resources/external-requests/schema?purpose=read')->assertOk()->assertJsonPath('properties.source.title', 'Source');
        $this->getJson('/frame/resources/external-requests?filter[source]=b')->assertUnprocessable();
        $this->getJson('/frame/resources/external-requests?filter[status]=approved')->assertUnprocessable();
    }

    public function test_gates_and_closed_writes_do_not_contact_provider(): void
    {
        $this->getJson('/frame/resources/external-requests')->assertUnauthorized();
        $this->getJson('/api/external-requests')->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->getJson('/frame/resources/external-requests')->assertForbidden();
        $this->getJson('/frame/resources/external-requests/schema?purpose=read')->assertForbidden();
        $this->getJson('/api/external-requests')->assertForbidden();
        $a = User::factory()->teamMember()->create();
        $b = User::factory()->teamMember()->create();
        $this->source($a, $b);
        $this->actingAs($a);
        $this->postJson('/frame/resources/external-requests', ['title' => 'forged'])->assertForbidden();
        $this->putJson('/frame/resources/external-requests/records/fixture-desk-a:R1', ['title' => 'forged'])->assertForbidden();
        $this->deleteJson('/frame/resources/external-requests/records/fixture-desk-a:R1')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_upstream_failures_and_oversized_or_malformed_data_are_not_empty_success(): void
    {
        $a = User::factory()->teamMember()->create();
        $b = User::factory()->teamMember()->create();
        $this->source($a, $b);
        $this->actingAs($a);
        foreach ([Http::response(['private' => 'secret'], 503), Http::response(['data' => [['externalId' => 'R1']]], 200), Http::response(['data' => array_fill(0, 101, [])], 200)] as $response) {
            Http::swap(new \Illuminate\Http\Client\Factory);
            Http::fake(['*' => $response]);
            $this->getJson('/frame/resources/external-requests')->assertStatus(502)->assertDontSee('secret')->assertJsonMissingPath('data');
        }
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*' => Http::failedConnection()]);
        $this->getJson('/frame/resources/external-requests')->assertStatus(504);
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*' => Http::response(['data' => []], 200)]);
        $this->getJson('/frame/resources/external-requests')->assertOk()->assertJsonCount(0, 'data');
        $this->source($a, $b);
        $this->getJson('/frame/resources/external-requests')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_same_backing_rechecks_selection_and_removed_membership_before_http(): void
    {
        $a = User::factory()->teamMember()->create();
        $b = User::factory()->teamMember()->create();
        $this->source($a, $b);
        app(\Splicewire\Beam\Accounts\Teams\TeamProvisioner::class)->addMember($a, $b->currentTeam, \Splicewire\Beam\Accounts\Enums\Role::Member);
        $original = $a->currentTeam;
        $this->actingAs($a);
        request()->setUserResolver(fn () => $a);
        $backing = app(\App\Backings\ExternalRequestBacking::class);
        $this->assertSame('fixture-desk-a:R3', $backing->records([], null, 2)->items()[0]->id);
        $a->switchTeam($b->currentTeam);
        $this->assertSame('fixture-desk-b:R1', $backing->records([], null, 2)->items()[0]->id);
        $a->switchTeam($original);
        $this->assertSame('fixture-desk-a:R3', $backing->records([], null, 2)->items()[0]->id);
        $original->removeMember($a);
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        $this->getJson('/frame/resources/external-requests')->assertForbidden();
        $this->getJson('/api/external-requests')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_unexpected_programming_fault_is_not_relabelled_as_source_failure(): void
    {
        $a = User::factory()->teamMember()->create();
        $b = User::factory()->teamMember()->create();
        $this->source($a, $b);
        $this->actingAs($a);
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(fn () => throw new \LogicException('Unexpected programming failure'));
        $this->getJson('/frame/resources/external-requests')->assertStatus(500);
    }
}
