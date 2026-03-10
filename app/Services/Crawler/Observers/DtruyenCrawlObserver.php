<?php

namespace App\Services\Crawler\Observers;

use App\Models\DTruyen\Story;
use App\Enums\DtruyenStoryType;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlObservers\CrawlObserver;

class DtruyenCrawlObserver extends CrawlObserver
{
    public function willCrawl(UriInterface $url, ?string $linkText): void
    {
        // echo "Crawling: " . (string) $url . "\n";
    }

    public function crawled(
        UriInterface $url,
        ResponseInterface $response,
        ?UriInterface $foundOnUrl = null,
        ?string $linkText = null
    ): void {
        $urlString = (string) $url;
        echo "\n[CRAWLED OK] " . $urlString . " (Size: " . $response->getBody()->getSize() . ")\n";

        if (str_contains($url->getPath(), '/doc-truyen')) {
            $storyUrl = rtrim($urlString, '/');

            if (empty($url->getQuery())) {
                Story::firstOrCreate(
                    ['url' => $storyUrl],
                    ['type' => DtruyenStoryType::TRANSLATED]
                );
            }
        }
    }

    public function crawlFailed(
        UriInterface $url,
        RequestException $requestException,
        ?UriInterface $foundOnUrl = null,
        ?string $linkText = null
    ): void {
        echo "\n[FAILED] " . (string) $url . " - Error: " . $requestException->getMessage() . "\n";
    }
}
