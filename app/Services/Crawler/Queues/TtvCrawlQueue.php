<?php

namespace App\Services\Crawler\Queues;

use App\Models\TtvQueue;
use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlQueues\CrawlQueue;
use Spatie\Crawler\CrawlUrl;

class TtvCrawlQueue implements CrawlQueue
{
    /**
     * @param CrawlUrl $url
     * @return CrawlQueue
     */
    public function add(CrawlUrl $url): CrawlQueue
    {
        $urlString = (string) $url->url;

        // Xóa trailing slash nếu có để chuẩn hoá url
        $urlString = rtrim($urlString, '/');

        if (!$this->has($url)) {
            TtvQueue::create([
                'url' => $urlString,
                'status' => 'pending'
            ]);
        }

        return $this;
    }

    /**
     * @param CrawlUrl|UriInterface $crawlUrl
     * @return bool
     */
    public function has(CrawlUrl|UriInterface $crawlUrl): bool
    {
        $url = $crawlUrl instanceof CrawlUrl ? (string) $crawlUrl->url : (string) $crawlUrl;
        $url = rtrim($url, '/');
        
        return TtvQueue::where('url', $url)->exists();
    }

    /**
     * @return bool
     */
    public function hasPendingUrls(): bool
    {
        return TtvQueue::where('status', 'pending')->exists();
    }

    /**
     * @param mixed $id
     * @return CrawlUrl
     */
    public function getUrlById($id): CrawlUrl
    {
        $record = TtvQueue::findOrFail($id);
        
        $uri = new \GuzzleHttp\Psr7\Uri($record->url);
        $crawlUrl = CrawlUrl::create($uri);
        $crawlUrl->setId($record->id);
        
        return $crawlUrl;
    }

    /**
     * @return CrawlUrl|null
     */
    public function getPendingUrl(): ?CrawlUrl
    {
        $record = TtvQueue::where('status', 'pending')->first();

        if (!$record) {
            return null;
        }

        // Đánh dấu processing
        $record->update(['status' => 'processing']);

        $uri = new \GuzzleHttp\Psr7\Uri($record->url);
        $crawlUrl = CrawlUrl::create($uri);
        $crawlUrl->setId($record->id);

        return $crawlUrl;
    }

    /**
     * @param CrawlUrl $url
     * @return bool
     */
    public function hasAlreadyBeenProcessed(CrawlUrl $url): bool
    {
        $urlString = rtrim((string) $url->url, '/');
        return TtvQueue::where('url', $urlString)
            ->whereIn('status', ['completed', 'failed'])
            ->exists();
    }

    /**
     * @param CrawlUrl $crawlUrl
     * @return void
     */
    public function markAsProcessed(CrawlUrl $crawlUrl): void
    {
        $urlString = rtrim((string) $crawlUrl->url, '/');
        TtvQueue::where('url', $urlString)->update(['status' => 'completed']);
    }

    /**
     * Optional custom method for failure
     */
    public function markAsFailed(CrawlUrl $crawlUrl, string $error = ''): void
    {
        $urlString = rtrim((string) $crawlUrl->url, '/');
        TtvQueue::where('url', $urlString)->update([
            'status' => 'failed',
            'last_error' => $error
        ]);
    }

    /**
     * @return int
     */
    public function getProcessedUrlCount(): int
    {
        return TtvQueue::whereIn('status', ['completed', 'failed'])->count();
    }
}
