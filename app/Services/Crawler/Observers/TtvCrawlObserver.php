<?php

namespace App\Services\Crawler\Observers;

use App\Models\TtvStory;
use App\Enums\TtvStoryType;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlObservers\CrawlObserver;

class TtvCrawlObserver extends CrawlObserver
{
    /**
     * Called when the crawler will crawl the url.
     */
    public function willCrawl(UriInterface $url, ?string $linkText): void
    {
        // echo "Crawling: " . (string) $url . "\n";
    }

    /**
     * Called when the crawler has crawled the given url successfully.
     */
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
                TtvStory::firstOrCreate(
                    ['url' => $storyUrl],
                    ['type' => TtvStoryType::TRANSLATED] 
                );
            }
        }
    }

    /**
     * Called when the crawler had a problem crawling the given url.
     */
    public function crawlFailed(
        UriInterface $url,
        RequestException $requestException,
        ?UriInterface $foundOnUrl = null,
        ?string $linkText = null
    ): void {
        echo "\n[FAILED] " . (string) $url . " - Error: " . $requestException->getMessage() . "\n";
    }
}
