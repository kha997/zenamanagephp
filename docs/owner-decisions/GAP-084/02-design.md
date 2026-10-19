---
work_id: GAP-084
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-19-gap-084-npm-vulnerabilities-evidence.md
  plan: null
  branch: docs/GAP-084-npm-vulnerabilities
  pr: https://github.com/kha997/zenamanagephp/pull/357
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-20T06:44:51+07:00"
  updated_at: "2026-10-20T06:44:51+07:00"
generated_by: agent
---

# GAP-084 — npm vulnerabilities: Gate 2 design

## OWNER GATE 2: AWAITING OWNER

## Owner Summary

Mình đã thử cả 3 phương án trên một bản sao tạm (không commit).

| Phương án | Thay đổi | Lỗ hổng còn lại | Kết quả build |
|---|---|---|---|
| A | chỉ cập nhật `package-lock.json` | 10 (6 high) | CSS giống hệt từng byte |
| **B (khuyến nghị)** | A + vite 8 + laravel-vite-plugin 3 + Node 22 trong `Dockerfile.prod` | 7 (5 high) | build được; CSS chỉ khác do bộ nén mới |
| C | B + tailwindcss 4 | **0** | build được, nhưng giao diện **có thay đổi** (xem dưới) |

- **7 lỗ hổng còn lại của B** đều nằm trong tailwind 3 và các gói nó kéo theo
  (braces, micromatch…). Chúng chỉ chạy lúc build, đọc file cấu hình của chính
  mình, nên rủi ro thực tế thấp. Một số gói trong đó chưa có bản vá.
- **Lý do C chưa nên làm ngay:**
  - Tailwind 4 bỏ các lớp kiểu `bg-opacity-*` (dùng ở 13 file, ví dụ nền mờ
    của hộp thoại).
  - Nó cũng đổi mặc định của `shadow-sm`, `rounded-sm`, `ring`… (85 view có
    dùng). Các trang dùng layout Operator, app, portal hoặc universal-frame
    sẽ khác giao diện. Cần sửa template và kiểm tra bằng mắt.
  - Mình đề xuất tách C thành một Work ID riêng.

## Design: Option B (exact allowlist)

1. **`package-lock.json`**: `npm audit fix` without `--force`. This is in-range only.
   axios, postcss, browserslist, nanoid, source-map-js, socket.io-parser and
   the other packages listed in Gate 3 are updated.
2. **`package.json` + `package-lock.json`**: `vite` `^4.5.0` → `^8.3.4`,
   `laravel-vite-plugin` `^0.8.1` → `^3.2.0`, via `npm install -D`.
3. **`Dockerfile.prod`**: `FROM node:18-alpine` → `FROM node:22-alpine`.
   vite 8 needs Node ≥ 20.19.
4. **Docs**: plan, this packet, 03-release.

No change to `vite.config.js`, `tailwind.config.js`, `postcss.config.js`,
CSS or views. Option A is items 1 and 4 only.

## Verification

- `npm audit` before and after.
- `npm run build` before and after:
  - every entry in `manifest.json` is still produced (10 inputs);
  - the CSS rule set is unchanged after normalising the minifier output.
- Option A: CSS output byte-identical.
- `docker build -f Dockerfile.prod` node stage builds, if the environment allows; otherwise exact-head
  CI (staging-smoke runs `npm ci` on Node 20).
- Governance and docs lints; exact-head CI green.

## Rollback

Revert the squash commit.

## Out of scope

- tailwindcss 4 and its template migration (proposed as a separate Work ID).
- The Vite "native config loader" warning: vite 8 only warns about it, and nothing changes in behaviour.
- PHP packages; deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-084 Gate 2 Option B` (khuyến nghị) / Option A /
Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
