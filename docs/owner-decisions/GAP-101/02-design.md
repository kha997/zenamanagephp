---
work_id: GAP-101
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-29-gap-101-symfony-mime-cve-evidence.md
  plan: null
  branch: docs/GAP-101-symfony-mime-cve
  pr: https://github.com/kha997/zenamanagephp/pull/384
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-29T07:02:01+07:00"
  updated_at: "2026-10-29T07:02:01+07:00"
generated_by: agent
---

# GAP-101 — symfony/mime CVE-2026-45070: Gate 2 design

## Owner Summary

Đề xuất **Phương án A**: chạy `composer update symfony/mime` để nâng **chỉ gói này** từ 7.4.19 lên 7.4.22 (bản vá cùng
dòng 7.4). Chỉ đổi `composer.lock`; không gói nào khác thay đổi.

## So sánh phương án

| Phương án | Nội dung | Kết luận |
|---|---|---|
| **A. Nâng `symfony/mime` lên 7.4.22** | 1 gói, bản vá | **Đề xuất** — hết lỗ hổng, CI bảo mật xanh lại |
| B. Bỏ qua cảnh báo trong kiểm tra bảo mật | Thêm vào danh sách bỏ qua | Không nên: đã có bản vá, lỗ hổng nằm trên đường gửi email |

## Thiết kế: Phương án A

- Lệnh: `composer update symfony/mime` (không `--with-dependencies`).
- Kỳ vọng: `composer.lock` chỉ đổi mục `symfony/mime` (phiên bản, nguồn, hash) và `content-hash` nếu có.
- Không đổi `composer.json` (gói không khai báo trực tiếp), không đổi mã ứng dụng.

### Files (allowlist)

`composer.lock` (chỉ mục `symfony/mime`); plan `docs/superpowers/plans/2026-10-29-gap-101-symfony-mime-cve.md`; packet và
evidence GAP-101.

## Verification required at Gate 3

`composer audit --locked`: 0 lỗ hổng; diff `composer.lock` chỉ chạm `symfony/mime`; test liên quan email
(`tests` có dùng Mail) và toàn bộ Unit + Feature + Integration không có lỗi mới so với baseline; exact-head CI xanh,
gồm **Security Vulnerability Scan**.

## Rollback

Revert squash commit (quay về 7.4.19, lỗ hổng trở lại).

## Decision Needed

Owner chọn: `APPROVE GAP-101 Gate 2 Option A` (khuyến nghị) / Option B / Request changes / Decline.

## What the owner is NOT being asked to decide

Chưa duyệt Gate 3, merge, phát hành; không nâng gói nào khác.
