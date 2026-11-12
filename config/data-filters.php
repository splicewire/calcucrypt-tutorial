<?php

declare(strict_types=1);

use App\Data\Filters\CalculatorFilterData;
use App\Data\Filters\GuideFilterData;
use App\Models\Calculator;
use App\Models\Guide;
use App\Queries\CalculatorQuery;
use App\Queries\GuideQuery;

return [
    /*
    |--------------------------------------------------------------------------
    | Resource Registry
    |--------------------------------------------------------------------------
    |
    | The Crypt's two filterable resources. Each binds a Filter Data declaration to
    | its Query (scoping + default sort) and model. `DataFilter::query('calculator')`
    | / `query('guide')` drive both browse and the "Search the Crypt" box.
    |
    */
    'resources' => [
        'calculator' => [
            'data' => CalculatorFilterData::class,
            'query' => CalculatorQuery::class,
            'model' => Calculator::class,
        ],
        'guide' => [
            'data' => GuideFilterData::class,
            'query' => GuideQuery::class,
            'model' => Guide::class,
        ],
    ],
];
