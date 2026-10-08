---
work_id: GAP-070
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_correction_or_defer
references:
  spec: docs/audits/2026-10-08-gap-070-composer-security-advisories-evidence.md
  plan: docs/superpowers/plans/2026-10-08-gap-070-composer-security-advisories.md
  branch: docs/GAP-070-composer-security-advisories
  pr: https://github.com/kha997/zenamanagephp/pull/343
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-08T16:31:29+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-08T16:31:29+07:00"
  updated_at: "2026-10-08T16:31:29+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-070 at subject a1db318d (lockfile commit 4f91ff14 plus a merge of main 989bf88b, GAP-069): composer.lock moves exactly six packages (guzzlehttp/guzzle 7.13.2->7.15.5, guzzlehttp/promises 2.5.0->2.5.3, guzzlehttp/psr7 2.12.3->2.13.1, laravel/framework v12.63.0->v12.69.3, league/commonmark 2.8.2->2.10.3, league/flysystem 3.35.2->3.36.0); no composer.json, code, config, migration, Dockerfile or workflow change. Red first: composer audit 20 advisories on base, 0 after. Local: Unit 943 and Feature+Integration 1794 tests with one root-only filesystem-permission failure each (also failing on main as root); 115 Treasury tests green after the merge; full PHPStan clean; SSOT, governance and docs lints pass; exact-head PR checks 34/34 green; CI Security Scan Report: no vulnerabilities."
technical_evidence:
  base_sha: "989bf88b3f51e9329500decf92ae9b425f9d2a4d"
  subject_sha: "a1db318dbf3ed3a0bb4f66d3b174907806460d6f"
  implementation_tree_digest: "52965e677826800f1ef8c6bb5c2143fdffe0d42e285f855495f77db9db2cb45c"
  verified_pr_head_sha: "a1db318dbf3ed3a0bb4f66d3b174907806460d6f"
  verified_at: "2026-10-08T16:31:29+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-070 — Gate 3 release decision (xoá 20 lỗ hổng Composer)

## Gói quyết định phát hành

**1. Vấn đề là gì?** `composer audit` báo 20 lỗ hổng ở `league/commonmark`
(12), `guzzlehttp/guzzle` (6), `laravel/framework` (1), `league/flysystem` (1)
(Gate 1).

**2. Sau thay đổi (Gate 2, Phương án A):** chỉ `composer.lock` đổi, đúng 6 gói:

| Gói | Trước | Sau |
|---|---|---|
| guzzlehttp/guzzle | 7.13.2 | 7.15.5 |
| guzzlehttp/promises | 2.5.0 | 2.5.3 |
| guzzlehttp/psr7 | 2.12.3 | 2.13.1 |
| laravel/framework | v12.63.0 | v12.69.3 |
| league/commonmark | 2.8.2 | 2.10.3 |
| league/flysystem | 3.35.2 | 3.36.0 |

Không đổi `composer.json`, code, cấu hình, migration, Dockerfile hay workflow.

**3. Khác biệt so với Gate 2** — Không có về nội dung. Sau khi GAP-069 được
merge, nhánh đã gộp main (`989bf88b`) để CI kiểm đúng tổ hợp sẽ lên main; diff
so với main vẫn chỉ là `composer.lock` và tài liệu GAP-070.

**4. Bằng chứng kỹ thuật**

- Base `989bf88b`; subject `a1db318dbf3ed3a0bb4f66d3b174907806460d6f`; digest
  `52965e677826800f1ef8c6bb5c2143fdffe0d42e285f855495f77db9db2cb45c`.
- **Đỏ trước:** `composer audit` trên base: 20 lỗ hổng. Sau cập nhật: **0**.
- Test cục bộ: Unit 943 test, Feature + Integration 1794 test — mỗi bộ có 1 lỗi
  chỉ xảy ra khi chạy bằng root (test quyền ghi file; cũng lỗi trên main khi chạy
  root, CI không chạy root và xanh). 115 test Treasury xanh sau khi gộp main.
- PHPStan toàn repo sạch; SSOT lint, governance lint, docs-lint đạt;
  `composer validate` đạt.
- CI exact head `a1db318`: 34/34 pass (gồm các job MySQL thật và
  `browser-tests`). Bot "Security Scan Report": không còn lỗ hổng. Báo cáo gói
  cũ: 91 → 88.

**5. Ngoài phạm vi** — `doctrine/annotations` bị đánh dấu bỏ (abandoned, không
phải lỗ hổng); lỗ hổng gói hệ thống trong Docker image (bước 2); nâng hàng loạt
gói và nâng phiên bản chính (bước 3); định dạng code (bước 4); chặn CI theo
`composer audit` (bước 5); deploy.

**6. Rủi ro còn lại** — Thấp. Chỉ nâng bản vá/bản nhỏ trong giới hạn phiên bản
hiện có; toàn bộ test và CI xanh.

**7. Hoàn tác** — Revert squash commit (khôi phục `composer.lock` cũ).

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-070 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy.
