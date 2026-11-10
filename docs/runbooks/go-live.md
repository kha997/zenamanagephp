# Runbook: lên chạy thật lần đầu (go-live)

GAP-115 D-1. Đây là **tài liệu duy nhất** cho lần đầu đưa ZENA lên máy chủ thật. Các tài liệu và script deploy cũ (`DEPLOYMENT_GUIDE.md`,
`DOCKER_PRODUCTION_GUIDE.md`, `docs/GO_LIVE_CHECKLIST.md`, `deploy-production.sh`, `scripts/deploy-production.sh`,
`scripts/deploy.sh`) **không còn dùng**.

Mọi bước dưới đây do **Owner (hoặc người được Owner giao)** thực hiện. Agent không deploy và không chạy lệnh trên
database thật.

## 0. Cần có trước

- Một máy chủ Linux.
- Một tên miền trỏ về máy chủ đó.
- Quyền admin trên repo GitHub `kha997/zenamanagephp`.
- Một địa chỉ email cho tài khoản Owner.

## 1. Chuẩn bị máy chủ (một lần)

Làm theo `docs/runbooks/gap-049-host-provisioning.md`:
- PHP 8.2-fpm và các extension;
- MySQL 8;
- nginx + TLS;
- Redis;
- user deploy;
- thư mục `/var/www/zena/{releases,shared}`;
- dịch vụ `zena-queue-worker`;
- cron chạy `php artisan schedule:run` mỗi phút.

## 2. Tạo `shared/.env` trên máy chủ

Bắt buộc:

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...        # tạo bằng: php artisan key:generate --show (chạy ở máy nào cũng được)
APP_URL=https://<tên miền>
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=<tên database>
DB_USERNAME=<user>
DB_PASSWORD=<mật khẩu>
QUEUE_CONNECTION=redis    # không được dùng sync
CACHE_DRIVER=redis
SESSION_DRIVER=redis
ENABLE_SCHEDULER=true     # bật các việc định kỳ (sao lưu, nhắc việc cuối tháng…); mặc định là false
```

Các mục còn lại (mail, sao lưu, log) xem `docs/deploy-runbook.md`.

## 3. Đặt secrets GitHub (Settings → Secrets and variables → Actions, môi trường `production`)

| Secret | Nội dung |
|---|---|
| `PRODUCTION_HOST` | IP hoặc tên máy chủ |
| `PRODUCTION_USER` | user deploy |
| `PRODUCTION_SSH_KEY` | khoá SSH riêng của user deploy |
| `PRODUCTION_HOST_KEY_FINGERPRINT` | vân tay khoá máy chủ (chống giả mạo) |
| `PRODUCTION_URL` | `https://<tên miền>` (để kiểm tra sức khoẻ sau deploy) |
| `SLACK_WEBHOOK_URL` | tuỳ chọn, để nhận thông báo |

## 4. Deploy lần đầu

1. Lấy SHA commit mới nhất của `main` (trên GitHub, trang Commits).
2. GitHub → Actions → **Production Deployment** → **Run workflow**. Nhập đúng SHA; để trống hai ô "breaking migration".
3. Workflow tự làm các bước:
   - kiểm tra SHA;
   - đóng gói;
   - chép lên máy chủ;
   - `deploy:migrate` (chỉ chạy khi mọi migration đã được phân loại);
   - chuyển `current` sang bản mới;
   - kiểm tra sức khoẻ;
   - kiểm tra queue.
4. Kết quả phải là **`health_verified`**. Kết quả khác thì dừng lại, xem log của workflow.

Không bao giờ dùng `git pull`, Docker hay script cũ trên máy chủ. Production chỉ deploy qua workflow này.

## 5. Nạp vai trò và quyền

SSH vào máy chủ:

```
cd /var/www/zena/current
php artisan production:rbac --dry-run   # xem trước, không ghi gì
php artisan production:rbac
```

Lệnh tạo 5 vai trò chuẩn rồi in bảng "vai trò → số quyền". Bảng phải có đủ 5 vai trò và mỗi vai trò có số quyền > 0:
`super_admin` (Owner), `Finance` (kế toán), `Project Manager`, `site_engineer`, `Project Member`.

**Không bao giờ chạy `php artisan db:seed` trên máy chủ thật.** Lệnh đó tạo tài khoản mẫu `admin@zena.local` với mật
khẩu `password`.

## 6. Tạo công ty và tài khoản Owner

```
php artisan production:bootstrap --tenant-name="<Tên công ty>" --tenant-slug=<ten-cong-ty> --admin-email=<email Owner>
php artisan production:rbac             # chạy lại để nạp mẫu công việc chuẩn cho công ty
```

Ghi lại mật khẩu in ra, vì chỉ hiện một lần. Đăng nhập rồi đổi mật khẩu.

## 7. Tạo tài khoản kế toán, PM, nhân viên

```
php artisan production:user --email=ketoan@<miền> --name="Tên kế toán" --role=Finance
php artisan production:user --email=pm@<miền> --name="Tên PM" --role="Project Manager"
php artisan production:user --email=kysu@<miền> --name="Tên kỹ sư" --role=site_engineer
```

- Mỗi lệnh in một mật khẩu ngẫu nhiên, chỉ hiện một lần. Gửi riêng cho từng người.
- Cấp lại mật khẩu: `php artisan production:user --email=<email> --reset-password`.
- Nhân viên tính lương / giờ làm: vào **Nhân sự & lương → Danh sách nhân sự**, nối mỗi nhân sự với đúng tài khoản đăng
  nhập.

## 8. Kiểm tra sau khi lên

- `php artisan deploy:queue-canary --timeout=30` phải báo thành công.
- `https://<tên miền>/api/v1/public/health/readiness` trả 200.
- Owner vào được: Nhân sự & lương, Bảng giờ, Ngân quỹ, Lời lỗ công trình. Kế toán vào được trang lương nhưng không có
  nút chốt.
- Sao lưu thử, rồi khôi phục thử vào một database tạm, theo `docs/runbooks/gap-049-backup-restore.md`.

## 9. Việc hằng tháng

1. PM duyệt hết giờ làm của tháng (trang **Duyệt giờ**).
2. Kế toán nhập bảng lương. Owner **chốt lương**. Hệ thống không cho chốt nếu còn giờ chờ duyệt.
3. Kế toán nhập **Chi phí chung** của tháng.
4. Owner **chốt chi phí chung**.
5. Xem **Lời lỗ công trình**.

### Nhắc việc cuối tháng (GAP-120)

Lệnh `reminders:month-end` tự chạy lúc 08:00 (giờ Việt Nam) mỗi ngày, nhưng **chỉ khi** `ENABLE_SCHEDULER=true` và cron
`php artisan schedule:run` đã chạy (mục 1). Nó ghi thông báo vào mục "Cập nhật chưa đọc" trên trang "Hôm nay":

- **Giờ chờ duyệt:** gửi cho PM của công trình; giờ của chính PM, hoặc công trình chưa có PM, gửi cho người chốt lương; giờ
  không thuộc công trình gửi cho kế toán. Nhắc từ 3 ngày cuối tháng đến ngày 5 tháng sau.
- **Chưa chốt lương / chưa chốt chi phí chung** tháng trước: gửi người chốt lương, mỗi ngày cho tới khi chốt.

Muốn xem trước mà không gửi: `php artisan reminders:month-end --dry-run`. Chạy thử cho một ngày khác:
`php artisan reminders:month-end --dry-run --date=2026-11-30`.

## 10. Deploy các lần sau và quay lại bản cũ

- **Deploy bản mới:** lặp lại bước 4 với SHA mới. Migration mới phải được phân loại trong
  `database/migrations/classifications.json` trước khi merge; CI báo đỏ nếu thiếu.
- **Migration loại `breaking`:** làm đúng `docs/runbooks/gap-049-migration-safety.md` (sao lưu, chế độ bảo trì, hai ô
  xác nhận khi chạy workflow).
- **Bản mới lỗi sau khi chuyển:** workflow tự quay lại bản trước. Quay lại bằng tay:
  `scripts/deploy/rollback.sh`, xem `docs/runbooks/gap-049-backup-restore.md`.
