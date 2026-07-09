# Biên bản nghiệm thu — ZenaManage (cập nhật 2026-07-09)

**Trạng thái: ĐẠT — đủ điều kiện bàn giao.**
Trục kiểm chứng: bảo mật · đúng đắn · chất lượng bàn giao · khả năng vận hành.
Quy trình: 2 reviewer độc lập (bảo mật + logic) trên diff tính năng, audit hạ tầng,
test phân quyền thủ công qua HTTP với 6 tài khoản vai trò, toàn bộ phát hiện được vá và
khóa bằng regression test.

## Số liệu chốt

| Hạng mục | Kết quả |
|---|---|
| Test suite (`--exclude-group=stress`) | **1337 passed / 0 failed** (9.782 assertions) |
| Lỗ hổng dependency (composer + npm) | **0** |
| Deptrac (ranh giới kiến trúc) | 0 vi phạm (2 nợ legacy baseline) |
| Guard-rail CI | Deptrac → SSOT lint → invariant tests → full suite |

## Phát hiện đã vá trong chu kỳ nghiệm thu

### Vòng review độc lập (commit `d3099cbb`, `63afc21f`)
| Mức | Phát hiện | Xử lý |
|---|---|---|
| Critical | SSRF qua webhook URL (cloud metadata, mạng nội bộ) | Chặn 2 lớp: validate lúc tạo + resolve DNS lúc giao (chống DNS rebinding) |
| Critical | TOCTOU tạo nhật ký trùng ngày → 500 | Unique constraint là nguồn chân lý; QueryException 23000 → 422 |
| Important | Webhook retry chạy 4 lần, đếm lỗi trùng | Guard `attempts < tries` |
| Important | LIKE-filter activity feed không escape `%`/`_` | Escape như global search |
| Important | Tạo API token không rate-limit | `throttle:6,1` |
| Minor | CSV formula injection; secret đi qua flash chung; export OOM; Gantt lệch múi giờ | Đã vá toàn bộ |

### Vòng migrate G7 (commit `5447ccf1`, `921b7ade`)
| Mức | Phát hiện | Xử lý |
|---|---|---|
| Critical | IDOR xuyên tenant: Web TaskController show/edit, DocumentController show/approvals/create không scope tenant | Tenant-scope toàn bộ |
| Important | ProjectService crash khi thiếu description; nuốt status/budget người dùng nhập | Vá + test |
| Important | API task update dùng bộ status khác store | Hợp nhất canonical + alias cũ |
| Minor | Relation `uploadedBy` không tồn tại (đúng: `uploader`) — trang approvals 500 sẵn | Vá |

### Vòng test phân quyền (commit `0bdbd964`)
| Mức | Phát hiện | Xử lý |
|---|---|---|
| **Critical** | Viewer tạo được task qua `POST /app/tasks` — route ghi mới thiếu rbac, API không tự check | `rbac:task.create/update`, `rbac:project.write`, `rbac:document.create` trên 5 route ghi; regression test viewer→403 |

## Kết quả test phân quyền (6 tài khoản, qua HTTP)

- **Ma trận GET** 7 trang × 6 vai trò: đúng thiết kế 100% (webhooks chỉ admin; kiểm định chỉ QC; BOQ chỉ admin/PM/procurement...)
- **Workflow xuyên vai trò**: site engineer tạo → gửi duyệt → tự phê duyệt bị 403 → PM phê duyệt thành công
- Viewer bị 403 trên mọi endpoint ghi, không sinh bản ghi

## Điều kiện vận hành phía nhận bàn giao

1. Production: `QUEUE_CONNECTION=redis|database` + queue worker (webhook giao qua queue)
2. Không bật Telescope/Debugbar ở production
3. Xác nhận nghiệp vụ: `/search` cho mọi user trong tenant thấy tiêu đề mọi module
4. Tài khoản test (`*@zena.local` / `password`) chỉ tồn tại DB local — không seed lên staging/production

## Khuyến nghị trước ký bàn giao cuối

- Load test (k6/Locust) trên export/search với dataset lớn
- Penetration test bên thứ ba
