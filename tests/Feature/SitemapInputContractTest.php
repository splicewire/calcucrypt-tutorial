<?php

namespace Tests\Feature;

use App\Data\SitemapData;
use App\Models\SitemapRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Tests\TestCase;

class SitemapInputContractTest extends TestCase
{
    use RefreshDatabase;

    private function author(): void
    {
        $owner = User::factory()->create();
        app(TeamProvisioner::class)->personalTeamFor($owner);
        $this->actingAs($owner);
    }

    /** @return array<string, array<int, string>> */
    private function databaseRows(): array
    {
        $tables = [];
        foreach (DB::select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name") as $table) {
            $rows = DB::table($table->name)->get()->map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR))->all();
            sort($rows);
            $tables[$table->name] = $rows;
        }

        return $tables;
    }

    public function test_create_and_update_pack_only_declared_fields(): void
    {
        $this->author();
        $fields = ['label' => 'Guides', 'href' => '/docs', 'order' => 3, 'externalUrl' => 'https://example.org/docs'];
        $poison = ['id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'payload' => ['label' => 'Smuggled'], 'meta' => ['injected' => true], 'schema_ref' => 'poison', 'unexpected' => 'ignored'];
        $id = $this->postJson('/frame/resources/sitemap', $fields + $poison)->assertOk()->assertJsonPath('data.label', 'Guides')->json('data.id');
        $this->assertTrue(Str::isUuid($id));
        $this->assertNotSame($poison['id'], $id);
        $record = SitemapRecord::findOrFail($id);
        $this->assertSame($fields, $record->payload);
        $this->assertNotSame($poison['meta'], $record->meta);
        $this->assertNotSame('poison', $record->schema_ref);
        $meta = $record->meta;
        $schema = $record->schema_ref;

        // A matching route ID is harmless; an unrelated ID is separately refused by Frame.
        $fields = ['label' => 'Updated', 'href' => '/updated', 'order' => 4, 'externalUrl' => null];
        $this->putJson("/frame/resources/sitemap/records/{$id}", $fields + array_replace($poison, ['id' => $id]))->assertOk()->assertJsonPath('data.id', $id);
        $record->refresh();
        $this->assertSame($fields, $record->payload);
        $this->assertSame($meta, $record->meta);
        $this->assertSame($schema, $record->schema_ref);
        $this->assertSame(1, SitemapRecord::count());
    }

    public function test_malformed_required_and_array_fields_refuse_before_any_write(): void
    {
        $this->author();
        $id = $this->postJson('/frame/resources/sitemap', ['label' => 'Existing', 'href' => '/existing'])->assertOk()->json('data.id');
        foreach ([[], ['label' => []], ['label' => 'Missing path'], ['label' => [], 'href' => '/x'], ['label' => 'X', 'href' => []], ['label' => 'X', 'href' => '/x', 'order' => []], ['label' => 'X', 'href' => '/x', 'externalUrl' => []]] as $body) {
            $before = $this->databaseRows();
            $this->postJson('/frame/resources/sitemap', $body)->assertUnprocessable();
            $this->assertSame($before, $this->databaseRows());
            $this->putJson("/frame/resources/sitemap/records/{$id}", $body)->assertUnprocessable();
            $this->assertSame($before, $this->databaseRows());
        }
    }

    public function test_direct_array_prepare_remains_supported_with_the_same_whitelist(): void
    {
        $record = new SitemapRecord;
        SitemapData::prepare($record, ['label' => 'Direct', 'href' => '/direct', 'payload' => ['label' => 'Smuggled']]);
        $this->assertSame(['label' => 'Direct', 'href' => '/direct', 'order' => 0, 'externalUrl' => null], $record->payload);
    }
}
