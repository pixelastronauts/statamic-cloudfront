<?php

namespace PixelAstronauts\Statamic\Cloudfront;

use Illuminate\Cache\Repository;
use Illuminate\Contracts\Container\Container;
use Statamic\Providers\AddonServiceProvider;
use Statamic\StaticCaching\StaticCacheManager;

class CloudfrontServiceProvider extends AddonServiceProvider
{
    public function register(): void
    {
        // Register any bindings if needed
    }

    public function boot(): void
    {
        $cloudfrontStrategies = collect(config('statamic.static_caching.strategies'));

        $cloudfrontStrategies->where('driver', 'cloudfront')->each(function ($item, $strategy) {
            $this->app->make(StaticCacheManager::class)->extend($strategy, function (Container $app) use ($strategy) {
                return new CloudfrontCacher(
                    $app->make(Repository::class),
                    $this->getConfig($strategy)
                );
            });
        });

        $this->app['router']->pushMiddlewareToGroup('web', Middleware\CacheControlHeader::class);
    }
}
