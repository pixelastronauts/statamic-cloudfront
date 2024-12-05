<?php

namespace PixelAstronauts\Statamic\Cloudfront;

use Illuminate\Cache\Repository;
use Illuminate\Http\Request;
use Statamic\StaticCaching\Cachers\AbstractCacher;
use Statamic\Facades\Blink;

class CloudfrontCacher extends AbstractCacher
{
    private $cloudfront;
    protected $cache;

    public function __construct(Repository $cache, array $config)
    {
        parent::__construct($cache, $config);
        $this->cloudfront = new Cloudfront($config);
        $this->cache = $cache;
    }

    public function cachePage(Request $request, $content)
    {
        $url = $this->getUrl($request);

        if ($this->isExcluded($url)) {
            return;
        }

        // Normalize the content (handles Response objects)
        $content = $this->normalizeContent($content);

        // Set cache headers via Blink
        Blink::put('statamic-cloudfront', 'max-age=' . $this->config('expiry', '2592000') . ', public');

        // Store in local cache
        $key = $this->makeHash($url);
        $this->cache->put(
            $this->normalizeKey($key),
            $content,
            $this->config('expiry', 2592000)
        );

        // Store URL mapping
        $this->cacheUrl($key, $url);
    }

    public function getCachedPage(Request $request): ?string
    {
        return null;
    }

    public function flush(): void
    {
        // Invalidate CloudFront cache
        $this->cloudfront->flush();

        // Clear local cache
        $this->getUrls()->each(function ($url, $key) {
            $this->cache->forget($this->normalizeKey($key));
        });

        // Clear URL mappings
        $this->flushUrls();
    }

    public function invalidateUrl($urls): void
    {
        $urls = is_string($urls) ? [$urls] : $urls;

        // Invalidate in CloudFront
        $this->cloudfront->delete($urls);

        foreach ($urls as $url) {
            $key = $this->getUrls()->flip()->get($url);

            if (!$key) {
                continue;
            }

            // Clear from local cache
            $this->cache->forget($this->normalizeKey($key));

            // Remove URL mapping
            $this->forgetUrl($key);
        }
    }
}
