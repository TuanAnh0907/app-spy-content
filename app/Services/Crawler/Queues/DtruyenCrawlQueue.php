<?php

namespace App\Services\Crawler\Queues;

use App\Models\DTruyen\Queue;
use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlQueues\CrawlQueue;
use Spatie\Crawler\CrawlUrl;

class DtruyenCrawlQueue implements CrawlQueue
{
    public function add(CrawlUrl $url): CrawlQueue
    {
        $urlString = rtrim((string) $url->url, '/');

        if (!$this->has($url)) {
            Queue::create(['url' => $urlString, 'status' => 'pending']);
        }

        return $this;
    }

    public function has(CrawlUrl|UriInterface $crawlUrl): bool
    {
        $url = $crawlUrl instanceof CrawlUrl ? (string) $crawlUrl->url : (string) $crawlUrl;

        return Queue::where('url', rtrim($url, '/'))->exists();
    }

    public function hasPendingUrls(): bool
    {
        return Queue::where('status', 'pending')->exists();
    }

    public function getUrlById($id): CrawlUrl
    {
        $record   = Queue::findOrFail($id);
        $uri      = new \GuzzleHttp\Psr7\Uri($record->url);
        $crawlUrl = CrawlUrl::create($uri);
        $crawlUrl->setId($record->id);

        return $crawlUrl;
    }

    public function getPendingUrl(): ?CrawlUrl
    {
        $record = Queue::where('status', 'pending')->first();

        if (!$record) {
            return null;
        }

        $record->update(['status' => 'processing']);

        $uri      = new \GuzzleHttp\Psr7\Uri($record->url);
        $crawlUrl = CrawlUrl::create($uri);
        $crawlUrl->setId($record->id);

        return $crawlUrl;
    }

    public function hasAlreadyBeenProcessed(CrawlUrl $url): bool
    {
        $urlString = rtrim((string) $url->url, '/');

        return Queue::where('url', $urlString)
            ->whereIn('status', ['completed', 'failed'])
            ->exists();
    }

    public function markAsProcessed(CrawlUrl $crawlUrl): void
    {
        Queue::where('url', rtrim((string) $crawlUrl->url, '/'))->update(['status' => 'completed']);
    }

    public function markAsFailed(CrawlUrl $crawlUrl, string $error = ''): void
    {
        Queue::where('url', rtrim((string) $crawlUrl->url, '/'))
            ->update(['status' => 'failed', 'last_error' => $error]);
    }

    public function getProcessedUrlCount(): int
    {
        return Queue::whereIn('status', ['completed', 'failed'])->count();
    }
}
