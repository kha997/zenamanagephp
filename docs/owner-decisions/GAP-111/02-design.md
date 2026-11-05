---
work_id: GAP-111
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-05-gap-111-symfony-security-update-evidence.md
  plan: docs/superpowers/plans/2026-11-05-gap-111-symfony-security-update.md
  branch: docs/GAP-111-symfony-security-update
  pr: https://github.com/kha997/zenamanagephp/pull/401
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-05T13:54:08+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-05, verbatim: 'APPROVE GAP-111 Gate 2 U-1'. Reviewed design head: a153e624040d56a70ca43933088db869c4ce5784. Approves U-1 (composer update of the six affected Symfony packages with dependencies: twelve patch upgrades inside Symfony 7.4, composer.lock only), with the exact allowlist in this packet; no composer.json or application code change; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-05T14:00:00+07:00"
  updated_at: "2026-11-05T13:54:08+07:00"
generated_by: agent
---

# GAP-111 — Symfony security advisories: Gate 2 design

## OWNER GATE 2: APPROVED — U-1

Owner approved U-1 in-session on 2026-11-05 against reviewed design head `a153e624040d56a70ca43933088db869c4ce5784`. This authorizes only the bounded change
defined by this packet; it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Anh/chị chọn **1 điểm**:

**Phạm vi cập nhật** — đề xuất **U-1**: chỉ nâng 6 gói Symfony bị lỗi và các gói phụ thuộc bắt buộc. Tổng **12 gói**, tất cả là
bản vá cùng dòng 7.4.

## Điểm 1 — Phạm vi cập nhật

| Phương án | Nội dung |
|---|---|
| **U-1 (đề xuất)** | Chạy `composer update symfony/config symfony/filesystem symfony/http-foundation symfony/http-kernel symfony/routing symfony/yaml --with-dependencies`. Kết quả (đã chạy thử): 12 gói nâng bản vá (7.4.17–7.4.21 → 7.4.22–7.4.23; contracts 3.7.1 → 3.8.1; polyfill-mbstring 1.43.0 → 1.43.3). Không thêm / bỏ gói, không đổi `composer.json`. |
| U-2 | `composer update` toàn bộ. Cũng sửa được lỗ hổng, nhưng kéo theo nâng mọi thư viện khác (Laravel, PHPUnit, …): rủi ro và phạm vi kiểm tra lớn hơn nhiều. |

## Thiết kế chi tiết (theo U-1)

- Chỉ đổi `composer.lock` bằng đúng lệnh của U-1. Không sửa tay lockfile, không đổi mã ứng dụng.
- Nếu test cho thấy bản vá làm đổi hành vi, dừng lại và trình anh/chị bổ sung phạm vi (Owner amendment). Không tự sửa mã.

### Kiểm tra (trước / sau)
- **Trước:** `composer audit --locked` báo 9 lỗ hổng (6 gói).
- **Sau:** `composer audit --locked` sạch.
- Toàn bộ Unit + Feature + Integration và `tests/Feature/Api`: không lỗi mới so với 2 lỗi có sẵn do chạy bằng root.
- PHPStan, php-cs-fixer, SSOT lint, `ssot:lint`, route guard, deptrac sạch.
- Khởi động được app (`php artisan route:list`, mở trang đăng nhập).
- CI trên đúng head xanh, gồm "Security Vulnerability Scan".

### Files (allowlist)
- `composer.lock`.
- Plan `docs/superpowers/plans/2026-11-05-gap-111-symfony-security-update.md`; packet và evidence GAP-111.

Không đổi `composer.json`, không đổi mã ứng dụng, không đổi gói npm.

## Ngoài phạm vi

Nâng Laravel / PHP; cập nhật toàn bộ thư viện (nếu chọn U-1); cấu hình trusted proxy; gói npm; deploy.

## Verification required at Gate 3

`composer audit` trước 9 / sau 0; bộ test như trên; CI trên đúng head xanh.

## Rollback

Revert squash commit (trả `composer.lock` về bản cũ), rồi `composer install`.

## Decision Needed

Owner chọn: `APPROVE GAP-111 Gate 2 U-1` (hoặc U-2) / Changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, phát hành.
