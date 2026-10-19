---
work_id: GAP-083
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-19-gap-083-security-scan-gating-evidence.md
  plan: null
  branch: docs/GAP-083-security-scan-gating
  pr: https://github.com/kha997/zenamanagephp/pull/356
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
  created_at: "2026-10-19T22:25:00+07:00"
  updated_at: "2026-10-19T22:25:00+07:00"
generated_by: agent
---

# GAP-083 — Make security scans fail CI: Gate 2 design

## OWNER GATE 2: AWAITING OWNER

## Owner Summary

Chỉ sửa workflow `.github/workflows/code-quality-security.yml`; không đổi gói
hay code ứng dụng. Trên main hiện tại, mọi phần sẽ bật chặn đều sạch, nên
không PR nào đang có bị đỏ.

- **Composer:** kiểm tra "Security Vulnerability Scan" đỏ khi `composer audit`
  thấy lỗ hổng (gói bị bỏ rơi chỉ báo, không chặn).
- **Docker image:** kiểm tra "Docker Security Scan" đỏ khi Trivy thấy lỗ hổng
  **HIGH/CRITICAL đã có bản vá** (lỗ hổng chưa có bản vá chỉ báo, vì không sửa
  được).
- **Bỏ bước hỏng** "PHPStan Security Rules" (cài gói không tồn tại, chưa từng
  chạy được).
- **Ngoại lệ:** muốn bỏ qua một lỗ hổng phải thêm mã lỗ hổng kèm lý do vào
  file trong repo (`composer.json` → `config.audit.ignore`, hoặc
  `.trivyignore`), nên luôn đi qua review PR.
- **npm:** hai phương án:
  - **A:** chỉ báo trong trang tóm tắt CI, không chặn.
  - **B (khuyến nghị):** chặn lỗ hổng **critical**, báo phần còn lại.
    Hiện có 0 critical nên không đỏ. 12 lỗ hổng high để một Work ID sau sửa
    (cần nâng vite 8, tailwindcss 4).

Lưu ý: lần quét theo lịch hằng đêm trên main cũng sẽ đỏ khi có lỗ hổng mới
được công bố. Đó chính là tín hiệu cần có.

## Design: Option B (exact allowlist)

1. `.github/workflows/code-quality-security.yml`:
   - **security-scan job:**
     - The audit step runs `composer audit --abandoned=report
       --format=json > composer-audit.json` and records its exit code instead
       of `|| true`.
     - A new last step, "Fail on Composer security advisories", fails the
       job when that exit code is non-zero.
     - The reports, the artifact upload and the PR comment still run first.
     - Remove the "PHPStan Security Rules" step, its summary section and its
       two artifact paths.
   - **security-scan job — new "Run npm audit" step:**
     - Runs `npm audit --package-lock-only --json > npm-audit.json`.
     - It is summarized in the step summary and uploaded with the security
       reports. There is no PR comment, so the existing high findings do not
       post on every PR.
     - The final gate step also fails on
       `npm audit --package-lock-only --audit-level=critical`.
   - **docker-security job:** a new last step, "Fail on fixable HIGH/CRITICAL
     image vulnerabilities", re-runs `aquasecurity/trivy-action@0.35.0` on
     `zenamanage:test` with:
     - `exit-code: 1`, `severity: CRITICAL,HIGH`, `ignore-unfixed: true`,
       `format: table`;
     - `.trivyignore` read when present.

     The DB and binary are cached. The SARIF and JSON reports and the PR
     comment are unchanged.
2. Exception mechanism, documented only. No file is created until it is
   needed:
   - **Composer:** `config.audit.ignore` in `composer.json`, with a reason
     for each advisory.
   - **Docker:** a CVE in `.trivyignore`, with a comment line giving the
     reason.
3. Docs: plan, this packet, 03-release, and the evidence file.

**Option A** is the same as B, except npm audit is report-only (no
critical gate).

## Verification

- **Red/green proof on the PR:** each gate is shown red on its own temporary
  commit, then reverted:
  - a composer advisory: temporarily lock a version with a known advisory;
  - an npm critical: the same approach;
  - a Trivy finding: temporarily drop `ignore-unfixed` and widen the
    severity to every level.
  The final head is green.
- YAML parse check of the workflow; governance, SSOT and docs lints;
  Exact-head CI green.

## Rollback

Revert the squash commit. Only the workflow file and docs change.

## Out of scope

- Fixing the npm high/moderate findings (a separate Work ID).
- `automated-testing.yml` security-tests: `composer audit || true` stays
  report-only, because it duplicates the new gate.
- `release-management.yml` Trivy: the release path is retired (GAP-049).
- `enlightn/security-checker` stays report-only, because it uses the same
  advisories as `composer audit`.
- Dependabot and deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-083 Gate 2 Option B` (khuyến nghị) / Option A /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy; sửa lỗ hổng npm.
