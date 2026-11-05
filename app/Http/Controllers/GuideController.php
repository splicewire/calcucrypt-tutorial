<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Calculators\CalculatorResolver;
use App\Data\CalculatorEmbedData;
use App\Data\CalculatorSpecData;
use App\Data\CalculatorSummaryData;
use App\Data\GuideData;
use App\Data\GuideSummaryData;
use App\Enums\AssociationMode;
use App\Models\Post;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Rushing\DataFilters\Facades\DataFilter;

/**
 * Serves Guides — editorial Posts that embed and/or link Calculators. On show, each
 * `embed`-mode Calculator is server-resolved so it boots with the same live prices and
 * provenance as its standalone page; `link`-mode associations become CTA cards.
 */
class GuideController extends Controller
{
    public function __construct(private readonly CalculatorResolver $resolver) {}

    public function index(Request $request): Response
    {
        $guides = DataFilter::query('guide')
            ->apply($request)
            ->get()
            ->map(GuideSummaryData::fromModel(...));

        return Inertia::render('guides/index', [
            'guides' => $guides,
            'filters' => $request->input('filter', []),
        ]);
    }

    public function show(Post $post): Response
    {
        abort_unless($post->isPublished(), 404);

        $post->load([
            'category',
            // Eager-load closure receives the BelongsToMany relation; `published()` forwards
            // to the underlying Calculator query builder.
            'calculators' => fn ($query) => $query->published(),
        ]);

        $embeds = [];
        $links = [];
        foreach ($post->calculators as $calculator) {
            $mode = $calculator->pivot->getAttribute('mode');
            $anchor = $calculator->pivot->getAttribute('anchor');

            if ($mode === AssociationMode::Embed->value) {
                $embeds[] = new CalculatorEmbedData(
                    anchor: is_string($anchor) ? $anchor : null,
                    spec: CalculatorSpecData::fromModel($calculator),
                    resolution: $this->resolver->resolve($calculator),
                );
            } else {
                $links[] = CalculatorSummaryData::fromModel($calculator);
            }
        }

        return Inertia::render('guides/show', [
            'guide' => GuideData::fromModel($post),
            'embeds' => $embeds,
            'links' => $links,
        ]);
    }
}
