<?php

namespace App\Content\Guides;

use App\Models\Calculator;
use Spatie\Sitemap\Tags\Url;
use Splicewire\Beam\Sitemap\Contracts\SitemapSource;
use Splicewire\Beam\Sitemap\Resolvers\SitemapBaseUrlResolver;

/** Calculator URLs complement UX's gated Guide entry source. */
class CalculatorSitemapSource implements SitemapSource
{
    public function urls(): iterable
    {
        $base = rtrim(app(SitemapBaseUrlResolver::class)->baseUrl(), '/');
        foreach (Calculator::query()->published()->get() as $calculator) {
            yield Url::create($base.'/calculators/'.$calculator->slug);
        }
    }
}
