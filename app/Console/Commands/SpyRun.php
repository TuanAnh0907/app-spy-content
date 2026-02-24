<?php

namespace App\Console\Commands;

use App\Models\ScrapedStory;
use App\Services\TruyenFullScraper;
use App\Services\TangThuVienScraper;
use App\Services\SSTruyenScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SpyRun extends Command
{
    protected $signature = 'spy:run
                            {source=truyenfull : Nguồn cần crawl (truyenfull|tangthuvien|sstruyen)}
                            {--limit=20 : Số truyện tối đa cần crawl}
                            {--pages=1 : Số trang danh sách cần quét}
                            {--chapters : Crawl luôn chương sau khi lấy meta}';

    protected $description = 'Crawl hàng loạt truyện từ 1 nguồn (trang danh sách)';

    public function handle(): int
    {
        $source  = $this->argument('source');
        $limit   = (int) $this->option('limit');
        $pages   = (int) $this->option('pages');

        $this->info("🚀 Bắt đầu crawl hàng loạt từ: {$source}");
        $this->line("   Limit: {$limit} truyện | Quét {$pages} trang danh sách");

        $scraper = $this->getScraper($source);
        if (!$scraper) {
            $this->error("Scraper không hỗ trợ nguồn: {$source}. Chọn: truyenfull | tangthuvien | sstruyen");
            return self::FAILURE;
        }

        // Sleep giữa các story (giây) — ngoài delay per-request đã có trong BaseScraperService
        $storyDelay = (int) env('CRAWLER_STORY_DELAY_S', 20);

        // Thu thập URLs từ các trang danh sách
        $storyUrls = [];
        for ($page = 1; $page <= $pages; $page++) {
            $this->line("📋 Đang quét trang danh sách {$page}/{$pages}...");
            $urls      = $scraper->getStoryListUrls($page);
            $storyUrls = array_merge($storyUrls, $urls);
        }
        $storyUrls = array_values(array_unique($storyUrls));

        // Skip: đã processed, hoặc đang processing mà chưa stale
        // Processing quá CRAWLER_STALE_MINUTES phút (job trước crash) thì crawl lại
        $staleMinutes = (int) env('CRAWLER_STALE_MINUTES', 60);

        $skipUrls = ScrapedStory::where('source', $source)
            ->where(function ($q) use ($staleMinutes) {
                $q->where('process_status', 'processed')
                  ->orWhere(function ($q2) use ($staleMinutes) {
                      $q2->where('process_status', 'processing')
                         ->where('updated_at', '>=', now()->subMinutes($staleMinutes));
                  });
            })
            ->pluck('source_url')
            ->toArray();

        $storyUrls = array_filter($storyUrls, fn($u) => !in_array($u, $skipUrls));
        $storyUrls = array_slice(array_values($storyUrls), 0, $limit);

        if (empty($storyUrls)) {
            $this->info('Không có truyện mới. Tất cả đã được crawl.');
            return self::SUCCESS;
        }

        $this->info("🕷️  Bắt đầu crawl " . count($storyUrls) . " truyện...");
        $bar = $this->output->createProgressBar(count($storyUrls));
        $bar->start();

        // Lấy sẵn tất cả title đã có trong DB (lowercase) để check dup
        $existingTitles = ScrapedStory::pluck('title')
            ->map(fn($t) => mb_strtolower($t))
            ->toArray();

        foreach ($storyUrls as $url) {
            $data = $scraper->scrapeStory($url);
            if (!$data) {
                Log::warning("[spy:run] scrapeStory() thất bại: {$url}");
                $bar->advance();
                continue;
            }

            // ── Kiểm tra trùng title từ nguồn khác ─────────────────
            $titleLower = mb_strtolower($data['title']);
            if (in_array($titleLower, $existingTitles)) {
                $existing = ScrapedStory::whereRaw('LOWER(title) = ?', [$titleLower])
                    ->where('source', '!=', $data['source'])
                    ->first();

                if ($existing) {
                    Log::warning("[spy:run] Bỏ qua \"{$data['title']}\" — trùng với [{$existing->id}] nguồn {$existing->source}");
                    $bar->advance();
                    continue;
                }
            }

            $chapterUrls = $data['chapter_urls'] ?? [];
            unset($data['chapter_urls']);

            // Đánh dấu đang xử lý
            $story = ScrapedStory::updateOrCreate(
                ['source' => $data['source'], 'source_id' => $data['source_id']],
                array_merge($data, ['last_scraped_at' => now(), 'process_status' => 'processing'])
            );

            $existingTitles[] = $titleLower; // cập nhật list để tránh crawl trùng trong cùng batch

            // Crawl chương nếu yêu cầu
            if ($this->option('chapters') && !empty($chapterUrls)) {
                $chapterSuccess = 0;
                foreach ($chapterUrls as $chapterUrl) {
                    $chapterData = $scraper->scrapeChapter($chapterUrl);

                    preg_match('/chuong-(\d+)/i', $chapterUrl, $m);
                    $chapterNum = (int) ($m[1] ?? 0);

                    $chapterRecord = \App\Models\ScrapedChapter::updateOrCreate(
                        ['scraped_story_id' => $story->id, 'chapter_number' => $chapterNum],
                        ['source_url' => $chapterUrl, 'process_status' => 'processing']
                    );

                    if (!$chapterData) {
                        $chapterRecord->update(['process_status' => 'failed', 'process_note' => 'scrapeChapter() trả về null']);
                        continue;
                    }

                    $content = $chapterData['content'] ?? '';
                    unset($chapterData['content']);

                    $chapterRecord->update(array_merge($chapterData, ['process_status' => 'processed', 'process_note' => null]));
                    $chapterRecord->writeContent($content);
                    $chapterSuccess++;
                }

                $story->update(['scraped_chapters' => $chapterSuccess]);
            }

            // Đánh dấu story đã processed
            $story->update(['process_status' => 'processed']);

            // Sleep giữa các story để tránh bị rate-limit
            sleep($storyDelay);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $total = ScrapedStory::where('source', $source)->count();
        $this->info("✅ Hoàn thành! Tổng trong DB: {$total} truyện từ {$source}");

        return self::SUCCESS;
    }

    private function getScraper(string $source): ?object
    {
        return match($source) {
            'truyenfull'  => new TruyenFullScraper(),
            'tangthuvien' => new TangThuVienScraper(),
            'sstruyen'    => new SSTruyenScraper(),
            default       => null,
        };
    }
}
