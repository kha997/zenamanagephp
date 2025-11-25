# Technical Debt & Duplication Cleanup Plan

**Mục tiêu**: gom và chuẩn hoá các phần technical debt/dụng cụ debug còn rải rác, giảm trùng lặp và làm repo “sạch” hơn nhưng vẫn an toàn cho vận hành và điều tra lỗi.

---

## 1. Root-level debug scripts & HTML testers

**Các file hiện có ở root:**

- `test-login.sh`
- `test-login-simple.sh`
- `test-login-session.sh`
- `test-views.sh`
- `test-docker-setup.sh`
- `test-api-endpoints.sh`
- `test-dashboard.html`
- `test-modal-fix.html`
- `test_tenant_isolation.php`
- `test_idempotency.php`
- `test_csrf_cors_session.php`
- `test_debug_component.php`
- `test_debug_route.php`

**Vấn đề:**
- Nằm ngay root → khó phân biệt với script chính thức.
- Một số đã được “thay thế” bởi test tự động (PHPUnit, Playwright, Dusk) hoặc tài liệu chi tiết trong `docs/`.

**Đề xuất xử lý:**

1. Tạo thư mục chuẩn để chứa manual/debug tools, ví dụ:
   - `tools/manual-tests/` hoặc `tests/manual/`.
2. Di chuyển các file trên vào thư mục đó, giữ nguyên tên file:
   - Ví dụ: `test-login.sh` → `tools/manual-tests/test-login.sh`.
3. Thêm README ngắn trong thư mục mới:
   - Mô tả: mục đích, khi nào dùng, môi trường phù hợp (local/dev), cảnh báo không chạy trên production.
4. Kiểm tra CI/CD:
   - Đảm bảo không có workflow nào gọi trực tiếp các script này từ root.
5. (Tuỳ chọn) Dần dần thay thế bằng test tự động hoặc script “chính thức” trong `scripts/`, sau đó xoá các manual script nếu đã redundant.

---

## 2. Snapshot / working copy cũ trong `_work/zenamanagephp`

**Thư mục:** `_work/zenamanagephp/…`

**Nhận định:**
- Đây có vẻ là snapshot hoặc working copy cũ (mirror của repo) dùng trong một giai đoạn debugging/handoff.
- Chứa nhiều file trùng với code hiện tại (`phpunit.xml`, tests E2E, docs, …).

**Rủi ro:**
- Dễ gây nhầm lẫn khi tìm kiếm (rg/grep trả về path trong `_work` thay vì source thật).
- Có nguy cơ reviewer hoặc tool chỉnh nhầm vào bản cũ.

**Đề xuất xử lý:**

1. Xác nhận với team:
   - Thư mục `_work/zenamanagephp` có còn use-case nào đặc biệt (ví dụ ví dụ training, archive cho báo cáo) không.
2. Nếu **không cần thiết**:
   - Xoá toàn bộ `_work/zenamanagephp` khỏi repo.
   - Thêm vào `.gitignore` (nếu có thể phát sinh lại) để tránh commit future snapshots.
3. Nếu **còn cần lưu làm archive**:
   - Chuyển nội dung quan trọng thành docs (ví dụ trích `AUTH_E2E_TEST_SUITE_SUMMARY.md` chính thức đã tồn tại trong root).
   - Đổi tên thư mục rõ ràng hơn, ví dụ: `archive/2025-01-e2e-snapshot/`.
   - Đảm bảo scripts, tools hiện tại không trỏ vào đó.

---

## 3. Legacy / duplicate docs & status reports

**Hiện trạng:**
- Repo có rất nhiều file báo cáo / summary / checklist (ví dụ: `*_SUMMARY.md`, `*_PROGRESS.md`, `*_REPORT.md`, `*_CHECKLIST.md`).
- Nhiều file mô tả cùng một phase hoặc domain (auth, dashboard, tasks, …) ở các thời điểm khác nhau.

**Vấn đề:**
- Khó nhận biết “nguồn sự thật” hiện tại.
- Người mới dễ đọc nhầm doc cũ, tưởng là trạng thái hiện tại.

**Đề xuất xử lý:**

1. Xác định canonical index:
   - `DOCUMENTATION_INDEX.md` và `COMPLETE_SYSTEM_DOCUMENTATION.md` sẽ trỏ tới tài liệu **mới nhất** theo domain.
2. Nhóm doc theo domain & phase:
   - Ví dụ: `docs/auth/…`, `docs/dashboard/…`, `docs/testing/…`.
   - Di chuyển các file cũ vào thư mục `docs/archive/` nếu chỉ còn giá trị historical.
3. Gắn nhãn rõ ràng cho doc cũ:
   - Ở đầu file thêm block:
     - `> ⚠️ Archived – refer to XXX.md for current spec.`
4. Duy trì 1–2 tài liệu tổng hợp cho mỗi domain:
   - Ví dụ: `AUTH_SYSTEM_DOCUMENTATION.md` + `AUTH_E2E_TEST_SUITE_SUMMARY.md` là canonical cho auth.

---

## 4. Legacy UI components & Blade partials

**Các đối tượng liên quan (ví dụ):**
- `resources/views/components/shared/header.blade.php` (legacy header mount point).
- `frontend/src/components/LegacyHeader.jsx` (nếu còn).
- Các Blade components/table legacy như được mô tả trong `docs/component-inventory.*`.

**Vấn đề:**
- Một số component được đánh dấu `legacy` hoặc `deprecate` nhưng vẫn tồn tại song song với variant chuẩn (`header-wrapper`, `header-standardized`, `x-shared.table-standardized`, …).

**Đề xuất xử lý:**

1. Chạy tìm kiếm usage:
   - Kiểm tra xem các component legacy còn được include/call ở đâu.
2. Nếu **không còn usage**:
   - Di chuyển vào `resources/views/components/legacy/` hoặc xoá hẳn (tốt nhất là một PR riêng, có thông báo rõ).
   - Cập nhật `docs/component-inventory.md` & `.csv` để phản ánh đã remove.
3. Nếu **còn usage**:
   - Lập kế hoạch migration (per page/per domain), ghi rõ trong inventory:
     - mapping: legacy → standardized component.
   - Mỗi lần refactor page, cập nhật inventory tương ứng.

---

## 5. Scripts / tooling trùng vai trò với docs

**Ví dụ:**
- Một số script shell trong root (`setup-*.sh`, `manage-*.sh`, `run-*.sh`) đã được mô tả lại rất chi tiết trong docs (CI/CD, monitoring).

**Đề xuất xử lý:**

1. Với script còn dùng trong CI/CD hoặc chạy local thường xuyên:
   - Đảm bảo có entry rõ trong docs (ví dụ `DEVELOPMENT_SERVER_SETUP.md`, `CI_CD_MONITORING_GUIDE.md`).
2. Với script chỉ còn mang tính thử nghiệm:
   - Di chuyển sang `tools/experimental/` hoặc `archive/scripts/`.
   - Gắn nhãn “experimental” trong chính file và/hoặc trong doc index.

---

## 6. Cách thực hiện an toàn (per-PR)

Để tránh breaking:

1. **Bước 1 – Di chuyển trước, không xoá**  
   - Với debug scripts/manual tests: di chuyển vào `tools/manual-tests/` hoặc tương đương, cập nhật docs nếu có đường dẫn.

2. **Bước 2 – Cập nhật docs & references**  
   - Sửa các đường dẫn trong docs (README, TEST_GUIDE, …) trỏ sang vị trí mới.
   - Đảm bảo không còn script/guide nào tham chiếu vị trí cũ.

3. **Bước 3 – Xác nhận CI/CD & test**  
   - Chạy `phpunit`, `npm test`/`pnpm test`, `npx playwright test` (tuỳ pipeline) để chắc chắn không có path hardcoded cũ bị gãy.

4. **Bước 4 – Xoá/Archive**  
   - Nếu sau một thời gian không có ai dùng, có thể xoá hẳn các file debug/manual cũ hoặc chuyển vào `archive/` chỉ-read.

---

## 7. Checklist ngắn

- [ ] Tạo `tools/manual-tests/` (hoặc `tests/manual/`) và di chuyển toàn bộ `test-*.sh`, `test-*.html`, `test_*.php` từ root vào đó.  
- [ ] Kiểm tra & quyết định số phận `_work/zenamanagephp` (xoá hoặc chuyển thành `archive/`).  
- [ ] Đánh nhãn `Archived` cho các docs cũ, cập nhật `DOCUMENTATION_INDEX.md` trỏ tới bản mới nhất.  
- [ ] Rà `component-inventory.*` và dọn legacy UI components không còn usage.  
- [ ] Chuẩn hoá vị trí và docs cho các script setup/monitoring/CI/CD còn active.  

> Sau khi hoàn thành các bước trên, repo sẽ gọn hơn, việc tìm kiếm/debug và onboard người mới cũng đơn giản hơn rất nhiều, mà vẫn giữ được đủ “dụng cụ” cho việc điều tra sự cố khi cần.  

