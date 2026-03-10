<?php

namespace App\Console\Commands\DTruyen;

use App\Models\DTruyen\Queue;
use App\Models\DTruyen\Story;
use App\Enums\DtruyenStoryType;
use Illuminate\Console\Command;

/**
 * Command Thread 1: Crawl link từ truyencom.com, phân loại URL và lưu vào DB queue.
 * Chạy vòng lặp liên tục qua bảng dtruyen_queues.
 */
class CrawlCommand extends Command
{
    protected $signature   = 'crawl:dtruyen';
    protected $description = 'Thread 1 - Crawl links từ truyencom.com và lưu vào dtruyen_queues';

    public function handle(): void
    {
        $this->info('Starting DTruyen Crawler (Thread 1)...');

        // Trả lại các link bị kẹt "processing" về "pending"
        Queue::query()
            ->where('status', 'processing')
            ->update([
                'status' => 'pending'
            ]);

        if (Queue::query()->where('status', 'pending')->count() === 0) {
            if (Queue::count() === 0) {
                $this->info('Queue trống. Seed URL gốc truyencom.com...');
                Queue::create(['url' => 'https://truyencom.com/', 'status' => 'pending']);
            } else {
                $this->info('Queue đã hết pending. Đợi 5 phút trước khi quét lại một vòng mới...');
                sleep(300);
                Queue::whereIn('status', ['completed', 'failed'])->update(['status' => 'pending']);
                $this->info('Đã reset toàn bộ queue về pending để tìm truyện mới.');
            }
        }

        while ($job = Queue::where('status', 'pending')->first()) {
            $job->update(['status' => 'processing']);
            $this->info("Crawling: {$job->url}");

            try {
                $nodeCmd = "cd ".escapeshellarg(base_path())." && node ".escapeshellarg(base_path('scraper.cjs'))." ".escapeshellarg($job->url);
                $html    = shell_exec($nodeCmd);

                if (!$html || strlen(trim($html)) < 1000) {
                    throw new \Exception("HTML rỗng hoặc bị Cloudflare chặn. Output: ".substr((string) $html, 0, 100));
                }

                $this->info("-> HTML length: ".strlen($html));
                $this->extractLinks($html);

                $job->update(['status' => 'completed']);

                $sleepTime = rand(30, 60);
                $this->info("-> Sleeping {$sleepTime}s...");
                sleep($sleepTime);

            } catch (\Exception $e) {
                $this->error("Lỗi {$job->url}: ".$e->getMessage());
                $job->update(['status' => 'failed', 'last_error' => $e->getMessage()]);
            }
        }

        $this->info('Hoàn tất quét DTruyen.');
    }

    private function extractLinks(string $html): void
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//a/@href');

        foreach ($links as $link) {
            $href = $link->nodeValue;

            if (str_starts_with($href, '/')) {
                $href = 'https://truyencom.com'.$href;
            }

            $parsed = parse_url($href);
            if (!isset($parsed['host']) ||
                (!str_contains($parsed['host'], 'dtruyen.com') && !str_contains($parsed['host'], 'truyencom.com'))
            ) {
                continue;
            }

            $path     = $parsed['path'] ?? '/';
            $query    = $parsed['query'] ?? null;
            $cleanUrl = rtrim($parsed['scheme'].'://'.$parsed['host'].$path, '/');
            if ($query) {
                $cleanUrl .= '?'.$query;
            }

            // Nhận diện link Truyện: định dạng "ten-truyen.123"
            if (preg_match('/^[a-z0-9\-]+\.[0-9]+$/', trim($path, '/')) && empty($query)) {
                Story::firstOrCreate(
                    ['url' => $cleanUrl],
                    ['type' => DtruyenStoryType::TRANSLATED]
                );
            }

            // Bỏ qua tag, author
            if (str_starts_with($path, '/tag/') || str_starts_with($path, '/author/')) {
                continue;
            }

            if (!Queue::where('url', $cleanUrl)->exists()) {
                Queue::create([
                    'url'    => $cleanUrl,
                    'status' => 'pending'
                ]);
            }
        }
    }
}
