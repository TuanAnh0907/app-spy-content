<?php

namespace App\Console\Commands;

use App\Models\ScrapedChapter;
use App\Models\ScrapedStory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Console\Command;

class SpySync extends Command
{
    protected $signature = 'spy:sync
                            {--story= : Chỉ sync 1 truyện theo ID trong spy DB}
                            {--all : Sync tất cả truyện đã processed nhưng chưa sync}
                            {--dry-run : Chỉ xem sẽ sync cái gì, không gửi thật}';

    protected $description = 'Sync data từ spy DB sang backend-doctruyen qua API';

    private Client $http;
    private string $apiBase;
    private string $apiToken;

    public function __construct()
    {
        parent::__construct();

        $this->apiBase  = rtrim(config('services.backend_api.url'), '/');
        $this->apiToken = config('services.backend_api.token');

        $this->http = new Client([
            'base_uri' => $this->apiBase,
            'timeout'  => 30,
            'headers'  => [
                'Authorization' => "Bearer {$this->apiToken}",
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN — Không gửi dữ liệu thật');
        }

        // Lấy danh sách truyện cần sync
        $query = ScrapedStory::where('process_status', 'processed')
            ->where('is_synced', false);

        if ($storyId = $this->option('story')) {
            $query->where('id', $storyId);
        }

        $stories = $query->get();

        if ($stories->isEmpty()) {
            $this->info('Không có truyện nào cần sync.');
            $this->line('💡 Hãy đánh dấu process_status = "processed" trước khi sync.');
            return self::SUCCESS;
        }

        $this->info("📡 Sẽ sync {$stories->count()} truyện lên backend...");

        $success = 0;
        $failed  = 0;

        foreach ($stories as $story) {
            $this->line("\n▶ [{$story->id}] {$story->title}");

            $result = $this->syncStory($story, $dryRun);

            if ($result) {
                $success++;
                $this->info("  ✅ Story synced → backend ID: {$result['story_id']}");

                // Sync chương
                $chapters = $story->chapters()->where('is_synced', false)->get();
                $this->line("  📖 Sync {$chapters->count()} chương...");

                $chapterSuccess = 0;
                foreach ($chapters as $chapter) {
                    $chapterResult = $this->syncChapter($story, $chapter, $result['story_id'], $dryRun);
                    if ($chapterResult) {
                        $chapterSuccess++;
                    }
                }

                $this->info("  ✅ Chapters synced: {$chapterSuccess}/{$chapters->count()}");
            } else {
                $failed++;
                $this->error("  ❌ Thất bại");
            }
        }

        $this->newLine();
        $this->info("=== Kết quả sync: {$success} thành công | {$failed} thất bại ===");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function syncStory(ScrapedStory $story, bool $dryRun): ?array
    {
        $payload = [
            'title'       => $story->title,
            'author'      => $story->author,
            'description' => $story->description,
            'status'      => $story->status,
            'genres'      => $story->genres ?? [],
            'source'      => $story->source,
            'source_url'  => $story->source_url,
            // cover_url bỏ qua, xử lý sau
        ];

        if ($dryRun) {
            $this->line('    Payload: '.json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return ['story_id' => 0];
        }

        try {
            $response = $this->http->post('/api/internal/stories', [
                'json' => $payload,
            ]);

            $body           = json_decode($response->getBody(), true);
            $backendStoryId = $body['data']['id'] ?? null;

            if (!$backendStoryId) {
                $this->error('  Backend trả về không có story ID!');
                return null;
            }

            // Đánh dấu đã sync
            $story->update([
                'is_synced'       => true,
                'synced_story_id' => $backendStoryId,
                'synced_at'       => now(),
            ]);

            return ['story_id' => $backendStoryId];

        } catch (RequestException $e) {
            $this->error('  HTTP error: '.$e->getMessage());
            return null;
        }
    }

    private function syncChapter(ScrapedStory $story, ScrapedChapter $chapter, int $backendStoryId, bool $dryRun): bool
    {
        $payload = [
            'story_id'       => $backendStoryId,
            'chapter_number' => $chapter->chapter_number,
            'title'          => $chapter->title,
            'content'        => $chapter->readContent(),
            'word_count'     => $chapter->word_count,
        ];

        if ($dryRun) {
            return true;
        }

        try {
            $response = $this->http->post('/api/internal/chapters', [
                'json' => $payload,
            ]);

            $body             = json_decode($response->getBody(), true);
            $backendChapterId = $body['data']['id'] ?? null;

            $chapter->update([
                'is_synced'         => true,
                'synced_chapter_id' => $backendChapterId,
                'synced_at'         => now(),
            ]);

            return true;

        } catch (RequestException $e) {
            $this->warn("    ⚠️ Chapter {$chapter->chapter_number} failed: ".$e->getMessage());
            return false;
        }
    }
}
