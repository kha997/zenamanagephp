---
work_id: GAP-082
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
  spec: docs/audits/2026-10-15-gap-082-major-package-upgrades-evidence.md
  plan: docs/superpowers/plans/2026-10-15-gap-082-major-package-upgrades.md
  branch: docs/GAP-082-major-package-upgrades
  pr: https://github.com/kha997/zenamanagephp/pull/355
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-15T23:01:23+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-15, verbatim: 'APPROVE GAP-082 Gate 3'. Given after the packet was presented at PR head b64712d798e9a6465871748b4e6e7e2a5086c0a3 (implementation CI 34/34 green at ef4bb8d4); bound to implementation subject ef4bb8d4c4b99b2255c375bd1ffe97695bc69748 and implementation-tree digest c8dd7b20de2d789836e8d9f233552dea0b639b31cfde768ff200826f0024072d (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-15T23:00:07+07:00"
  updated_at: "2026-10-15T23:01:23+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-082 at subject ef4bb8d4: doctrine/dbal 3.10.6 -> 4.5.0 (with carbonphp/carbon-doctrine-types 2.1.1 -> 3.2.1; doctrine/event-manager removed; no code uses Doctrine\\DBAL); laravel/tinker 2.11.1 -> 3.0.2 (symfony/console, symfony/string, symfony/var-dumper 7.4.x -> 7.4.21; tinker --execute smoke ok); the four Api files' @OA docblocks converted to #[OA\\...] attributes on swagger-php 4 (sorted OpenAPI JSON identical before/after; syntax trees identical apart from attributes and the OA import); darkaonline/l5-swagger 8.6.5 -> 11.2.0 (zircote/swagger-php 4.11.1 -> 6.14.0, swagger-api/swagger-ui 5.33.3, symfony/yaml 7.4.21, doctrine/annotations removed) with the generated OpenAPI JSON identical to the old stack's; config/l5-swagger.php unchanged; committed api-docs.json regenerated, adding only the five /api/v1/legacy-routes/* operations and their tag that the code already documented (the committed file was stale). Guzzle 8 excluded (laravel/framework 12 requires guzzle ^7.8.2). composer validate ok, composer audit clean, PHPStan clean, route:list identical, Unit/Feature/Integration locally with only the two known root-only failures (same on main), governance/SSOT/docs lints pass, exact-head CI 34/34 green at ef4bb8d4."
technical_evidence:
  base_sha: "8a961a48fd528036b015a0815dc15b0008ebc1b5"
  subject_sha: "ef4bb8d4c4b99b2255c375bd1ffe97695bc69748"
  implementation_tree_digest: "c8dd7b20de2d789836e8d9f233552dea0b639b31cfde768ff200826f0024072d"
  verified_pr_head_sha: "ef4bb8d4c4b99b2255c375bd1ffe97695bc69748"
  verified_at: "2026-10-15T23:00:07+07:00"
owner_decision_binding:
  implementation_tree_digest: "c8dd7b20de2d789836e8d9f233552dea0b639b31cfde768ff200826f0024072d"
  decision_recorded_at: "2026-10-15T23:01:23+07:00"
---

# GAP-082 — Gate 3 release decision (nâng gói bản lớn)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-10-15 ("APPROVE GAP-082 Gate 3"), bound to implementation subject
`ef4bb8d4c4b99b2255c375bd1ffe97695bc69748` and implementation-tree digest `c8dd7b20de2d789836e8d9f233552dea0b639b31cfde768ff200826f0024072d`. No deployment is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** 4 gói chậm một bản lớn; Guzzle 8 bị Laravel 12 chặn
(Gate 1).

**2. Sau thay đổi (Gate 2, Phương án A):**

| Gói | Trước | Sau | Kéo theo |
|---|---|---|---|
| doctrine/dbal | 3.10.6 | 4.5.0 | carbon-doctrine-types 3.2.1; bỏ doctrine/event-manager |
| laravel/tinker | 2.11.1 | 3.0.2 | symfony/console, string, var-dumper 7.4.21 |
| darkaonline/l5-swagger | 8.6.5 | 11.2.0 | swagger-php 6.14.0, swagger-ui 5.33.3, symfony/yaml 7.4.21; bỏ doctrine/annotations |

- 4 file controller: 25 khối chú thích `@OA` (203 chú thích) chuyển sang
  PHP attribute; không đổi code khác.
- `api-docs.json` sinh lại.

**3. Bằng chứng**

- Base `8a961a48`; subject `ef4bb8d4c4b99b2255c375bd1ffe97695bc69748`; digest `c8dd7b20de2d789836e8d9f233552dea0b639b31cfde768ff200826f0024072d`.
- **Tài liệu API giống hệt** (JSON sắp xếp khoá): docblock vs attribute trên
  swagger-php 4, và swagger-php 4 vs 6.
- `api-docs.json` so với bản đã commit: chỉ **thêm** 5 endpoint
  `/api/v1/legacy-routes/*` + tag "Legacy Route Monitoring" (bản cũ bị lỗi
  thời, code đã có chú thích từ trước).
- Route giống hệt; PHPStan sạch; `composer validate`, `composer audit` (0 lỗ
  hổng) đạt; tinker chạy được.
- Test trên máy: Unit 944, Feature 1767 (mỗi bộ 1 lỗi chỉ do chạy bằng root,
  cũng lỗi trên main), Integration 31 đạt.
- CI exact head `ef4bb8d` 34/34 xanh; lint governance, SSOT, docs đạt.

**4. Công khai**

- Công cụ chuyển chú thích tự viết (Rector không tải được do GitHub chặn);
  không đưa vào repo; kết quả kiểm bằng so sánh JSON.
- Guzzle 8 **không** nâng (cần nâng Laravel trước).

**5. Ảnh hưởng** — Khi deploy: chạy `composer install` theo lock mới; trang
`/api/documentation` hiển thị thêm 5 endpoint legacy-routes. Không đổi API
hay dữ liệu.

**6. Rủi ro còn lại** — Thấp: gói nâng không được code gọi trực tiếp (dbal,
tinker) hoặc đã kiểm bằng so sánh đầu ra (swagger).

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-082 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy; không duyệt nâng Laravel/Guzzle.
