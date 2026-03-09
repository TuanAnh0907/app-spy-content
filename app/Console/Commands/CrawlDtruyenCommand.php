<?php

namespace App\Console\Commands;

use App\Services\Crawler\Observers\TtvCrawlObserver;
use App\Services\Crawler\Profiles\TtvCrawlProfile;
use App\Services\Crawler\Queues\TtvCrawlQueue;
use Illuminate\Console\Command;
use Spatie\Crawler\Crawler;

class CrawlDtruyenCommand extends Command
{
    protected $signature = 'crawl:dtruyen';
    protected $description = 'Fetch links from DTruyen safely (with delays)';

    public function handle()
    {
        $this->info('Starting Headless DTruyen Crawler...');

        // Thêm mồi nhử nếu queue trống
        if (\App\Models\DtruyenQueue::count() === 0) {
            $this->info('No pending URLs found. Seeding with dtruyen.com');
            \App\Models\DtruyenQueue::create([
                'url' => 'https://dtruyen.com/',
                'status' => 'pending'
            ]);
        } else {
            $this->info('Found pending URLs, resuming from database queue.');
            // Trả lại các link processing bị treo về pending
            \App\Models\DtruyenQueue::where('status', 'processing')->update(['status' => 'pending']);
        }

        while ($job = \App\Models\DtruyenQueue::where('status', 'pending')->first()) {
            $job->update(['status' => 'processing']);
            $this->info("Crawling: {$job->url}");

            try {
                // Dùng custom Stealth NodeJS thay vì Browsershot để 100% qua mặt Cloudflare
                $nodeCmd = "cd /var/www/read-app/spy-doctruyen && node /var/www/read-app/spy-doctruyen/scraper.cjs " . escapeshellarg($job->url);
                $html = shell_exec($nodeCmd);

                if (!$html || strlen(trim($html)) < 1000) {
                    throw new \Exception("Empty HTML returned or blocked by Cloudflare. Output: " . substr((string)$html, 0, 100));
                }
                
                $this->info("-> Fetched HTML length: " . strlen($html));
                $this->extractLinks($html);

                $job->update(['status' => 'completed']);

                // Nghỉ ngơi giữa các vòng (random 30-60 giây) để an toàn tuyệt đối cho IP
                $sleepTime = rand(30, 60);
                $this->info("-> Sleeping for {$sleepTime}s...");
                sleep($sleepTime);

            } catch (\Exception $e) {
                $this->error("Failed to crawl {$job->url}: " . $e->getMessage());
                $job->update([
                    'status' => 'failed',
                    'last_error' => $e->getMessage()
                ]);
            }
        }

        $this->info('Crawling completed.');
    }

    private function extractLinks(string $html)
    {
        // Suppress warnings from invalid HTML
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//a/@href');

        foreach ($links as $link) {
            $href = $link->nodeValue;
            
            // Xử lý link tương đối
            if (str_starts_with($href, '/')) {
                $href = 'https://truyencom.com' . $href;
            }

            // Parser Host & Path
            $parsed = parse_url($href);
            if (!isset($parsed['host']) || 
                (!str_contains($parsed['host'], 'dtruyen.com') && !str_contains($parsed['host'], 'truyencom.com'))
            ) {
                continue;
            }

            $path = $parsed['path'] ?? '/';
            $query = $parsed['query'] ?? null;
            $cleanUrl = rtrim($parsed['scheme'] . '://' . $parsed['host'] . $path, '/');
            if ($query) {
                $cleanUrl .= '?' . $query;
            }

            // 1. Nhận diện link Truyện: Có định dạng "ten-truyen.123"
            $isStory = false;
            if (preg_match('/^[a-z0-9\-]+\.[0-9]+$/', trim($path, '/'))) {
                $isStory = true;
                if (empty($query)) {
                    \App\Models\DtruyenStory::firstOrCreate(
                        ['url' => $cleanUrl],
                        ['type' => \App\Enums\DtruyenStoryType::TRANSLATED]
                    );
                }
            }

            // 2. Lọc link vào Queue
            $allowQueue = true;
            
            // Bỏ qua tag, page tĩnh
            if (str_starts_with($path, '/tag/') || str_starts_with($path, '/author/')) {
                $allowQueue = false;
            }

            if ($allowQueue) {
                if (!\App\Models\DtruyenQueue::where('url', $cleanUrl)->exists()) {
                    \App\Models\DtruyenQueue::create([
                        'url' => $cleanUrl,
                        'status' => 'pending'
                    ]);
                }
            }
        }
    }
}
