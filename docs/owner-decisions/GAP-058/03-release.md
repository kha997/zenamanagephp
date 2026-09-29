---
work_id: GAP-058
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
  spec: docs/audits/2026-09-29-gap-058-badge-api-evidence.md
  plan: docs/superpowers/plans/2026-09-29-gap-058-badge-api-retirement-implementation.md
  branch: docs/GAP-058-badge-api-500
  pr: https://github.com/kha997/zenamanagephp/pull/326
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
  created_at: "2026-09-30T00:53:03+07:00"
  updated_at: "2026-09-30T00:53:03+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Option-1 retirement at subject 6193aec2: retirement test RED 3/3 at base then GREEN; 242 related tests green locally; route guard OK; SSOT orphan-route lint passes after declaring the intentional negative probe; all 33 exact-head PR checks green; diff = Gate-2 allowlist plus one disclosed line in scripts/ssot/allow_orphan_routes.txt; canonical digest computed at subject."
technical_evidence:
  base_sha: "93fd0d7ab58b8e281affbfab9aef0fc3c6e09389"
  subject_sha: "6193aec2d5ed6fc81ab8329c11fb868db189fba5"
  implementation_tree_digest: "67ab00c990a9de046dcf2255a5ee698107ab38bc4cf36d455c6f3db64edd6c93"
  verified_pr_head_sha: "6193aec2d5ed6fc81ab8329c11fb868db189fba5"
  verified_at: "2026-09-30T00:53:03+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-058 — Gate 3 release decision

## OWNER GATE 3: AWAITING OWNER DECISION

## Gói quyết định phát hành

**1. Vấn đề là gì?** API huy hiệu (8 đường) luôn lỗi 500, tính số luôn ra 0,
token giả, không trang nào gọi tới. Owner chọn: không cần huy hiệu lúc này.

**2. Sau thay đổi:** không còn route `/api/badges/*`, `BadgeController`,
`BadgeService`, component `Sidebar` và view `components/sidebar.blade.php`.
Người dùng không thấy khác biệt (không trang nào hiển thị các thứ này).

**3. Khác biệt so với Gate 2 (cần Owner biết)**

- **Thêm 1 dòng ngoài allowlist:** `scripts/ssot/allow_orphan_routes.txt`
  (`/api/badges|reason=NEGATIVE_PROBE_LEGACY_SURFACE`) và chú thích
  `SSOT_ALLOW_ORPHAN` trong test — lint SSOT (CI `code-quality`) coi chuỗi
  `/api/badges` trong test chứng minh-đã-gỡ là tham chiếu route không tồn
  tại. Cùng quy ước với `DeadMilestoneTemplateApiRemovedTest`.
- **Gán commit:** các file xoá nằm trong commit `test(GAP-058)` thay vì commit
  `refactor(GAP-058)` (đã stage sẵn khi commit). Nội dung cây cuối đúng;
  không viết lại lịch sử (không force-push).

**4. Bằng chứng kỹ thuật**

- Base `93fd0d7a`; subject `6193aec2d5ed6fc81ab8329c11fb868db189fba5`; digest
  `67ab00c990a9de046dcf2255a5ee698107ab38bc4cf36d455c6f3db64edd6c93`.
- `tests/Feature/GAP058/BadgeApiRetiredTest.php`: 3 test (không route
  `api/badges`/`api.badges.*`; class/view không tồn tại; không tham chiếu còn
  sót trong `app`, `resources`, `routes`) — đỏ 3/3 ở base, xanh ở subject.
- Local: 242 test (sidebar config/service, user-preference API, route-middleware
  contracts, GAP-055/057, OwnerGovernance, Architecture) xanh; route guard
  `ROUTE_GUARD_OK`; SSOT orphan lint rc=0.
- CI exact head `6193aec2`: 33/33 pass (lần đầu `c0f3f5ea` đỏ `code-quality`
  đúng vì lint orphan ở trên).
- Ghi nhận (không do GAP-058): `scripts/ssot/lint_tests.sh` cục bộ báo
  `raw_model_create_zena` ở `tests/Feature/Zena/PermissionCanonicalIdentityRegressionTest.php:32`
  — tái hiện y hệt trên `main` sạch.

**5. Ngoài phạm vi** — Hệ thanh bên cũ còn lại (sidebar builder, preset,
`show_badges`, route legacy `/sidebar/badges`); tính năng huy hiệu mới.

**6. Rủi ro còn lại** — Thấp; chỉ gỡ mã không được gọi.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: Approve / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay tính năng huy hiệu mới.
