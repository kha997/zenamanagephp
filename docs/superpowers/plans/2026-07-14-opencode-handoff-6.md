# Handoff #6 cho opencode — Gói kiểm định tiền-merge PR #163 (2026-07-14)

Đợt xóa alias + docs đã review: đạt. Branch đang chờ user merge PR #163 — đợt này KHÔNG thêm feature, chỉ chạy các tầng kiểm định chưa đụng tới trong các đợt vừa qua và chuẩn bị mô tả PR. Ràng buộc handoff #1 vẫn áp dụng.

## Task S — Chạy Deptrac (kiểm tra ranh giới lớp kiến trúc)

1. `composer deptrac` (hoặc `vendor/bin/deptrac analyse` — xem script trong composer.json).
2. Kỳ vọng: **0 violation MỚI** so với trạng thái đã biết. Lưu ý đã biết trước: chỉ số "Uncovered: ~10,026" KHÔNG phải lỗi — đó là các edge nằm ngoài 7 layer mà `deptrac.yaml` định nghĩa, đừng "sửa" nó.
3. Nếu có violation mới do code các slice vừa rồi (PaymentCertificate/ContractExpense/...): sửa đúng chỗ vi phạm (thường là import sai tầng), KHÔNG sửa `deptrac.yaml` để hợp thức hóa.
4. Ghi kết quả (số violation/uncovered) vào báo cáo cuối. Commit nếu có sửa: `fix(architecture): ...`.

## Task T — Chạy ssot:lint (kiểm tra đồng bộ route/SSOT)

1. Quirk môi trường local ĐÃ BIẾT: `display_errors` đẩy warning imagick/memcached vào stdout làm hỏng JSON của `dump_routes.sh`. Chạy với `php -d display_errors=0` cho các lệnh php bên trong (xem cách gọi trong `composer.json` script `ssot:lint` và chỉnh lời gọi khi chạy tay — KHÔNG sửa file script/composer.json chỉ vì quirk local).
2. Quirk đã biết #2: tồn tại báo cáo ~71 orphan-route CÓ TỪ TRƯỚC (pre-existing, 2026-07-12) — không phải lỗi của các slice mới. Chỉ hành động nếu xuất hiện orphan MỚI liên quan các route vừa thêm (contracts.certificates.*, contracts.boq-lines.*, contracts.finance-settings.*, tasks/design-items block/unblock, contracts.expenses.*).
3. Nếu SSOT route registry cần đăng ký các route mới (xem cơ chế trong docs/agent-ssot-rules.md hoặc scripts/ssot/): đăng ký cho đủ, commit `docs(ssot): register new contract-finance and blocker routes`.
4. Ghi kết quả vào báo cáo cuối.

## Task U — Soạn và cập nhật mô tả PR #163

PR đã gom nhiều giai đoạn; mô tả hiện tại chắc chắn lỗi thời. Soạn body mới theo cấu trúc:

1. **Tóm tắt** (5-7 dòng): consolidation discovery → hardening (throttle AI, TenantScope, route cleanup) → R-DPM (revision/blocker/project section) → R-CTR (contract_type, thu–chi, progress per type) → IPC (BOQ HĐ + chứng chỉ kỳ) → Retention/tạm ứng → dọn repo root + alias retirement + PHPStan enforced.
2. **Bảng slice → commit range → docs** (spec/plan tương ứng trong docs/superpowers/).
3. **Verification**: 3 con số cuối cùng + ghi chú suite mặc định đã loại nhóm performance.
4. **Breaking/behavior changes**: đợt thu tự sinh từ chứng chỉ giờ = net_payable (không phải 100% KL); PHPStan chặn CI với lỗi mới; 6 alias Zena* đã xóa; ~270 file rác root đã dọn.
5. Cập nhật bằng: `gh pr edit 163 --body-file <file tạm>` (viết body ra file tạm trước). Nếu `gh` không có sẵn/không auth: lưu body vào `docs/superpowers/plans/2026-07-14-pr-163-description.md`, commit, và ghi rõ trong báo cáo để user tự dán.

## Checklist cuối (báo cáo kèm đủ)

```bash
php artisan test tests/Feature/Architecture/     # 29
php artisan test --testsuite=Feature             # 897
vendor/bin/phpstan analyse --memory-limit=1G     # exit 0, baseline không tăng count
composer deptrac                                  # kết quả Task S
# ssot:lint                                       # kết quả Task T
```

KHÔNG feature mới. KHÔNG merge. Sau đợt này, branch sẵn sàng để user bấm merge.
