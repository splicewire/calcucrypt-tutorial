<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Product as LunarProduct;
use Lunar\Core\Models\ProductType;
use Spatie\Activitylog\Facades\LogBatch;
use Spatie\Activitylog\Models\Activity;
use Splicewire\Beam\Taxonomy\Models\Silo;
use Splicewire\Beam\Taxonomy\Models\Tag;
use Tests\TestCase;

class LocalMarketplaceDependenciesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! class_exists(LunarProduct::class)) {
            $this->markTestSkipped('Optional marketplace dependency composition is not installed.');
        }
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh')->assertExitCode(0);
        $this->artisan('migrate', ['--path' => 'examples/local-marketplace/migrations'])->assertExitCode(0);
        config(['app.key' => 'base64:'.base64_encode(str_repeat('m', 32)), 'scout.driver' => 'collection', 'scout.queue' => false, 'scout.prefix' => 'marketplace-proof_']);
        Language::factory()->create(['code' => 'en', 'default' => true]);
        $this->actingAs(User::factory()->teamMember()->create());
    }

    public function test_native_activity_schema_lunar_roundtrip_batch_and_rollback(): void
    {
        $columns = DB::select('pragma table_info(activity_log)');
        $types = collect($columns)->pluck('type', 'name');
        $this->assertArrayHasKey('batch_uuid', $types->all());
        $this->assertStringContainsString('varchar', strtolower($types['subject_id']));
        $this->assertStringContainsString('varchar', strtolower($types['causer_id']));
        $product = LogBatch::withinBatch(function () {
            $product = LunarProduct::create(['product_type_id' => ProductType::firstOrFail()->id, 'status' => 'draft', 'name' => ['en' => 'Original local listing']]);
            $product->update(['name' => ['en' => 'Updated local listing']]);

            return $product;
        });
        $logs = Activity::where('subject_type', $product->getMorphClass())->where('subject_id', $product->id)->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame('created', $logs[0]->event);
        $this->assertSame('updated', $logs[1]->event);
        $this->assertSame('Original local listing', $logs[1]->properties['old']['name']['en']);
        $this->assertSame('Updated local listing', $logs[1]->properties['attributes']['name']['en']);
        $this->assertSame((string) auth()->id(), (string) $logs[1]->causer_id);
        $this->assertNotNull($logs[0]->batch_uuid);
        $this->assertSame($logs[0]->batch_uuid, $logs[1]->batch_uuid);
        $before = Activity::count();
        DB::beginTransaction();
        $product->update(['name' => ['en' => 'Rolled back']]);
        DB::rollBack();
        $this->assertSame($before, Activity::count());
        $this->assertSame('Updated local listing', $product->fresh()->name['en']);
    }

    public function test_workflow_actor_and_run_grouping_survive_activitylog4_and_outer_rollback(): void
    {
        $this->seed(\Database\Seeders\ServiceRequestApprovalSeeder::class);
        $actor = auth()->user();
        $context = new \Splicewire\Beam\Workflows\Control\TransitionContext(actor: 'user:'.$actor->id, runId: 'local-marketplace-dependency-proof');
        $records = collect(['First', 'Second'])->map(function ($title) use ($actor) {
            $record = new \App\Models\ServiceRequest(['title' => $title, 'status' => 'open']);
            $record->user_id = $actor->id;
            $record->team_id = $actor->current_team_id;
            $record->save();

            return $record;
        });
        $actuator = app(\Splicewire\Beam\Workflows\Control\WorkflowActuator::class);
        $before = Activity::count();
        DB::beginTransaction();
        $this->assertTrue($actuator->transition($records[0], 'approve', $context)->applied);
        DB::rollBack();
        $this->assertSame($before, Activity::count());
        $this->assertDatabaseCount('workflow_transition_facts', 0);
        foreach ($records as $record) {
            $this->assertTrue($actuator->transition($record->fresh(), 'approve', $context)->applied);
        }
        $statuses = Activity::where('log_name', 'status')->where('properties->run_id', $context->runId)->get();
        $this->assertCount(2, $statuses);
        foreach ($statuses as $status) {
            $this->assertSame($context->actor, $status->properties['actor']);
            $this->assertSame('complete', $status->event);
        }
        $facts = \Splicewire\Beam\Workflows\Control\WorkflowTransitionFact::where('run_id', $context->runId)->get();
        $this->assertCount(2, $facts);
        $this->assertSame([$context->actor], $facts->pluck('actor')->unique()->values()->all());
    }

    public function test_scout_collection_save_update_delete_callbacks_and_model_index_separation(): void
    {
        $models = [
            Tag::create(['name' => 'Market search unique', 'type' => 'marketplace-proof']),
            Silo::create(['name' => 'Market search unique']),
            LunarProduct::create(['product_type_id' => ProductType::firstOrFail()->id, 'status' => 'published', 'name' => ['en' => 'Market search unique']]),
        ];
        $indexes = [];
        foreach ($models as $model) {
            $class = $model::class;
            $indexes[] = $model->searchableAs();
            $this->assertStringStartsWith('marketplace-proof_', $model->searchableAs());
            $this->assertTrue($class::search('Market search unique')->get()->contains(fn ($row) => $row->getKey() === $model->getKey()));
            $model->update(['name' => $model instanceof LunarProduct ? ['en' => 'Changed search unique'] : 'Changed search unique']);
            $this->assertFalse($class::search('Market search unique')->get()->contains(fn ($row) => $row->getKey() === $model->getKey()));
            $this->assertTrue($class::search('Changed search unique')->get()->contains(fn ($row) => $row->getKey() === $model->getKey()));
            $model->delete();
            $this->assertFalse($class::search('Changed search unique')->get()->contains(fn ($row) => $row->getKey() === $model->getKey()));
        }
        $this->assertCount(3, array_unique($indexes));
        // CollectionEngine deliberately has no external index update/delete IO; results query live rows.
        $this->assertSame('Laravel\\Scout\\Engines\\CollectionEngine', get_class($models[0]->searchableUsing()));
    }
}
