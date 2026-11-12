<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Calculators\CalculatorResolver;
use App\Data\CalculatorEmbedData;
use App\Data\CalculatorSpecData;
use App\Data\CalculatorSummaryData;
use App\Data\GuideData;
use App\Data\GuideSummaryData;
use App\Data\Pages\GuideIndexPageData;
use App\Data\Pages\GuideShowPageData;
use App\Enums\AssociationMode;
use App\Models\Guide;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Rushing\DataFilters\Facades\DataFilter;
use Rushing\LaravelDataSchemasScribe\Attributes\ResponseFromData;

/**
 * Serves Guides — published UX entries that embed and/or link Calculators. On show, each
 * `embed`-mode Calculator is server-resolved so it boots with the same live prices and
 * provenance as its standalone page; `link`-mode associations become CTA cards.
 */
class GuideController extends Controller
{
    public function __construct(private readonly CalculatorResolver $resolver) {}

    #[ResponseFromData(GuideIndexPageData::class)]
    public function index(Request $request): Response
    {
        $guides = DataFilter::query('guide')
            ->apply($request)
            ->get()
            ->map(static function (Model $model): GuideSummaryData {
                if (! $model instanceof Guide) {
                    throw new \LogicException('The Guide filter returned a different model.');
                }

                return GuideSummaryData::fromModel($model);
            });

        return Inertia::render('guides/index', new GuideIndexPageData(
            guides: $guides->all(),
            filters: $request->input('filter', []),
        ));
    }

    #[ResponseFromData(GuideShowPageData::class)]
    public function show(Guide $guide): Response
    {
        abort_unless($guide->isPublished(), 404);
        $guide->refresh();

        $guide->load([
            'category',
            // Eager-load closure receives the BelongsToMany relation; `published()` forwards
            // to the underlying Calculator query builder.
            'calculators' => fn ($query) => $query->published(),
        ]);

        $embeds = [];
        $links = [];
        foreach ($guide->calculators as $calculator) {
            $mode = $calculator->pivot->getAttribute('mode');
            $anchor = $calculator->pivot->getAttribute('anchor');

            if ($mode === AssociationMode::Embed->value) {
                $spec = CalculatorSpecData::project($calculator);

                $embeds[] = new CalculatorEmbedData(
                    anchor: is_string($anchor) ? $anchor : null,
                    spec: $spec,
                    resolution: $spec->locked ? null : $this->resolver->resolve($calculator),
                );
            } else {
                $links[] = CalculatorSummaryData::fromModel($calculator);
            }
        }

        return Inertia::render('guides/show', new GuideShowPageData(
            guide: GuideData::project($guide),
            embeds: $embeds,
            links: $links,
        ));
    }
}
