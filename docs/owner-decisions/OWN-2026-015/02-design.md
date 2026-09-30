---
work_id: OWN-2026-015
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-09-30-own-2026-015-gap058-059-post-release-reconciliation.md
  plan: null
  branch: docs/OWN-2026-015-gap058-059-post-release-reconciliation
  pr: https://github.com/kha997/zenamanagephp/pull/328
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
  created_at: "2026-09-30T17:45:27+07:00"
  updated_at: "2026-09-30T17:45:27+07:00"
generated_by: agent
---

# OWN-2026-015 — GAP-058/059 post-release register reconciliation: Gate 2 design

## OWNER GATE 2: AWAITING OWNER DECISION

## Owner Summary

Sửa đúng 2 dòng GAP-058 và GAP-059 trong sổ sang "đã giải quyết" và thêm một
bản ghi đối soát riêng. Không sửa code hay hồ sơ đã duyệt. Đề xuất
**Phương án B** — giống OWN-2026-011/013/014.

## So sánh phương án

| Phương án | Nội dung | Kết luận |
|---|---|---|
| A. Chỉ sửa register | 2 dòng | Hợp lệ nhưng yếu hơn B |
| **B. Register + bản ghi đối soát riêng** | 2 dòng + `docs/audits/2026-09-30-own-2026-015-reconciliation-record.md` | **Đề xuất** |
| C. Append vào Gate-3 packet GAP-058/059 | Mutate bằng chứng lịch sử ngoài digest | Loại |

## Thiết kế: Phương án B

Đúng hai file nội dung:

1. `OPERATIONAL_GAP_REGISTER.md` — sửa cột Status và cột bằng chứng của dòng
   GAP-058 (Tier 6) và GAP-059 (Tier 2); không đổi dòng nào khác.
2. `docs/audits/2026-09-30-own-2026-015-reconciliation-record.md` — bản ghi mới
   với governed frontmatter (`work_id: OWN-2026-015`,
   `owner_governance_version: 1`,
   `owner_gate_2_record: docs/owner-decisions/OWN-2026-015/02-design.md`).

### Contract dòng

Cả hai: Status bắt đầu chính xác bằng `**RESOLVED (verified 2026-09-30)**`,
tiếp theo `Released to main via PR #<n> at squash SHA <sha>`, subject + digest
Gate-3 đã duyệt, `neither is recomputed or rebound by OWN-2026-015`,
`Not deployed`. Cột bằng chứng thêm Gate 1/2/3 packets, evidence audit, URL PR,
reconciliation record. Giá trị lấy từ bảng "Release facts" của Gate-1 evidence.

- **GAP-058:** ghi quyết định sản phẩm của Owner tại Gate 1 (không cần huy hiệu
  menu lúc này) → gỡ bề mặt huy hiệu chết; phần còn lại của hệ thanh bên cũ
  giữ nguyên; một dòng khai báo trong `scripts/ssot/allow_orphan_routes.txt`.
- **GAP-059:** tóm tắt sửa (từ chối `--password`, `--password-stdin`, ghi
  `.env` có quote/escape, script không `sed`/`.env.bak`, backup 0600); **việc
  vận hành còn mở**: nếu `scripts/configure-production-smtp.sh` từng chạy trên
  máy thật, dọn `.env.backup.*`/`.env.bak` và đổi mật khẩu SMTP — ngoài repo,
  chưa làm.

### Contract bản ghi đối soát

Merge facts 2 PR; post-merge CI push trên mỗi squash SHA; không có
`production.yml` run; SHA-256 của `docs/owner-decisions/GAP-058/03-release.md`
và `GAP-059/03-release.md` bằng nhau giữa `origin/main` và subject; việc vận
hành còn mở (GAP-056 và GAP-059). Không tự nhận là Gate packet.

## Allowlist và digest

Register, bản ghi đối soát, Gate-1 evidence và 01/02 của OWN-2026-015 nằm
trong digest; `03-release.md` của OWN-2026-015 bị loại (self-exclusion); Gate-3
packets của GAP-058/059 bị loại (cross-work) và phải byte-identical.

## Verification trước Gate 3

Diff chỉ gồm allowlist; lint + gate ordering; `tests/Unit/OwnerGovernance`;
SHA-256 packet lịch sử không đổi; required CI xanh exact head; digest
canonical; CI push sau merge của GAP-059 đã hoàn tất xanh.

## Rollback

Revert commit thường.

## Explicit Exclusions

Sửa code; workflow a11y/perf hằng ngày; vào máy chủ; đổi mật khẩu; deploy.

## Decision Needed

Owner chọn: Approve Phương án B / Approve Phương án A / Request changes /
Decline.

## What the owner is NOT being asked to decide

Không duyệt Gate 3, merge hay deployment.
