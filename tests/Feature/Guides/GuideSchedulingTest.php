<?php

use App\Models\Guide;
use App\Models\User;
use Database\Seeders\CryptSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Splicewire\Beam\Calendars\Actions\ActionScheduler;
use Splicewire\Beam\Calendars\Actions\ActionService;
use Splicewire\Beam\Calendars\Contracts\ActionContextProvider;
use Splicewire\Beam\Calendars\Models\CalendarAction;
use Splicewire\Beam\Ux\Compile\EntryArtifactStore;
use Splicewire\Beam\Ux\Release\ReleaseService;
use Splicewire\Beam\Workflows\Actions\Contracts\WorkflowActionAuthority;
use Tests\Support\GuideScheduling\CalendarContext;
use Tests\Support\GuideScheduling\ReleaseAuthority;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    if (! class_exists(ActionService::class)) {
        $this->markTestSkipped('Optional real calendars integration: install the documented isolated proof overlay.');
    }
    Illuminate\Support\Facades\Storage::fake('beam-ux');
    $this->withoutVite();
    foreach (['create_calendars_table', 'create_calendar_series_table', 'create_calendar_events_table', 'create_calendar_firings_table', 'create_calendar_actions_table', 'create_calendar_action_attempts_table', 'create_calendar_action_series_table'] as $migration) {
        (require base_path('vendor/splicewire/laravel-beam-calendars/database/migrations/shared/'.$migration.'.php.stub'))->up();
    }
    // The optional fixture explicitly declares one central database, without a tenant split.
    config()->set('tenancy.database.central_connection', null);
    $this->app->bind(ActionContextProvider::class, CalendarContext::class);
    $this->app->bind(WorkflowActionAuthority::class, ReleaseAuthority::class);
    $this->seed(CryptSeeder::class);
});

/** Two actual authors, each with package-provisioned realm grants. */
function guideScheduleAuthors(): array
{
    $authors = [User::factory()->create(), User::factory()->create()];
    foreach ($authors as $actor) {
        app(TeamProvisioner::class)->personalTeamWithFullReachFor($actor);
    }

    return array_map(fn ($actor) => $actor->fresh(), $authors);
}

function approvedGuideRelease(User $author, User $reviewer): array
{
    $guide = Guide::query()->where('slug', 'how-crypto-profit-works')->firstOrFail();
    $entry = $guide->entry;
    $entry->forceFill(['workflow_marking' => 'draft'])->save();
    $service = app(ReleaseService::class);
    $release = $service->create($entry, 'Publish the Guide', $author);
    $service->putMember($release, $entry->id, $entry->published_version, $author);
    $service->transition($release->fresh(), 'submit', $author);
    $service->transition($release->fresh(), 'approve', $reviewer);

    return [$guide, $entry->fresh(), $release->fresh()];
}

it('publishes a hidden Guide only when its real scheduled Release executes, once', function (): void {
    [$author, $reviewer] = guideScheduleAuthors();
    [$guide, $entry, $release] = approvedGuideRelease($author, $reviewer);
    $this->actingAs($author);
    $due = now()->addHour();
    app(ReleaseService::class)->schedule($release, $due->toIso8601String(), 'UTC', $author);
    $action = CalendarAction::query()->sole();
    expect($action->kind)->toBe('kind.workflow-transition')->and($release->fresh()->status)->toBe('scheduled');
    $artifact = route('beam.ux.site.artifact', ['entry' => $entry->id, 'version' => app(EntryArtifactStore::class)->version($entry)]);
    $this->get('/guides/'.$guide->slug)->assertNotFound();
    $this->getJson('/api/guides/'.$guide->slug)->assertNotFound();
    $this->get($artifact)->assertNotFound();
    expect(app(ActionScheduler::class)->sweep(CalendarContext::SCOPE, $due->copy()->subSecond()))->toBe([]);
    expect($entry->fresh()->workflow_marking)->toBe('draft');
    Auth::logout();
    $attempts = app(ActionScheduler::class)->sweep(CalendarContext::SCOPE, $due);
    expect($attempts)->toHaveCount(1)->and($attempts[0]->status)->toBe('applied')
        ->and($entry->fresh()->workflow_marking)->toBe('published')->and($release->fresh()->status)->toBe('published');
    $this->get('/guides/'.$guide->slug)->assertOk()->assertInertia(fn ($page) => $page->has('embeds', 1));
    $this->get($artifact)->assertOk();
    expect(app(ActionScheduler::class)->sweep(CalendarContext::SCOPE, $due->copy()->addDay()))->toBe([]);
    expect($action->fresh()->attempts()->count())->toBe(1);
});

it('blocks the due action after its execution author loses actual realm reach', function (): void {
    [$author, $reviewer] = guideScheduleAuthors();
    [$guide, $entry, $release] = approvedGuideRelease($author, $reviewer);
    $this->actingAs($author);
    $due = now()->addHour();
    app(ReleaseService::class)->schedule($release, $due->toIso8601String(), 'UTC', $author);
    $pin = $entry->published_version;
    $team = $author->currentTeamOrPersonal();
    $revoked = 0;
    foreach (Splicewire\Beam\Ux\Models\BeamUxEntry::query()->where('namespace', 'realms')->get() as $root) {
        $revoked += app(Splicewire\Beam\Accounts\Sharing\AccessGrants::class)->revoke($root, $team);
    }
    expect($revoked)->toBeGreaterThan(0);
    expect(Illuminate\Support\Facades\Gate::forUser($author->fresh())->allows('ux.author'))->toBeFalse();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Auth::logout();
    $attempts = app(ActionScheduler::class)->sweep(CalendarContext::SCOPE, $due);
    expect($attempts)->toHaveCount(1)->and($attempts[0]->status)->toBe('blocked')
        ->and($entry->fresh()->workflow_marking)->toBe('draft')->and($entry->fresh()->published_version)->toBe($pin);
    $this->get('/guides/'.$guide->slug)->assertNotFound();
    // Recovery is a newly reviewed revision, not replay of a terminal blocked action.
    app(Splicewire\Beam\Accounts\Teams\RealmReachGrant::class)->toTeam($team);
    $service = app(ReleaseService::class);
    $service->transition($release->fresh(), 'revise', $author);
    $service->transition($release->fresh(), 'submit', $author);
    $service->transition($release->fresh(), 'approve', $reviewer);
    $this->actingAs($author->fresh());
    $service->schedule($release->fresh(), $due->copy()->addHour()->toIso8601String(), 'UTC', $author->fresh());
    Auth::logout();
    $recovered = app(ActionScheduler::class)->sweep(CalendarContext::SCOPE, $due->copy()->addHour());
    expect($recovered)->toHaveCount(1)->and($recovered[0]->status)->toBe('applied');
    expect(CalendarAction::query()->count())->toBe(2);
});

it('does not mount calendar action APIs or a default scheduler with the optional dependency', function (): void {
    $routes = collect(app('router')->getRoutes()->getRoutes())->map(fn ($route) => $route->uri())->all();
    expect(array_values(array_filter($routes, fn ($uri) => str_contains($uri, 'calendar-actions') || str_contains($uri, 'calendar-action-series'))))->toBe([]);
    $this->getJson('/calendar-actions')->assertNotFound();
    $this->postJson('/calendar-actions/schedule', [])->assertStatus(405);
    Auth::logout();
    expect(fn () => app(ActionContextProvider::class)->current())->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
});

it('makes an old scheduled action harmless after publish-now or revision', function (string $change, string $expectedMarking): void {
    [$author, $reviewer] = guideScheduleAuthors();
    [$guide, $entry, $release] = approvedGuideRelease($author, $reviewer);
    $this->actingAs($author);
    $due = now()->addHour();
    $service = app(ReleaseService::class);
    $service->schedule($release, $due->toIso8601String(), 'UTC', $author);
    $service->transition($release->fresh(), $change, $author);
    $pin = $entry->fresh()->published_version;
    Auth::logout();
    $attempts = app(ActionScheduler::class)->sweep(CalendarContext::SCOPE, $due);
    expect($attempts)->toHaveCount(1)->and($attempts[0]->status)->toBe('blocked')
        ->and($entry->fresh()->workflow_marking)->toBe($expectedMarking)
        ->and($entry->fresh()->published_version)->toBe($pin);
})->with([
    'publish now' => ['publish', 'published'],
    'revise before due' => ['revise', 'draft'],
]);

it('rolls scheduling back for a non-Guide member and rejects declared tenant context', function (): void {
    [$author, $reviewer] = guideScheduleAuthors();
    [$guide, $entry, $release] = approvedGuideRelease($author, $reviewer);
    $entry->forceFill(['namespace' => 'other-content'])->save();
    $this->actingAs($author);
    expect(fn () => app(ReleaseService::class)->schedule($release, now()->addHour()->toIso8601String(), 'UTC', $author))->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
    expect($release->fresh()->status)->toBe('approved')->and($release->fresh()->schedule_token)->toBeNull()
        ->and(CalendarAction::query()->count())->toBe(0);
    config()->set('tenancy.database.central_connection', config('database.default'));
    expect(fn () => app(ActionContextProvider::class)->current())->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
});
