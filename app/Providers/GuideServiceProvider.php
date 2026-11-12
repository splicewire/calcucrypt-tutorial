<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Splicewire\Beam\Ux\Workflow\EntryPublishLifecycle;
use Splicewire\Beam\Workflows\Binding\WorkflowBindingRegistry;

class GuideServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->scoped(\App\Content\Guides\GuideCatalog::class);
        \Splicewire\Beam\Ux\Models\BeamUxEntry::saved(fn () => app(\App\Content\Guides\GuideCatalog::class)->invalidate());
        \Splicewire\Beam\Ux\Models\BeamUxEntry::deleted(fn () => app(\App\Content\Guides\GuideCatalog::class)->invalidate());
        $this->app->booted(function (): void {
            $bindings = app(WorkflowBindingRegistry::class);
            if ($bindings->has('page') && $bindings->resolve('page')->lineageRef !== EntryPublishLifecycle::DEFINITION) {
                throw new \LogicException('The Guide host already binds a different Page lifecycle.');
            }
            $bindings->bind('page', EntryPublishLifecycle::DEFINITION);
            app(\Splicewire\Beam\Sitemap\SitemapSourceRegistry::class)->register(\App\Content\Guides\CalculatorSitemapSource::class);
        });
    }
}
