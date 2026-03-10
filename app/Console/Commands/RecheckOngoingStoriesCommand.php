<?php

namespace App\Console\Commands;

use App\Models\DTruyen\Story as DtruyenStory;
use App\Models\TruyenFull\Story as TruyenFullStory;
use App\Enums\StoryStatus;
use Illuminate\Console\Command;

class RecheckOngoingStoriesCommand extends Command
{
    /**
     * Dùng --days=2 để quyết định quét lại truyện cập nhật từ 2 ngày trước.
     */
    protected $signature = 'crawl:recheck-ongoing {--days=2 : Số ngày bặt buộc kể từ lần cập nhật cuối}';
    
    protected $description = 'Kiểm tra truyện COMPLETED đã cũ, chuyển về PENDING để cào tiếp chương mới (năm, tháng, ngày...).';

    public function handle(): void
    {
        $days = (int) $this->option('days');
        
        if ($days <= 0) {
            $this->error('Tham số --days phải lớn hơn 0.');
            return;
        }

        $this->info("Bắt đầu quét các truyện COMPLETED cách đây >= {$days} ngày để đẩy vào luồng Re-Crawl...");
        
        $thresholdDate = now()->subDays($days);

        // --- 1. DTruyen ---
        $dtCount = DtruyenStory::where('status', StoryStatus::COMPLETED)
            ->where('updated_at', '<=', $thresholdDate)
            ->update([
                'status' => StoryStatus::PENDING,
                // Ta reset synced_at = null nếu muốn khi có chương mới sẽ sync lại thông tin Story (Ví dụ: trạng thái Hoàn thành)
                // Tuy nhiên hiện tại backend updateOrCreate theo slug nên cứ để nguyên synced_at cũng ok.
                // Ở đây ta cứ trả nó về PENDING để Queue tóm lấy tiến hành cào mục lục.
            ]);
            
        $this->info("[DTruyen] Đã chuyển {$dtCount} truyện về PENDING.");

        // --- 2. TruyenFull ---
        $tfCount = TruyenFullStory::where('status', StoryStatus::COMPLETED)
            ->where('updated_at', '<=', $thresholdDate)
            ->update([
                'status' => StoryStatus::PENDING,
            ]);
            
        $this->info("[TruyenFull] Đã chuyển {$tfCount} truyện về PENDING.");
        
        $total = $dtCount + $tfCount;
        $this->info("Hoàn tất! Tổng cộng {$total} truyện sẽ được Dispatcher tự động ném vào hàng chờ để check chương mới.");
    }
}
