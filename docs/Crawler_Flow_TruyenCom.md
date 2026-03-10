# Tài liệu Kỹ thuật Hệ thống Crawler - TruyenCom

## 1. Tổng quan hệ thống (Architecture Overview)

Hệ thống thu thập dữ liệu (Crawler) được thiết kế đặc thù để vượt qua hệ thống tường lửa WAF (Cloudflare) của trang `truyencom.com`.

Hệ thống kết hợp 2 công nghệ cốt lõi:

- **Headless Browser (Puppeteer Stealth):** Xử lý JavaScript DOM và giả lập người dùng thật nhằm vượt qua Captcha/Cloudflare.
- **Asynchronous Task Queue (Laravel Queue):** Phân tán tải trọng và xử lý bất đồng bộ hàng ngàn chương truyện mà không gây tràn RAM hay Timeout hệt thống.

Hệ thống được chia làm hai luồng (Thread) chạy độc lập để tách biệt việc "Tìm truyện" và "Tải nội dung".

---

## 2. Chi tiết các thành phần (Component Details)

### 2.1. Headless Browser Interface

Môi trường giả lập trình duyệt để cào dữ liệu gốc.

- **File:** `scraper.cjs`
- **Công nghệ:** Node.js, Puppeteer, thẻ plugin `puppeteer-extra-plugin-stealth`.
- **Chức năng:** Nhận URL đầu vào từ PHP thông qua `shell_exec`, khởi tạo trình duyệt Chrome ẩn, chờ DOM render hoàn tất và trả về chuỗi HTML thô (raw HTML) qua `stdout`.

### 2.2. Luồng 1: Bộ quét Truyện (Discovery Pipeline)

Luồng chạy ngầm liên tục để tìm kiếm các tựa truyện mới trên hệ thống.

- **File Command:** `app/Console/Commands/CrawlDtruyenCommand.php`
- **Chức năng chính:**
    - Kéo các URL danh mục từ bảng `dtruyen_queues`.
    - Phân tích HTML để bóc tách các link truyện mới.
    - Lưu vào bảng `dtruyen_stories` với trạng thái `pending`.
- **Rate Limit:** Tự động `sleep(rand(30, 60))` giây giữa các lần quét để bảo vệ IP khỏi hệ thống ban tự động.

### 2.3. Luồng 2: Bộ tải Nội dung (Content Download Pipeline)

Một hệ thống Job dựa trên Queue được triển khai theo mô hình hình cây (Tree-based Dispatching).

| Tên Component                                        | Mô tả nhiệm vụ                                                                                                                                                                              |
| :--------------------------------------------------- | :------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Dispatcher Command**<br>`DispatchStoryJobsCommand` | Quét bảng `dtruyen_stories`, tìm các truyện `pending`. Chuyển sang `processing` và đẩy `ProcessDtruyenStoryJob` vào Queue `stories`.                                                        |
| **Story Job**<br>`ProcessDtruyenStoryJob`            | Tải trang chủ truyện. Lấy MetaData (Tên, Tác giả). Tạo thư mục Storage. <br>- Xử lý ngay trang 1 của danh sách chương.<br>- Lập lịch (Delay) `CrawlChapterPageJob` cho các trang tiếp theo. |
| **Page Job**<br>`CrawlChapterPageJob`                | Tải một trang phân trang cụ thể (VD: Trang 2). Quét danh sách các chương trên trang này và đẩy từng HTTP Link vào `CrawlChapterJob`.                                                        |
| **Chapter Job**<br>`CrawlChapterJob`                 | Nhận link lẻ của 1 chương. Bóc tách cục Text văn bản (bằng DOMXPath). Ghi vào thư mục vật lý dưới dạng file `.txt` và cập nhật đường dẫn dạng Relative vào DB.                              |

---

## 3. Quản lý Hàng đợi (Queue Management)

Hệ thống sử dụng **PM2** để giám sát và vận hành các Worker của Laravel.
Độ ưu tiên hàng đợi (Queue Priority) được cấu hình nghiêm ngặt nhằm tránh thắt cổ chai:

- **Cấu hình PM2:** Thay vì chạy mặc định, cờ `--queue=chapters,stories` được áp dụng.
- **Mục đích:** Worker sẽ bắt buộc phải xử lý dứt điểm toàn bộ các hàng đợi tải Text (`chapters`) cho tới khi rỗng hoàn toàn, trước khi quay lại nhận thêm các Job phân tích danh sách truyện mới (`stories`).

---

## 4. Cơ chế chống Bot (Anti-Bot & Bypass Bypass Mechanisms)

Để đảm bảo tỷ lệ Crawl thành công cao nhất, hệ thống áp dụng 3 quy tắc sau:

1. **Giãn cách nhịp độ (Staggered Delays):**
    - Các trang danh sách chương (Pagination) được dispatch cách nhau **30 giây** (`now()->addSeconds(($page - 1) * 30)`).
    - Sau khi tải xong 1 chương Text thành công, Job tự động ngủ đông từ **20 đến 30 giây**.
2. **Back-off Error Handling:**
    - Trong trường hợp bị Cloudflare chặn đột xuất và ném Exception, khối `catch` bắt buộc tiến trình phải Sleep từ **30 đến 40 giây** trước khi nhả lỗi lại cho Queue để Retry.
3. **Mô phỏng User Agent:** Được inject trực tiếp vào Puppeteer thông qua file `scraper.cjs`.

---

## 5. Hướng dẫn Bảo trì Cấu trúc Web (DOM XPath)

Giao diện HTML của Target Website có thể thay đổi trong tương lai. Khi đó, cần cập nhật các chuỗi `XPath` tại các file tương ứng:

- **Sửa Selector Nội dung Text Truyện:**
  Mở `app/Jobs/CrawlChapterJob.php` -> Tùy chỉnh hàm `extractChapterContent()`.
- **Sửa Selector Danh sách Tên Chương:**
  Mở `app/Jobs/ProcessDtruyenStoryJob.php` -> Tùy chỉnh hàm `extractAndSaveChapters()`.
- **Sửa Selector Thông tin Truyện (Meta):**
  Mở `app/Jobs/ProcessDtruyenStoryJob.php` -> Tùy chỉnh hàm `extractAndSaveStoryInfo()`.
