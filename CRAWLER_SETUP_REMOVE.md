# Hướng Dẫn Cài Đặt và Gỡ Bỏ Hệ Thống Crawler (Puppeteer Node.js)

Tài liệu này cung cấp các lệnh chuẩn xác nhất để thiết lập (Install) hoặc gỡ bỏ hoàn toàn (Remove) hệ thống cào dữ liệu dùng Headless Browser (Puppeteer) đang chạy nối tiếp cùng hệ thống Laravel Queue (PM2).

---

## PHẦN 1: HƯỚNG DẪN CÀI ĐẶT (INSTALLATION)

Nếu bạn thiết lập server mới hoặc clone source code về một hệ thống hoàn toàn trắng, hãy chạy các lệnh sau để đảm bảo Crawler (Puppeteer) hoạt động ổn định.

### 1. Cài đặt Node.js và NPM
Script `scraper.cjs` yêu cầu Node.js.
```bash
# Cài đặt Node.js phiên bản 18.x hoặc 20.x (từ NodeSource)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs
```

### 2. Cài đặt các thư viện Chrome/Webkit cho Puppeteer
Puppeteer cần các dependencies lõi của hệ điều hành (chủ yếu là Ubuntu/Debian) để chạy Headless Chromium.
```bash
sudo apt-get update
sudo apt-get install -y libnss3 libnspr4 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 libxkbcommon0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 libasound2
```

### 3. Cài đặt NPM Packages cho Project
Di chuyển vào thư mục dự án Laravel và tải về các gói Node.js chuyên dụng cho việc Bypass Cloudflare.
```bash
cd /var/www/read-app/spy-doctruyen

# Khởi tạo package.json (Nếu chưa có)
npm init -y

# Cài đặt Puppeteer và module vượt tường lửa (Stealth Plugin)
npm install puppeteer puppeteer-extra puppeteer-extra-plugin-stealth
```

### 4. Đảm bảo file `scraper.cjs` tồn tại
Kiểm tra xem file `scraper.cjs` đã nằm ở root thư mục dự án chưa. Laravel Jobs sẽ call shell command: `node /var/www/read-app/spy-doctruyen/scraper.cjs <url>`.

### 5. Khởi động Queue Workers (PM2)
Bật các process cào truyện thông qua file cấu hình Ecosystem của PM2 đã được tạo sẵn trong thư mục dự án:
```bash
# Đảm bảo đang ở thư mục: /var/www/read-app/spy-doctruyen
# Khởi động Crawler cho DTruyen
pm2 start ecosystem.dtruyen.yml

# Khởi động Crawler cho TruyenFull
pm2 start ecosystem.truyenfull.yml

# Lưu cấu hình PM2 để tự khởi động cùng OS
pm2 save
```

---

## PHẦN 2: HƯỚNG DẪN GỠ BỎ (REMOVAL)

Nếu bạn không muốn sử dụng Crawler cào web Node.js nữa và muốn quay về một hệ thống Laravel tinh gọn (hoặc xóa để giải phóng dung lượng Server).

### 1. Dừng và xóa Queue Workers trong PM2
Tuyệt đối phải dừng worker trước khi xóa file để tránh sinh log lỗi liên tục. Bạn có thể sử dụng lại các file YML để xóa:
```bash
# Tắt tất cả các process cào truyện
pm2 delete ecosystem.dtruyen.yml
pm2 delete ecosystem.truyenfull.yml
pm2 save
```

### 2. Xóa Môi trường Node.js Crawler
Gỡ bỏ hoàn toàn Chromium và gói NPM.
```bash
cd /var/www/read-app/spy-doctruyen

# Xóa Source code Node.js
rm scraper.cjs
rm package.json package-lock.json
rm -rf node_modules/
```

### 3. Xóa Data Database (Tùy chọn)
Nếu bạn muốn reset sạch sẽ cơ sở dữ liệu các truyện đã cào, vào bảng console của MySQL hoặc dùng Tinker (⚠️ Lệnh này sẽ xóa trắng dữ liệu truyện cào):
```sql
DROP TABLE tf_chapters, tf_stories, dtruyen_chapters, dtruyen_stories;
```
*(Nếu muốn xóa qua Migration, sử dụng `php artisan migrate:rollback` nhiều lần, nhưng DROP trực tiếp sẽ nhanh hơn).*

### 4. Dọn dẹp Log và thư mục lưu trữ rác
Dọn sạch rác, RAM hoặc bộ nhớ do hệ thống file Txt (Truyện chữ).
```bash
cd /var/www/read-app/spy-doctruyen

# Xóa rác log của Crawlers
rm storage/logs/truyenfull*.log
rm storage/logs/dtruyen*.log

# Xóa toàn bộ nội dung file chương dạng txt đã tải (Nếu thực sự muốn xóa)
rm -rf storage/app/txt_chapters/*
rm -rf storage/app/covers/*
```

### 5. (Tùy chọn Nâng Cao) Xóa Code Logic Laravel liên quan
Nếu bạn muốn triệt tiêu hoàn toàn module cào truyện ra khỏi Source PHP, bạn có thể xóa hẳn cấu trúc Jobs và Console mới tạo:
```bash
# Xóa commands
rm -rf app/Console/Commands/DTruyen/
rm -rf app/Console/Commands/TruyenFull/

# Xóa Jobs xử lý
rm -rf app/Jobs/DTruyen/
rm -rf app/Jobs/TruyenFull/

# Xóa Models
rm -rf app/Models/DTruyen/
rm -rf app/Models/TruyenFull/
```
