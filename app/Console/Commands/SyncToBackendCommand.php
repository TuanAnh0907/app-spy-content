<?php

namespace App\Console\Commands;

use App\Models\DTruyen\Chapter as DtruyenChapter;
use App\Models\DTruyen\Story as DtruyenStory;
use App\Models\TruyenFull\Chapter as TruyenFullChapter;
use App\Models\TruyenFull\Story as TruyenFullStory;
use App\Enums\StoryStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncToBackendCommand extends Command
{
    protected $signature   = 'sync:backend {--limit=50 : Số lượng record mỗi lần sync}';
    protected $description = 'Đồng bộ data (story & chapter) đã crawl sang backend lớn thông qua API';

    public function handle(): void
    {
        $limit      = (int) $this->option('limit');
        $backendUrl = rtrim(config('app.backend_api_url'), '/');
        $token      = config('app.backend_api_token');

        if (!$backendUrl || !$token) {
            $this->error('Thiếu cấu hình BACKEND_API_URL hoặc BACKEND_API_TOKEN trong .env');
            return;
        }

        $this->info("Bắt đầu đồng bộ sang: {$backendUrl}");

        $this->syncStories($backendUrl, $token, $limit);
        $this->syncChapters($backendUrl, $token, $limit);

        $this->info("Done sync đợt này.");
    }

    private function syncStories(string $backendUrl, string $token, int $limit): void
    {
        $this->info("--- Đồng bộ Stories ---");

        // Gộp DTruyen và TruyenFull
        $dtStories = DtruyenStory::where('status', StoryStatus::COMPLETED)
            ->whereNull('synced_at')
            ->whereNotNull('title')
            ->limit($limit / 2)
            ->get();

        $tfStories = TruyenFullStory::where('status', StoryStatus::COMPLETED)
            ->whereNull('synced_at')
            ->whereNotNull('title')
            ->limit($limit / 2)
            ->get();

        $allStories = $dtStories->concat($tfStories);

        if ($allStories->isEmpty()) {
            $this->info("Không có Story nào cần đồng bộ.");
            return;
        }

        $bar = $this->output->createProgressBar($allStories->count());
        $bar->start();

        foreach ($allStories as $story) {
            // Chuẩn bị payload
            $payload = [
                'title'       => $story->title,
                'slug'        => $story->slug,
                'author'      => $story->author,
                'description' => '', // Hiện tại crawler chưa lấy
                'cover_image' => null, // Hiện tại crawler chưa lấy
                'status'      => 0, // 0 = Đang ra
            ];

            try {
                $response = Http::withToken($token)
                    ->timeout(10)
                    ->post("{$backendUrl}/api/v1/sync/story", $payload);

                if ($response->successful()) {
                    $story->update(['synced_at' => now()]);
                } else {
                    Log::channel('sync')->error("Lỗi sync story {$story->id}: ".$response->body());
                }
            } catch (\Exception $e) {
                Log::channel('sync')->error("Exception sync story {$story->id}: ".$e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function syncChapters(string $backendUrl, string $token, int $limit): void
    {
        $this->info("--- Đồng bộ Chapters ---");

        // Chỉ lấy chapter có content_path khác null, chưa sync, và story ĐÃ sync
        $dtChapters = DtruyenChapter::whereNotNull('content_path')
            ->whereNull('synced_at')
            ->whereHas('story', function ($q) {
                $q->whereNotNull('synced_at');
            })
            ->limit($limit / 2)
            ->get();

        $tfChapters = TruyenFullChapter::whereNotNull('content_path')
            ->whereNull('synced_at')
            ->whereHas('story', function ($q) {
                $q->whereNotNull('synced_at');
            })
            ->limit($limit / 2)
            ->get();

        $allChapters = $dtChapters->concat($tfChapters);

        if ($allChapters->isEmpty()) {
            $this->info("Không có Chapter nào cần đồng bộ.");
            return;
        }

        $bar = $this->output->createProgressBar($allChapters->count());
        $bar->start();

        foreach ($allChapters as $chapter) {
            $story = $chapter->story;

            // Tìm số chương từ chapter_title hoặc mặc định
            // VD title là "Chương 5: xyz", cố gắng lấy số 5
            $chapterNumber = $this->extractChapterNumber($chapter->chapter_title, $chapter->order_index ?? 0);

            $payload = [
                'story_slug'     => $story->slug,
                'chapter_number' => $chapterNumber,
                'title'          => $chapter->chapter_title,
                'content_path'   => $chapter->content_path,
                'word_count'     => 0, // Sẽ tính nếu cần thiết
            ];

            try {
                $response = Http::withToken($token)
                    ->timeout(10)
                    ->post("{$backendUrl}/api/v1/sync/chapter", $payload);

                if ($response->successful()) {
                    $chapter->update(['synced_at' => now()]);
                } else {
                    Log::channel('sync')->error("Lỗi sync chapter {$chapter->id}: ".$response->body());
                }
            } catch (\Exception $e) {
                Log::channel('sync')->error("Exception sync chapter {$chapter->id}: ".$e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function extractChapterNumber(?string $title, int $fallback): int
    {
        if (!$title) {
            return $fallback;
        }

        // Cố gắng tìm số sau chữ "Chương"
        preg_match('/(?:Chương|Quyển|Thiên)[\s\:]*([0-9]+)/iu', $title, $matches);
        if (isset($matches[1])) {
            return (int) $matches[1];
        }

        return $fallback;
    }
}
