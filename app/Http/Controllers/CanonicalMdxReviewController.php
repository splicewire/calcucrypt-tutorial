<?php

namespace App\Http\Controllers;

use App\Data\CanonicalMdxVersionChoiceData;
use App\Data\Pages\CanonicalMdxReviewPageData;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Rushing\LaravelDataSchemasScribe\Attributes\ResponseFromData;
use Splicewire\Beam\Ux\Body\BodyKind;
use Splicewire\Beam\Ux\Data\ReleaseData;
use Splicewire\Beam\Ux\Format\UxFormat;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Splicewire\Beam\Ux\Models\BeamUxRelease;
use Splicewire\Beam\Ux\Publish\EntryPublication;
use Splicewire\Beam\Ux\Release\ReleaseService;
use Splicewire\Beam\Ux\Type\UxType;

final class CanonicalMdxReviewController
{
    #[ResponseFromData(CanonicalMdxReviewPageData::class)]
    public function __invoke(string $id, Request $request, ReleaseService $releases, EntryPublication $publication): Response
    {
        abort_unless(config('canonical-mdx.enabled', false), 404);
        $actor = $request->user();
        abort_unless($actor instanceof Authenticatable, 403);
        Gate::forUser($actor)->authorize('ux.author');
        $entry = BeamUxEntry::findOrFail($id);
        $releases->authorizeFileEdit($entry, $actor);
        abort_unless($entry->getAttribute('type') === UxType::Page && $entry->getAttribute('format') === UxFormat::Mdx && $entry->bodyKind() === BodyKind::Source, 404);
        abort_if($request->query->count() !== 0, 422);
        // Authorize before reading the version store; only saved identities/labels cross this door.
        $choices = array_map(fn ($version): CanonicalMdxVersionChoiceData => new CanonicalMdxVersionChoiceData(
            $version->id, $version->readable.($version->label === null ? '' : ' — '.$version->label),
        ), $publication->state($entry)->versions);

        // This narrow page never projects a Release with another entry the actor has not authorized.
        $savedReleases = BeamUxRelease::on($entry->getConnectionName())
            ->whereHas('members', fn ($query) => $query->where('entry_id', $entry->id))
            ->whereDoesntHave('members', fn ($query) => $query->where('entry_id', '!=', $entry->id))
            ->orderByDesc('created_at')->get()->map(fn ($release): ReleaseData => ReleaseData::project($release))->all();

        return Inertia::render('account/canonical-mdx-review', new CanonicalMdxReviewPageData(
            (string) $entry->getKey(), $entry->title ?? (string) $entry->getAttribute('slug'), $choices, array_values($savedReleases),
        ));
    }
}
