---
work_id: GAP-102
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-11-02-gap-102-follow-redirects-cve-evidence.md
  plan: null
  branch: docs/GAP-102-follow-redirects-cve
  pr: https://github.com/kha997/zenamanagephp/pull/391
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-02T22:58:39+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-02T22:58:39+07:00"
  updated_at: "2026-11-02T22:58:39+07:00"
generated_by: agent
---

# GAP-102 — follow-redirects GHSA-mrwp-f3q8-3g9p: Gate 2 design

## OWNER GATE 2: AWAITING OWNER

## Owner Summary

Đề xuất **Phương án A**: nâng riêng `follow-redirects` 1.16.1 → 1.16.2 trong `package-lock.json` — đúng 3 dòng
(phiên bản, đường tải, mã kiểm tra), do npm sinh ra. Không đổi `package.json`, không đổi gói nào khác.

## So sánh phương án

| Phương án | Nội dung | Kết luận |
|---|---|---|
| **A. Chỉ 3 dòng của follow-redirects** | Lấy đúng phần npm sinh cho gói này | **Đề xuất** — thay đổi nhỏ nhất, dễ xem lại |
| B. Toàn bộ kết quả của `npm update` | Thêm 6 mục gói đi kèm của `@tailwindcss/oxide-wasm32-wasi` do bản npm trên máy này | Có thay đổi không liên quan |
| C. Thêm vào danh sách bỏ qua `.npm-audit-ignore` | Không vá | Không đề xuất: lỗ hổng có bản vá |

## Thiết kế: Phương án A

### 1. `package-lock.json`

Mục `node_modules/follow-redirects`: `version` 1.16.2,
`resolved` `https://registry.npmjs.org/follow-redirects/-/follow-redirects-1.16.2.tgz`,
`integrity` `sha512-/kVP2U8z596Ia0gwFrwyIeo74ta5aVmSeU1FfAUMAmcEP5EP7zydmag3SLiN64NaRKef3lxsq7i/g7ltJQDGow==` (giá trị npm
sinh ra). Không dòng nào khác.

### 2. Kiểm chứng (đỏ trước / xanh sau)

- Trước: `npm audit --package-lock-only` báo GHSA-mrwp-f3q8-3g9p (high). Sau: 0 high / critical.
- `npm ci --ignore-scripts` cài được từ lock; `npm run build` chạy được.
- CI "Security Vulnerability Scan" xanh trên đúng head.

### 3. Files (allowlist)

`package-lock.json` (3 dòng trên); plan (added after Gate 2 approval) `docs/superpowers/plans/2026-11-02-gap-102-follow-redirects-cve.md`; packet và
evidence GAP-102.

## Rollback

Revert squash commit.

## Explicit Exclusions

`package.json`; gói khác; mã ứng dụng; deploy.

## Decision Needed

Owner chọn: `APPROVE GAP-102 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Chưa duyệt Gate 3, merge hay phát hành.
