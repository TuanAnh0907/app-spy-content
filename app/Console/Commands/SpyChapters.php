<?php

namespace App\Console\Commands;

use App\Models\ScrapedChapter;
use App\Models\ScrapedStory;
use App\Services\TruyenFullScraper;
use App\Services\TangThuVienScraper;
use App\Services\SSTruyenScraper;
use Illuminate\Console\Command;

class SpyChapters extends Command
{
    protected $signature = 'spy:chapters
                            {story_id : ID truyện trong spy DB}
                            {--from=1 : Crawl từ chương số bao nhiêu}
                            {--to= : Đến chương số bao nhiêu (mặc định: tất cả)}
                            {--force : Crawl lại dù đã có dữ liệu}';

    protected $description = 'Crawl toàn bộ chương của 1 truyện và lưu vào spy DB';

    public function handle(): int
    {
        $storyId = $this->argument('story_id');
        $story   = ScrapedStory::findOrFail($storyId);

        $this->info("📖 Crawl chương: [{$story->id}] {$story->title}");
        $this->line("   Nguồn: {$story->source} | URL: {$story->source_url}");

        $scraper = $this->getScraper($story->source);
        if (!$scraper) {
            $this->error("Scraper không hỗ trợ nguồn: {$story->source}");
            return self::FAILURE;
        }

        // Lấy lại danh sách chapter URLs từ trang truyện
        $this->line("🔍 Đang lấy danh sách chương...");
        $storyData = $scraper->scrapeStory($story->source_url);
        if (!$storyData || empty($storyData['chapter_urls'])) {
            $this->error('Không lấy được danh sách chương!');
            return self::FAILURE;
        }

        $chapterUrls = $storyData['chapter_urls'];
        $fromChapter = (int) $this->option('from');
        $toChapter   = $this->option('to') ? (int) $this->option('to') : null;
        $force       = $this->option('force');

        // Filter theo --from / --to
        if ($fromChapter > 1 || $toChapter) {
            $chapterUrls = array_filter($chapterUrls, function ($url) use ($fromChapter, $toChapter) {
                preg_match('/chuong-(\d+)/i', $url, $m);
                $num = (int) ($m[1] ?? 0);
                return $num >= $fromChapter && ($toChapter === null || $num <= $toChapter);
            });
            $chapterUrls = array_values($chapterUrls);
        }

        $this->info("📋 Tổng: " . count($chapterUrls) . " chương cần crawl");

        $bar      = $this->output->createProgressBar(count($chapterUrls));
        $success  = 0;
        $skipped  = 0;
        $failed   = 0;

        $bar->start();

        foreach ($chapterUrls as $url) {
            preg_match('/chuong-(\d+)/i', $url, $m);
            $chapterNum = (int) ($m[1] ?? 0);

            if (!$force) {
                // Skip nếu đã processed, hoặc đang processing mà chưa stale
                $staleMinutes = (int) env('CRAWLER_STALE_MINUTES', 60);

                $shouldSkip = ScrapedChapter::where('scraped_story_id', $story->id)
                    ->where('chapter_number', $chapterNum)
                    ->where(function ($q) use ($staleMinutes) {
                        $q->where('process_status', 'processed')
                          ->orWhere(function ($q2) use ($staleMinutes) {
                              $q2->where('process_status', 'processing')
                                 ->where('updated_at', '>=', now()->subMinutes($staleMinutes));
                          });
                    })
                    ->exists();

                if ($shouldSkip) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }
            }

            // Đánh dấu đang xử lý
            $chapter = ScrapedChapter::updateOrCreate(
                ['scraped_story_id' => $story->id, 'chapter_number' => $chapterNum],
                ['source_url' => $url, 'process_status' => 'processing']
            );

            $data = $scraper->scrapeChapter($url);
            if (!$data) {
                $failed++;
                $chapter->update(['process_status' => 'failed', 'process_note' => 'scrapeChapter() trả về null']);
                $bar->advance();
                continue;
            }

            // Tách content ra để lưu file, không đẩy vào DB
            $content = $data['content'] ?? '';
            unset($data['content']);

            $chapter->update(array_merge($data, ['process_status' => 'processed', 'process_note' => null]));

            // Ghi nội dung ra file
            $chapter->writeContent($content);

            $success++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // Cập nhật số chương đã crawl
        $totalScraped = ScrapedChapter::where('scraped_story_id', $story->id)->count();
        $story->update([
            'scraped_chapters' => $totalScraped,
            'last_scraped_at'  => now(),
        ]);

        $this->info("✅ Kết quả: {$success} thành công | {$skipped} bỏ qua | {$failed} thất bại");
        $this->line("   Tổng đã crawl: {$totalScraped}/{$story->total_chapters} chương");

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
