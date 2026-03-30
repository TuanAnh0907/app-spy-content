<?php

namespace App\Jobs\TruyenFull;

use App\Models\TruyenFull\Chapter;
use App\Models\TruyenFull\Story;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job quản lý việc cào chương theo từng batch 10 chương nối tiếp cho TruyenFull.
 */
class CrawlStoryChapterBatchJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 600;

    public function __construct(
        public Story $story,
        public int $startOrderIndex = 1,
        public int $batchSize = 10
    ) {
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        // 1. Tìm pattern URL từ các chương đã có
        $sampleChapters = Chapter::query()
            ->where('story_id', $this->story->id)
            ->whereNotNull('chapter_url')
            ->orderBy('order_index')
            ->limit(10)
            ->get();

        $pattern = $this->detectUrlPattern($sampleChapters);

        // 2. Chuẩn bị 10 chương tiếp theo
        $chaptersToCrawl = [];

        for ($i = 0; $i < $this->batchSize; $i++) {
            $orderIndex = $this->startOrderIndex + $i;

            // Tìm chương trong DB hoặc tự sinh nếu chưa có link
            $chapter = Chapter::query()
                ->where('story_id', $this->story->id)
                ->where('order_index', $orderIndex)
                ->first();

            if (!$chapter) {
                $generatedUrl = $this->generateUrl($pattern, $orderIndex);
                if (!$generatedUrl) {
                    continue; // Không sinh được link thì bỏ qua
                }

                $chapter = Chapter::query()
                    ->create([
                        'story_id'      => $this->story->id,
                        'order_index'   => $orderIndex,
                        'chapter_url'   => $generatedUrl,
                        'chapter_title' => "Chương ".$orderIndex,
                        'status'        => 'pending',
                    ]);
            }

            if ($chapter->status !== 'completed') {
                $chaptersToCrawl[] = new CrawlChapterJob($chapter);
            }
        }

        if (empty($chaptersToCrawl)) {
            // Nếu không tìm thấy chương nào để cào trong dải này
            $maxExistingOrder = Chapter::query()
                ->where('story_id', $this->story->id)
                ->max('order_index') ?: 0;

            if ($this->startOrderIndex < $maxExistingOrder) {
                // Nhảy tới batch tiếp theo
                self::dispatch($this->story, $this->startOrderIndex + $this->batchSize)->onQueue('tf-chapters');
            } else {
                Log::channel('truyenfull')->info("[TruyenFull] Story ID {$this->story->id} tạm thời hết chương để batch.");
            }
            return;
        }

        // 3. Dispatch batch
        $nextOrderIndex = $this->startOrderIndex + $this->batchSize;
        $story          = $this->story;

        Log::channel('truyenfull')->info("[TruyenFull] Dispatching Batch $this->startOrderIndex - $nextOrderIndex cho story: $story->title");

        Bus::batch($chaptersToCrawl)
            ->name("TruyenFull Story $story->id Batch $this->startOrderIndex")
            ->onQueue('tf-chapters')
            ->then(function () use ($story, $nextOrderIndex) {
                // Tự động trigger batch tiếp theo CHỈ khi batch này hoàn thành thành công 100%
                CrawlStoryChapterBatchJob::dispatch($story, $nextOrderIndex)->onQueue('tf-chapters');
            })
            ->dispatch();
    }

    private function detectUrlPattern($chapters): ?string
    {
        foreach ($chapters as $chapter) {
            // Pattern phổ biến TruyenFull: .../chuong-123/ hoặc .../chapter-123/
            if (preg_match('/(.*chuong-)\d+(\/.*|.*)/i', $chapter->chapter_url, $m)) {
                return $m[1].'{n}'.$m[2];
            }
            if (preg_match('/(.*chapter-)\d+(\/.*|.*)/i', $chapter->chapter_url, $m)) {
                return $m[1].'{n}'.$m[2];
            }
        }
        return null;
    }

    private function generateUrl(?string $pattern, int $index): ?string
    {
        if (!$pattern) {
            return null;
        }
        return str_replace('{n}', $index, $pattern);
    }
}
