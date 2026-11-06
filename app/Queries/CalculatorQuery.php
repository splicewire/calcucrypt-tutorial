<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Calculator;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Rushing\DataFilters\Query\ResourceQuery;

/**
 * Binds the Calculator filter surface to the model and scopes it to what a visitor may see
 * (published only). The default sort is by name.
 */
class CalculatorQuery extends ResourceQuery
{
    protected function baseQuery(Request $request): Builder
    {
        return Calculator::query()->published()->with('category');
    }

    protected function defaultSort(): ?string
    {
        return 'name';
    }
}
