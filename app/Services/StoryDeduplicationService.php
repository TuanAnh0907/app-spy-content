<?php

namespace App\Services;

use App\Enums\StoryStatus;
use App\Models\DTruyen\Story as DtruyenStory;
use App\Models\TruyenFull\Story as TruyenFullStory;

/**
 * Service kiểm tra trùng lặp truyện cross-site.
 *
 * Bậc 1: So khớp normalized_title + normalized_author giữa 2 bảng.
 * Nếu site kia đã có record với cùng title+author ở status processing/completed
 * → báo duplicate để job skip, không cào nội dung nữa.
 */
class StoryDeduplicationService
{
    /**
     * Normalize text: lowercase, bỏ dấu tiếng Việt, bỏ ký tự đặc biệt.
     */
    public function normalize(string $text): string
    {
        $text = trim($text);

        // Bảng chuyển đổi dấu tiếng Việt → không dấu
        $map = [
            'à','á','ả','ã','ạ','ă','ắ','ặ','ằ','ẳ','ẵ','â','ấ','ầ','ẩ','ẫ','ậ' => 'a',
            'è','é','ẻ','ẽ','ẹ','ê','ế','ề','ể','ễ','ệ' => 'e',
            'ì','í','ỉ','ĩ','ị' => 'i',
            'ò','ó','ỏ','õ','ọ','ô','ố','ồ','ổ','ỗ','ộ','ơ','ớ','ờ','ở','ỡ','ợ' => 'o',
            'ù','ú','ủ','ũ','ụ','ư','ứ','ừ','ử','ữ','ự' => 'u',
            'ỳ','ý','ỷ','ỹ','ỵ' => 'y',
            'đ' => 'd',
            'À','Á','Ả','Ã','Ạ','Ă','Ắ','Ặ','Ằ','Ẳ','Ẵ','Â','Ấ','Ầ','Ẩ','Ẫ','Ậ' => 'a',
            'È','É','Ẻ','Ẽ','Ẹ','Ê','Ế','Ề','Ể','Ễ','Ệ' => 'e',
            'Ì','Í','Ỉ','Ĩ','Ị' => 'i',
            'Ò','Ó','Ỏ','Õ','Ọ','Ô','Ố','Ồ','Ổ','Ỗ','Ộ','Ơ','Ớ','Ờ','Ở','Ỡ','Ợ' => 'o',
            'Ù','Ú','Ủ','Ũ','Ụ','Ư','Ứ','Ừ','Ử','Ữ','Ự' => 'u',
            'Ỳ','Ý','Ỷ','Ỹ','Ỵ' => 'y',
            'Đ' => 'd',
        ];

        $text = strtr($text, $map);

        // Lowercase, bỏ ký tự không phải chữ/số/khoảng trắng, gộp khoảng trắng
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Kiểm tra xem (normalizedTitle, normalizedAuthor) đã tồn tại ở bảng đối diện chưa.
     *
     * @param  string  $normalizedTitle   Title đã normalize
     * @param  string  $normalizedAuthor  Author đã normalize
     * @param  string  $currentSite       'dtruyen' hoặc 'truyenfull'
     * @return array{duplicate: bool, source: string|null, id: int|null}
     */
    public function checkDuplicate(
        string $normalizedTitle,
        string $normalizedAuthor,
        string $currentSite
    ): array {
        // Không check nếu thiếu cả 2
        if (empty($normalizedTitle) && empty($normalizedAuthor)) {
            return ['duplicate' => false, 'source' => null, 'id' => null];
        }

        $statuses = [StoryStatus::PROCESSING, StoryStatus::COMPLETED];

        if ($currentSite === 'dtruyen') {
            // Đang crawl từ DTruyen → check TruyenFull
            $existing = TruyenFullStory::query()
                ->where('normalized_title', $normalizedTitle)
                ->where('normalized_author', $normalizedAuthor)
                ->whereIn('status', $statuses)
                ->first(['id', 'title']);

            if ($existing) {
                return [
                    'duplicate' => true,
                    'source'    => 'tf_stories',
                    'id'        => $existing->id,
                    'title'     => $existing->title,
                ];
            }
        } else {
            // Đang crawl từ TruyenFull → check DTruyen
            $existing = DtruyenStory::query()
                ->where('normalized_title', $normalizedTitle)
                ->where('normalized_author', $normalizedAuthor)
                ->whereIn('status', $statuses)
                ->first(['id', 'title']);

            if ($existing) {
                return [
                    'duplicate' => true,
                    'source'    => 'dtruyen_stories',
                    'id'        => $existing->id,
                    'title'     => $existing->title,
                ];
            }
        }

        return ['duplicate' => false, 'source' => null, 'id' => null];
    }
}
