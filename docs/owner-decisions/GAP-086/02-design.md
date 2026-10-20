---
work_id: GAP-086
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-20-gap-086-npm-audit-high-gate-evidence.md
  plan: docs/superpowers/plans/2026-10-20-gap-086-npm-audit-high-gate.md
  branch: docs/GAP-086-npm-audit-high-gate
  pr: https://github.com/kha997/zenamanagephp/pull/359
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-20T19:55:44+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-20, verbatim: 'APPROVE GAP-086 Gate 2 Option A'. Reviewed design head: 85ca544e31902a9f4b3a4b922d60cd0604ec1aab. Approves Option A and its exact allowlist (.github/workflows/code-quality-security.yml final gate step: npm high/critical advisories counted from via[] GHSA ids, minus ids in an optional .npm-audit-ignore file, report included; .npm-audit-ignore not created; docs); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-20T19:54:52+07:00"
  updated_at: "2026-10-20T19:55:44+07:00"
generated_by: agent
---

# GAP-086 — npm audit gate at high: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-20 against reviewed design head
`85ca544e31902a9f4b3a4b922d60cd0604ec1aab`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

- **Phương án A (khuyến nghị):**
  - Kiểm tra "Security Vulnerability Scan" đỏ khi `npm audit` có lỗ hổng
    **high hoặc critical**.
  - Có thêm file ngoại lệ `.npm-audit-ignore`: mỗi dòng một mã lỗ hổng
    (GHSA) kèm lý do. Lỗ hổng có trong file được bỏ qua. File này nằm trong
    repo nên thêm ngoại lệ luôn phải qua review PR, giống Composer và Trivy.
  - Hiện npm có 0 lỗ hổng, nên không PR nào bị đỏ.
- **Phương án B:** như A nhưng không có file ngoại lệ. Nếu có lỗ hổng high chưa
  vá thì phải ghim phiên bản gói hoặc sửa workflow mới qua được.

**Kiểm chứng:** mỗi trường hợp dùng một commit tạm, rồi hoàn tác:

1. thêm một gói có lỗ hổng **high** (không critical): CI phải **đỏ**;
2. ghi mã lỗ hổng đó vào `.npm-audit-ignore`: CI phải **xanh**.

Đề xuất **Phương án A**.

## Design: Option A (exact allowlist)

1. **`.github/workflows/code-quality-security.yml`**, Security Vulnerability Scan, final gate step:
   - The gate counts distinct advisories with severity `high` or `critical`.
     It reads them from `.vulnerabilities[].via[]` objects in `npm-audit.json`,
     and the GHSA id is the last segment of the advisory URL.
   - It excludes ids listed in `.npm-audit-ignore`, if that file exists. Each
     line holds a GHSA id followed by `#` and a reason; blank lines and
     comment-only lines are skipped.
   - It fails when the remaining count is above 0, and prints the
     advisories it counted.
   - A missing or unreadable report still fails.
   - The step name becomes "Fail on Composer advisories or npm high/critical vulnerabilities".
   - The step summary also lists the ignored advisories.
   - Composer and Trivy gates are unchanged.
2. **`.npm-audit-ignore`**: not created now. The mechanism is documented in
   the workflow comment and in this packet.
3. **Docs**: plan, this packet, 03-release.

## Verification

- Red/green proof on the PR with temporary commits, each reverted:
  - a dependency with a known **high**, non-critical advisory → Security Vulnerability Scan red, listing it;
  - the same dependency plus its GHSA id in `.npm-audit-ignore` → green.
- The final head has none of it. Exact-head CI is green; governance and docs lints pass.

## Rollback

Revert the squash commit.

## Out of scope

- Moderate and low findings (they stay report-only).
- Composer and Trivy gates.
- Deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-086 Gate 2 Option A` (khuyến nghị) / Option B /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.

## Owner amendment (2026-10-20T21:12:09+07:00)

Owner selection via AskUserQuestion, verbatim: "Sửa trong PR này (Recommended)".

This amendment answers a Codex review finding (P2) on PR #359: a bare `GHSA-...` line,
or one with an empty `#`, was still treated as an exception, although this design
requires every entry to carry a reason. It authorizes, in the same gate step of
`.github/workflows/code-quality-security.yml`:

- each non-blank, non-comment line of `.npm-audit-ignore` must be a GHSA id followed
  by `#` and a non-empty reason; any other line fails the step and is printed with its
  line number;
- only valid entries are used as exceptions.

The Gate 3 approval recorded before this amendment is superseded; Gate 3 is re-presented.
