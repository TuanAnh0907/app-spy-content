# Spy DocTruyen — Hướng dẫn chạy crawler

## Các lệnh chính

### 1. `spy:run` — Crawl hàng loạt

Quét trang danh sách, lấy truyện mới về.

```bash
# Crawl 5 truyện mới từ truyenfull
php artisan spy:run truyenfull --pages=1 --limit=5

# Crawl và luôn kéo chương luôn
php artisan spy:run tangthuvien --pages=1 --limit=3 --chapters

# Các nguồn hỗ trợ
php artisan spy:run truyenfull   # https://truyenfull.io
php artisan spy:run tangthuvien  # https://tangthuvien.net
php artisan spy:run sstruyen     # https://sstruyen.vn
```

**Dedup tự động**: Bỏ qua truyện đã `processed`. Warn nếu title trùng với nguồn khác.

---

### 2. `spy:story` — Crawl 1 truyện theo URL

```bash
# Crawl meta + danh sách chương
php artisan spy:story https://truyenfull.io/ten-truyen/ --source=truyenfull

# Crawl meta + kéo luôn tất cả chương
php artisan spy:story https://truyenfull.io/ten-truyen/ --source=truyenfull --chapters

# Bỏ qua warning trùng title
php artisan spy:story https://... --source=truyenfull --force-dup
```

---

### 3. `spy:chapters` — Crawl chương của 1 truyện

```bash
# Crawl tất cả chương (tự resume nếu chạy lại)
php artisan spy:chapters {story_id}

# Crawl từ chương 50 đến 100
php artisan spy:chapters {story_id} --from=50 --to=100

# Crawl lại dù đã processed
php artisan spy:chapters {story_id} --force
```

> **Tự resume**: Chương đã `processed` sẽ bị skip tự động.
> Chạy lại lệnh sau khi lỗi → tự crawl tiếp từ chỗ dở, không crawl lại từ đầu.

---

### 4. `spy:sync` — Sync lên backend

```bash
# Sync tất cả truyện đã processed
php artisan spy:sync --all

# Sync 1 truyện cụ thể
php artisan spy:sync --story=42

# Xem sẽ sync gì, không gửi thật
php artisan spy:sync --all --dry-run
```

---

## Luồng chuẩn (từ đầu đến cuối)

```bash
# Bước 1: Crawl meta truyện
php artisan spy:story https://truyenfull.io/ten-truyen/ --source=truyenfull

# Bước 2: Crawl chương (tự resume nếu bị ngắt)
php artisan spy:chapters {story_id}

# Bước 3: Đánh dấu processed (hoặc để spy:run tự làm)
# UPDATE scraped_stories SET process_status='processed' WHERE id={story_id};

# Bước 4: Sync lên backend
php artisan spy:sync --story={story_id}
```

---

## Vòng đời `process_status`

```
pending → processing → processed
                   └→ failed (tự retry lần chạy sau)
```

| Status       | Ý nghĩa                     |
| ------------ | --------------------------- |
| `pending`    | Chưa crawl                  |
| `processing` | Đang crawl (hoặc job crash) |
| `processed`  | Xong, skip ở lần sau        |
| `failed`     | Lỗi, sẽ tự retry            |

> **Stale detection**: `processing` quá `CRAWLER_STALE_MINUTES` (mặc định 60 phút) → coi là crash → retry tự động.

---

## Cấu hình `.env`

```env
# Tốc độ crawl
CRAWLER_DELAY_MS=1500         # Sleep giữa mỗi HTTP request (ms)
CRAWLER_STORY_DELAY_S=30      # Sleep giữa mỗi truyện (giây)
CRAWLER_CHAPTER_DELAY_S=5     # Sleep giữa mỗi chương (giây)
CRAWLER_TIMEOUT=30            # HTTP timeout per request (giây)
CRAWLER_RETRIES=3             # Số lần retry khi lỗi

# Stale detection
CRAWLER_STALE_MINUTES=60      # Sau bao lâu coi processing là crashed

# Lưu file content chương
# CHAPTER_DISK: chapters (local) | chapters_s3 (S3/MinIO)
CHAPTER_DISK=chapters

# Dùng khi CHAPTER_DISK=chapters
CHAPTER_STORAGE_PATH=/path/to/storage/chapters

# Dùng khi CHAPTER_DISK=chapters_s3 (nếu bỏ trống sẽ fallback về AWS_*)
CHAPTER_S3_KEY=
CHAPTER_S3_SECRET=
CHAPTER_S3_REGION=us-east-1
CHAPTER_S3_BUCKET=
CHAPTER_S3_URL=
CHAPTER_S3_ENDPOINT=
CHAPTER_S3_PATH_STYLE=false
CHAPTER_S3_VISIBILITY=private

# Backend để sync
BACKEND_API_URL=http://localhost:8000
BACKEND_API_TOKEN=your-token
```

---

## Cron (production)

```bash
# Thêm vào crontab
* * * * * cd /var/www/read-app/spy-doctruyen && php artisan schedule:run >> /dev/null 2>&1
```

**Schedule đang chạy:**

```
0 */6 * * *  spy:run truyenfull --pages=1 --limit=5   (timeout 2h)
0 */6 * * *  spy:run tangthuvien --pages=1 --limit=5  (timeout 2h)
0 */6 * * *  spy:run sstruyen --pages=1 --limit=5     (timeout 2h)
0 * * * *    spy:sync --all                            (timeout 2m)
```

**Xem log:**

```bash
tail -f storage/logs/spy-run-truyenfull.log
tail -f storage/logs/spy-sync.log
```
