<?php

namespace App\Console\Commands;

use App\Models\ScrapedStory;
use App\Services\TruyenFullScraper;
use App\Services\TangThuVienScraper;
use App\Services\SSTruyenScraper;
use Illuminate\Console\Command;

class SpyStory extends Command
{
    protected $signature = 'spy:story
                            {url : URL truyện cần crawl}
                            {--chapters : Crawl luôn tất cả chương sau khi lấy meta}
                            {--source=truyenfull : Nguồn (truyenfull|tangthuvien|sstruyen)}
                            {--force-dup : Lưu dù bị trùng title với truyện từ nguồn khác}';

    protected $description = 'Crawl meta + danh sách chương của 1 truyện và lưu vào spy DB';

    public function handle(): int
    {
        $url    = $this->argument('url');
        $source = $this->option('source');

        $this->info("🕷️  Đang crawl truyện: {$url}");

        $scraper = $this->getScraper($source);
        if (!$scraper) {
            $this->error("Nguồn không hợp lệ: {$source}. Chọn: truyenfull | tangthuvien | sstruyen");
            return self::FAILURE;
        }

        $data = $scraper->scrapeStory($url);
        if (!$data) {
            $this->error('Crawl thất bại!');
            return self::FAILURE;
        }

        // ── Kiểm tra trùng title ──────────────────────────────────────────────
        if (!$this->option('force-dup')) {
            $duplicate = ScrapedStory::whereRaw('LOWER(title) = ?', [mb_strtolower($data['title'])])
                ->where('source', '!=', $data['source'])
                ->first();

            if ($duplicate) {
                $this->warn("⚠️  Trùng title với: [{$duplicate->id}] \"{$duplicate->title}\" (nguồn: {$duplicate->source})");
                if (!$this->confirm('Vẫn tiếp tục lưu?', false)) {
                    $this->line('Bỏ qua.');
                    return self::SUCCESS;
                }
            }
        }

        // Lưu hoặc update vào DB
        $chapterUrls = $data['chapter_urls'] ?? [];
        unset($data['chapter_urls']);

        $story = ScrapedStory::updateOrCreate(
            ['source' => $data['source'], 'source_id' => $data['source_id']],
            array_merge($data, ['last_scraped_at' => now()])
        );

        $this->info("✅ Đã lưu: [{$story->id}] {$story->title}");
        $this->line("   Tác giả   : ".($story->author ?? 'N/A'));
        $this->line("   Thể loại  : ".implode(', ', $story->genres ?? []));
        $this->line("   Tổng chương: {$story->total_chapters}");
        $this->line("   URLs chương: ".count($chapterUrls));

        // Crawl chương ngay nếu có --chapters
        if ($this->option('chapters') && !empty($chapterUrls)) {
            $this->info("📖 Bắt đầu crawl ".count($chapterUrls)." chương...");
            $this->crawlChapters($story, $chapterUrls, $scraper);
        } else {
            if (!empty($chapterUrls)) {
                $this->line("\n💡 Chạy lệnh sau để crawl chương:");
                $this->line("   php artisan spy:chapters {$story->id}");
            }
        }

        return self::SUCCESS;
    }

    private function crawlChapters(ScrapedStory $story, array $chapterUrls, $scraper): void
    {
        $chapterDelay = config('crawler.delays.chapter_s');
        $bar          = $this->output->createProgressBar(count($chapterUrls));
        $bar->start();

        foreach ($chapterUrls as $chapterUrl) {
            preg_match('/chuong-(\d+)/i', $chapterUrl, $m);
            $chapterNum = (int) ($m[1] ?? 0);

            // Đánh dấu đang xử lý
            $chapter = \App\Models\ScrapedChapter::updateOrCreate(
                ['scraped_story_id' => $story->id, 'chapter_number' => $chapterNum],
                ['source_url' => $chapterUrl, 'process_status' => 'processing']
            );

            $data = $scraper->scrapeChapter($chapterUrl);
            if (!$data) {
                $chapter->update(['process_status' => 'failed', 'process_note' => 'scrapeChapter() trả về null']);
                $bar->advance();
                sleep($chapterDelay);
                continue;
            }

            $content = $data['content'] ?? '';
            unset($data['content']);

            $chapter->update(array_merge($data, ['process_status' => 'processed', 'process_note' => null]));
            $chapter->writeContent($content);
            $story->increment('scraped_chapters');

            sleep($chapterDelay);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ Đã crawl {$story->scraped_chapters}/{$story->total_chapters} chương.");
    }

    private function getScraper(string $source): ?object
    {
        return match ($source) {
            'truyenfull'  => new TruyenFullScraper(),
            'tangthuvien' => new TangThuVienScraper(),
            'sstruyen'    => new SSTruyenScraper(),
            default       => null,
        };
    }
}
