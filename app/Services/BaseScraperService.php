<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;
use App\Models\ScrapeLog;
use Illuminate\Support\Facades\Log;

abstract class BaseScraperService
{
    protected Client $http;
    protected string $source;

    public function __construct()
    {
        $this->http = new Client([
            'timeout'         => config('crawler.timeout'),
            'connect_timeout' => 10,
            'headers'         => [
                'User-Agent'      => config('crawler.user_agent'),
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'vi-VN,vi;q=0.9,en;q=0.8',
            ],
            'verify'          => false,
        ]);
    }

    /**
     * Fetch HTML từ URL, trả về Crawler object.
     * Tự động retry nếu thất bại.
     */
    protected function fetch(string $url, string $type = 'story'): ?Crawler
    {
        $retries   = config('crawler.retries');
        $delay     = config('crawler.delay_ms');
        $startTime = microtime(true);

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                // Rate-limit: sleep trước mỗi request (kể cả lần đầu)
                usleep($delay * 1000 * $attempt);

                $response = $this->http->get($url);
                $html     = (string) $response->getBody();
                $duration = (int) ((microtime(true) - $startTime) * 1000);

                $this->log($type, $url, 'success', null, $response->getStatusCode(), $duration);

                return new Crawler($html, $url);

            } catch (RequestException $e) {
                $httpCode = $e->getResponse()?->getStatusCode();
                $message  = $e->getMessage();

                Log::warning("[{$this->source}] Attempt {$attempt}/{$retries} failed: {$url} — {$message}");

                if ($attempt === $retries) {
                    $duration = (int) ((microtime(true) - $startTime) * 1000);
                    $this->log($type, $url, 'failed', $message, $httpCode, $duration);
                    return null;
                }
            }
        }

        return null;
    }

    /**
     * Ghi log vào DB
     */
    protected function log(
        string $type,
        string $url,
        string $status,
        ?string $message = null,
        ?int $httpCode = null,
        ?int $durationMs = null
    ): void {
        ScrapeLog::create([
            'source'      => $this->source,
            'type'        => $type,
            'url'         => $url,
            'status'      => $status,
            'message'     => $message,
            'http_code'   => $httpCode,
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Crawl meta + danh sách chương của 1 truyện từ URL
     */
    abstract public function scrapeStory(string $url): ?array;

    /**
     * Crawl nội dung 1 chương từ URL
     */
    abstract public function scrapeChapter(string $url): ?array;

    /**
     * Lấy danh sách URL truyện từ trang danh sách (dùng cho crawl hàng loạt)
     */
    abstract public function getStoryListUrls(int $page = 1): array;
}
