---
work_id: GAP-128
gate: 3
gate_status: approved
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-18-gap-128-websocket-server-evidence.md
  plan: docs/superpowers/plans/2026-11-20-gap-128-websocket-server.md
  branch: docs/GAP-128-websocket-server
  pr: https://github.com/kha997/zenamanagephp/pull/418
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-20T08:48:18+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-20, verbatim: 'APPROVE GAP-128 Gate 3' (answer to 'Approve GAP-128 Gate 3 (merge despite the existing scan failure, then deploy)?'). Given on PR #418 head a6b075941b3c434885cf579c21ba2125772b2bbc after exact-head CI: 34 checks green; Security Vulnerability Scan red with the composer advisories already red on main (GAP-127, PR #419), PR touches no dependency file; Owner Governance Lint red only through its live check of that scan. Bound to implementation subject 719750d737bdf7daa9f2295ae6785071e2ef8df4 and implementation-tree digest 0db6c6b7425d782f32dcc869dbaaa465149fe62da35977182e6ae51fa78655c7. Authorizes merge and deployment of the merged SHA via Production Deployment; host cleanup (H-1) follows health_verified, by whoever holds root."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-20T04:57:04+07:00"
  updated_at: "2026-11-20T08:48:18+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-128 at subject 719750d737bdf7daa9f2295ae6785071e2ef8df4 (Gate 2 A, S-1, H-1 + amendment 1), base 7256ff8d. Removed websocket:serve (WebSocketServerCommand), DashboardWebSocketHandler, scripts/start-websocket-server.sh and their 9 phpstan-baseline entries; phpstan.neon drops the deleted app/WebSocket exclude (amendment 1); production.yml no longer restarts zena-websocket at cutover or auto-rollback; gap-049-host-provisioning.md drops the websocket unit and its sudo line and adds the retirement procedure (root, only after a GAP-128 deploy reaches health_verified); go-live.md points to it. New WebSocketServerRetiredTest (3): red before (3 failures), green after. Local: tests/Feature/Deployment green except 2 ProductionUserCommandTest errors from the worktree having no APP_KEY; PHPStan exit 0; php-cs-fixer clean; owner-governance-lint PASS. Exact-head CI on a5c7b40e (03-release.md-only commit after the subject, same digest): 35 checks green incl. Unit, Feature, Integration, API, PHPStan (Security Tests, Code Quality Analysis), MySQL concurrency suites, browser-tests, staging-smoke, Build Dockerfile; Security Vulnerability Scan red with the composer advisories already red on main (GAP-127, PR #419 open); this PR touches no dependency file."
technical_evidence:
  base_sha: "7256ff8db344fd3315d9dab6415d84d75cd59bf9"
  subject_sha: "719750d737bdf7daa9f2295ae6785071e2ef8df4"
  implementation_tree_digest: "0db6c6b7425d782f32dcc869dbaaa465149fe62da35977182e6ae51fa78655c7"
  verified_pr_head_sha: "a5c7b40ea40dba395cbd3d0b32b566cfa54116de"
  verified_at: "2026-11-20T05:52:55+07:00"
owner_decision_binding:
  implementation_tree_digest: "0db6c6b7425d782f32dcc869dbaaa465149fe62da35977182e6ae51fa78655c7"
  decision_recorded_at: "2026-11-20T08:48:18+07:00"
---

# GAP-128 — Gate 3 release decision (retire the websocket server)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-11-20 ("APPROVE GAP-128 Gate 3"), bound to implementation subject
`719750d737bdf7daa9f2295ae6785071e2ef8df4` and implementation-tree digest `0db6c6b7425d782f32dcc869dbaaa465149fe62da35977182e6ae51fa78655c7`.
Merge with the pre-existing red Security Vulnerability Scan (same failure on main, GAP-127 / PR #419; no dependency file
in this PR) and deployment of the merged SHA via Production Deployment are authorized. Host cleanup follows H-1: only
after that deploy reaches `health_verified`, whoever holds root removes the placeholder unit and its sudo line.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Lệnh `websocket:serve` không bao giờ chạy được (thiếu thư viện Ratchet) và không trang nào kết nối
tới nó. Trên máy chủ thật, dịch vụ `zena-websocket` là một dịch vụ giả `/bin/true`, giữ lại chỉ để workflow deploy gọi
"khởi động lại".

**2. Sau thay đổi (Gate 2 A, S-1, H-1, bổ sung 1)**
- Xoá lệnh `websocket:serve`, handler và script khởi động.
- `production.yml` không còn khởi động lại `zena-websocket` (cả lúc chuyển bản và lúc tự rollback).
- Runbook dựng máy bỏ dịch vụ websocket và quyền sudo của nó; thêm mục **"Retiring the old zena-websocket unit"**.
  `go-live.md` trỏ tới mục đó.
- **Bổ sung 1:** `phpstan.neon` bỏ dòng loại trừ thư mục `app/WebSocket` đã xoá.
- Người dùng không thấy gì thay đổi. Các mã realtime chết khác giữ nguyên (S-1).

**3. Đã kiểm thử** — base `7256ff8d`; subject `719750d737bdf7daa9f2295ae6785071e2ef8df4`; digest
`0db6c6b7425d782f32dcc869dbaaa465149fe62da35977182e6ae51fa78655c7`.
- Test mới `WebSocketServerRetiredTest` (3 test), đỏ trước khi sửa, xanh sau: lệnh không còn; `production.yml` không
  nhắc `zena-websocket`; runbook không cấp sudo và không yêu cầu dịch vụ websocket.
- CI trên đúng head `a5c7b40e` (chỉ thêm file này sau subject, digest không đổi): **35 check xanh** — Unit, Feature,
  Integration, API, PHPStan, các bộ MySQL, browser-tests, staging-smoke, build Docker.
- **Security Vulnerability Scan đỏ**: cùng lỗi composer advisories đang đỏ sẵn trên main, do GAP-127 (PR #419) xử lý.
  PR này không đụng file dependency. Owner Governance Lint có thể đỏ theo, chỉ qua bước kiểm tra trực tiếp check này.

**4. Lưu ý vận hành (H-1, đúng thứ tự)**
1. Merge.
2. Deploy SHA mới qua **Production Deployment**, chờ `health_verified`.
3. **Sau đó** người có root trên `app.zena.com.vn` làm theo mục "Retiring the old zena-websocket unit" trong
   `docs/runbooks/gap-049-host-provisioning.md`: tắt và xoá unit, `daemon-reload`, xoá dòng sudo.
   Làm bước 3 trước bước 2 sẽ làm deploy hỏng ở bước restart.

**5. Ngoài phạm vi** — tính năng realtime; các mã realtime chết khác (API `api/websocket/*`, `realtime/*`, kiểm tra cổng
6001, Echo trong layout cũ).

**6. Rủi ro còn lại** — Thấp. Chỉ xoá mã không chạy được và 2 dòng restart trong workflow.

**7. Hoàn tác** — Revert PR. Nếu đã xoá unit trên máy chủ, dựng lại unit `/bin/true` và dòng sudo trước khi deploy bản cũ.

**8. Đề xuất** — Phát hành (merge dù Security Vulnerability Scan đỏ sẵn), deploy, rồi dọn máy chủ theo H-1.

## Decision Needed

Owner chọn: `APPROVE GAP-128 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

GAP-127 (dependency advisories); tính năng realtime.
