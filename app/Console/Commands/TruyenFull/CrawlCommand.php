<?php

namespace App\Console\Commands\TruyenFull;

use App\Models\TruyenFull\Queue;
use App\Models\TruyenFull\Story;
use Illuminate\Console\Command;

/**
 * Command Thread 1: Crawl link từ truyenfull.vn, phân loại URL và lưu vào DB queue.
 * Chạy vòng lặp liên tục qua bảng tf_queues.
 * Khi phát hiện link truyện, lưu vào tf_stories để Thread 2 xử lý.
 */
class CrawlCommand extends Command
{
    protected $signature   = 'crawl:truyenfull';
    protected $description = 'Thread 1 - Crawl links từ truyenfull.vn và lưu vào tf_queues';

    // Danh sách domain hợp lệ của TruyenFull (có nhiều clone domain)
    private const ALLOWED_DOMAINS = [
        'truyenfull.vn',
        'truyenfull.com',
        'truyenfull.vision',
        'www.truyenfull.vn',
        'www.truyenfull.com',
        'www.truyenfull.vision',
    ];

    public function handle(): void
    {
        $this->info('Starting TruyenFull Crawler (Thread 1)...');

        // Trả lại các link bị kẹt "processing" về "pending"
        Queue::where('status', 'processing')->update(['status' => 'pending']);

        if (Queue::where('status', 'pending')->count() === 0) {
            if (Queue::count() === 0) {
                $this->info('Queue trống. Seed URL gốc truyenfull.vision...');
                Queue::create(['url' => 'https://truyenfull.vision/', 'status' => 'pending']);
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

        $this->info('Hoàn tất quét TruyenFull.');
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

            // Chuẩn hóa URL tương đối → tuyệt đối
            if (str_starts_with($href, '/')) {
                $href = 'https://'.($parsed['host'] ?? 'truyenfull.vision').$href;
            }

            $parsed = parse_url($href);
            if (!isset($parsed['host']) || !in_array($parsed['host'], self::ALLOWED_DOMAINS)) {
                continue;
            }

            $path     = $parsed['path'] ?? '/';
            $query    = $parsed['query'] ?? null;
            $cleanUrl = rtrim($parsed['scheme'].'://'.$parsed['host'].$path, '/');
            if ($query) {
                $cleanUrl .= '?'.$query;
            }

            // Nhận diện link truyện: /ten-truyen/ (slug ngắn, không chứa /chuong- hay /trang-)
            if ($this->isStoryUrl($path, $query)) {
                Story::firstOrCreate(['url' => rtrim($cleanUrl, '/')]);
                // Không cần đưa link truyện vào queue nữa
                continue;
            }

            // Bỏ qua link chương, link tác giả, ảnh, tag...
            if ($this->shouldSkip($path)) {
                continue;
            }

            // Đưa vào queue nếu chưa có
            if (!Queue::where('url', $cleanUrl)->exists()) {
                Queue::create(['url' => $cleanUrl, 'status' => 'pending']);
            }
        }
    }

    /**
     * Nhận diện link trang chủ truyện TruyenFull.
     * Dạng: /ten-truyen/ (chỉ 1 segment, không phân trang)
     */
    private function isStoryUrl(string $path, ?string $query): bool
    {
        if (!empty($query)) {
            return false;
        }

        $segments = array_filter(explode('/', trim($path, '/')));

        // Đúng 1 segment, không chứa keyword hệ thống
        if (count($segments) !== 1) {
            return false;
        }

        $slug     = reset($segments);
        $excluded = ['the-loai', 'tag', 'tac-gia', 'tim-kiem', 'login', 'register', 'dich-gia'];

        return !in_array($slug, $excluded) && preg_match('/^[a-z0-9\-]+$/', $slug);
    }

    /**
     * Bỏ qua các link không cần crawl.
     */
    private function shouldSkip(string $path): bool
    {
        $skipPrefixes = ['/chuong-', '/trang-', '/tag/', '/tac-gia/', '/tim-kiem', '/login', '/register', '/dich-gia/'];

        foreach ($skipPrefixes as $prefix) {
            if (str_contains($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
