<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Calculators\CalculatorResolver;
use App\Data\CalculatorSpecData;
use App\Data\CalculatorSummaryData;
use App\Data\GuideSummaryData;
use App\Data\Pages\CalculatorIndexPageData;
use App\Data\Pages\CalculatorShowPageData;
use App\Models\Calculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Rushing\DataFilters\Facades\DataFilter;
use Rushing\LaravelDataSchemasScribe\Attributes\ResponseFromData;
use Spatie\LaravelData\Lazy;

/**
 * Serves the Crypt's Calculators. `show` renders a tool page grounded in live prices for its
 * first paint; after that the widget re-resolves through the `resolve` operation
 * (App\Particle\Operations\ResolveCalculator), which calls the same resolver.
 * The server resolves refs; the client evaluates the arithmetic DAG.
 */
class CalculatorController extends Controller
{
    public function __construct(private readonly CalculatorResolver $resolver) {}

    #[ResponseFromData(CalculatorIndexPageData::class)]
    public function index(Request $request): Response
    {
        $calculators = DataFilter::query('calculator')
            ->apply($request)
            ->get()
            ->map(CalculatorSummaryData::fromModel(...));

        return Inertia::render('calculators/index', new CalculatorIndexPageData(
            calculators: $calculators->all(),
            filters: $request->input('filter', []),
        ));
    }

    #[ResponseFromData(CalculatorShowPageData::class)]
    public function show(Calculator $calculator): Response
    {
        abort_unless($calculator->isPublished(), 404);

        // Inputs that steer a ref (e.g. `asset`) arrive as query params on a partial reload.
        // Lazy so a `?only=resolution` reload re-resolves without rebuilding the whole page.
        $inputs = request()->query();

        // Back-findability: every published Guide that references this calculator, with its
        // canonical explainer (the primary Guide) surfaced first in "Learn more".
        $guides = $calculator->posts()->published()->get();
        $primary = $calculator->primary_post_id !== null
            ? $guides->firstWhere('id', $calculator->primary_post_id)
            : null;

        // A locked calculator is described but not grounded: no graph, and no live prices.
        $spec = CalculatorSpecData::project($calculator);

        return Inertia::render('calculators/show', new CalculatorShowPageData(
            spec: $spec,
            resolution: $spec->locked ? null : Lazy::closure(fn () => $this->resolver->resolve($calculator, $inputs)),
            guides: $guides->map(GuideSummaryData::fromModel(...))->values()->all(),
            primaryGuide: $primary !== null ? GuideSummaryData::fromModel($primary) : null,
        ));
    }
}
