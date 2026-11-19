---
work_id: GAP-127
gate: 3
gate_status: preparing
technical_readiness:
  value: not_checked
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-18-gap-127-dependency-advisories-evidence.md
  plan: docs/superpowers/plans/2026-11-20-gap-127-dependency-advisories.md
  branch: docs/GAP-127-dependency-advisories
  pr: https://github.com/kha997/zenamanagephp/pull/419
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
  created_at: "2026-11-20T05:55:00+07:00"
  updated_at: "2026-11-20T05:55:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-127 at subject 29f72f8647364ed1cd3028293016a214e7b9fa8a (Gate 2 L-1, amendment 1 dropped P-1, amendment 2 option 2-A). L-1: composer.json laravel/framework ^12.71.0; composer.lock changes only laravel/framework v12.69.3 -> v12.73.0 (GHSA-63hm-g96r-2m2q / CVE-2026-128083 fixed in 12.71.0); composer audit --locked exits 0. P-1 dropped: GHSA-fc5q-39xc-vjpf was withdrawn 2026-11-18 15:38 UTC and npm audit reports 0 vulnerabilities, so no .npm-audit-ignore. Amendment 2: Laravel 12.73.0 teardown calls getConnections() on the db instance; 5 tests that mock DB with DB::shouldReceive errored, fixed by adding DB::shouldReceive('getConnections')->andReturn([]) in 4 test files, no assertion removed. Local Unit + Feature + Integration, 3083 tests, compared per test with the 12.69.3 baseline: 0 pass->fail, 1 fail->pass (GateOrderingFrontmatterGrandfatherTest, repo snapshot now complete); the 690 local errors are MissingAppKeyException (worktree has no APP_KEY) and identical in both runs. CI on b2cd780554d6c5e4184307a9fe303878fad5359b (same implementation; the subject only corrects packet timestamps): 36/36 checks green, including Security Vulnerability Scan. Exact-head CI on the subject to be recorded before awaiting_owner."
technical_evidence:
  base_sha: "7256ff8db344fd3315d9dab6415d84d75cd59bf9"
  subject_sha: "29f72f8647364ed1cd3028293016a214e7b9fa8a"
  implementation_tree_digest: "8908e7aa9e7df54e43423bc5addfd007774d9c1b2051dde2637894bb04417419"
  verified_pr_head_sha: null
  verified_at: null
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-127 — Gate 3 release decision (Security Vulnerability Scan green again)

## OWNER GATE 3: PREPARING — waiting for exact-head CI

## Gói quyết định phát hành

**1. Vấn đề là gì?** Kiểm tra bảo mật thư viện trong CI đỏ trên `main` từ đêm 14/11, do 2 cảnh báo mới về thư viện.

**2. Sau thay đổi (Gate 2 L-1, bổ sung 1 và 2)**
- **L-1:** Laravel 12.69.3 → **12.73.0**. `composer.json` yêu cầu tối thiểu 12.71.0. Trong `composer.lock` chỉ Laravel đổi.
- **Bổ sung 1 (bỏ P-1):** cảnh báo picomatch đã bị GitHub **rút lại** ngày 18/11. npm không còn cảnh báo nào, nên không
  cần file ngoại lệ `.npm-audit-ignore`.
- **Bổ sung 2 (2-A):** Laravel 12.73.0 dọn kết nối CSDL sau mỗi test. 5 test dùng CSDL giả bị lỗi vì vậy; đã cho CSDL giả
  trả lời yêu cầu đó (4 file test). Không bỏ hay nới kiểm tra nào, không đổi code sản phẩm.

**3. Đã kiểm thử** — base `7256ff8d`; subject `29f72f8647364ed1cd3028293016a214e7b9fa8a`; digest
`8908e7aa9e7df54e43423bc5addfd007774d9c1b2051dde2637894bb04417419`.

- `composer audit` sạch; `npm audit` 0 cảnh báo.
- Toàn bộ 3.083 test chạy trên máy, so từng test với bản cũ (12.69.3): **không test nào từ xanh thành đỏ**.
  690 lỗi ở máy local là do worktree thiếu `APP_KEY`, giống hệt ở cả hai lần chạy.
- CI trên `b2cd7805` (cùng nội dung code, subject chỉ sửa giờ ghi trong gói quyết định): **36/36 xanh**, gồm Security
  Vulnerability Scan.

**4. Lưu ý vận hành**
- Sau khi merge, deploy SHA mới qua **Production Deployment** theo `docs/runbooks/go-live.md`.
- Không có migration, không đổi cấu hình máy chủ.

**5. Ngoài phạm vi** — Laravel 13; nâng các thư viện khác.

**6. Rủi ro còn lại** — Thấp. Nâng bản vá nhỏ của Laravel trong cùng dòng 12.x.

**7. Hoàn tác** — Revert PR (`composer.lock` về 12.69.3), rồi deploy lại.

**8. Đề xuất** — Phát hành (merge), rồi deploy.

## Decision Needed

Chưa có — chờ CI trên đúng head.
