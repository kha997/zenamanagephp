---
work_id: GAP-094
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
  spec: docs/audits/2026-10-26-gap-094-treasury-s4b-routes-readiness.md
  plan: docs/superpowers/plans/2026-10-27-gap-094-treasury-s4b-routes.md
  branch: docs/GAP-094-treasury-s4b-routes
  pr: https://github.com/kha997/zenamanagephp/pull/373
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-27T08:11:20+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-27, verbatim: 'APPROVE GAP-094 Gate 3'. Given after the re-presented packet (after Owner amendment 2) at PR head 447b89d6b625bd13f2efc59b0f2996d8b592abd9; bound to implementation subject a664d144cbe7480087fe91c34d8241ced1e83091 and implementation-tree digest 0903dcabdd625d7351ef56b46477b5ef025220604e21c720ebd0fce93a52485c (recomputed at recording time, zero drift). Supersedes the earlier approval at digest f1171331. Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment or release authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-27T07:27:03+07:00"
  updated_at: "2026-10-27T08:11:20+07:00"
generated_by: agent
residual_risk_rating: medium
mandatory_technical_gate_summary: "RE-PRESENTED after Owner amendment 2 (AskUserQuestion, 'Sửa trong PR này (Recommended)'): the Owner Gate-3 approval bound to subject a97e7ec81be807dabc229018233327d2191f1913 / digest f117133143eae0de9d309f211be49df9fa00867828dba51de3c6ce611837c98a is superseded because a664d144 changed the tree to answer a Codex P2 finding: reverseLeg() could deadlock on MySQL with a concurrent reconciliation of the same route (shared lock on the leg credit entry vs. id-ordered exclusive locks); it now locks every ledger entry of the route id-ascending right after the to-wallet lock and before the balance read; new test test_leg_reversal_locks_the_route_entries_before_the_balance_read is red before the fix and green after. Local after the fix: tests/Feature/Treasury 138 OK, real-MySQL Treasury concurrency job 5/5 OK, PHPStan, php-cs-fixer, SSOT lint clean. Exact-head CI at a664d144: 34/35 green; Owner Governance Lint red only because the packet was bound to the superseded digest, which this re-presented packet resolves. History below describes a97e7ec8. GAP-094 (Treasury S4b-1) at subject a664d144cbe7480087fe91c34d8241ced1e83091 (Gate 2 Option A plus Owner amendments 1 and 2). New TreasuryRouteService: createRoute (funding / owner_contribution / internal_transfer; document via_route + approved, amount frozen; route planned with total = amount, Case B link; S2 validation incl. owner-party rule and duplicate warning, which now also sees routes on their way); recordLeg (one step, settled; wallet-to-wallet debit/credit pair or a lone credit from outside on funding routes only; from-wallet balance guard; money from outside <= route amount; transfer net out of the source <= amount; no leg on completed/cancelled routes; occurred_at not in the future; permission treasury.manage_routes, or treasury.create_transfer when the user holds the from- or to-wallet); completion per v17 4.3 after every leg and leg reversal (destination +amount, transfer source -amount, other wallets 0) posts the document (posted_unreconciled, posted_at); reverseLeg (reason; leg reversed + one compensating entry per original entry; to-wallet must still hold the amount; completed route back to partial; reconciled document regresses); cancelRoute (reason; only while the document is approved and no leg is in force; route cancelled, document rejected). Locks: leg 0 -> shared 4 -> 5 -> 6, settled legs read with a locking read once the route is held; reversal 0 -> 4 -> 5 -> 6; cancel 5 -> 6. TreasuryPostingService reverses a via_route original only when its route is completed (read under the route lock, 5 -> 6) and posts the swapped shape directly. TreasuryReconciliationService accepts leg entries, maps them to the route's document, and reconciles a via_route document when its route is completed and every leg entry has an active apply; leg entries are re-read with locking reads only after the route lock. New permission treasury.manage_routes (owner, accountant); gates treasury.manage-routes, treasury.record-route-leg. API TreasuryRouteController (6 endpoints) and operator pages (route section + create form on the project page, route page, register badge). Owner amendment 1: the S4a test that rejected leg entries now asserts they reconcile with the route's document. Tests: TreasuryRouteServiceTest 16, TreasuryRouteApiTest 3, TreasuryRouteWebTest 3, two real-MySQL races (transit wallet drained twice; money from outside over the route amount) whose lock-removal mutation goes red on both. Local: tests/Feature/Treasury 137 OK; tests/Feature/Api 806 OK; tests/Feature/Web 50 OK; Zena 290 OK; Architecture, Deployment OK; Unit OK except the known root-only GrandfatherLoaderTest; route guard and RouteHygieneTest OK; PHPStan no errors; php-cs-fixer 0 files; SSOT lint, governance and docs lints pass. Exact-head CI at 81697bee: Code Quality Analysis red for import order in the concurrency test, fixed in a97e7ec8; exact-head CI at a97e7ec8: 35/35 green."
technical_evidence:
  base_sha: "740b84500a805805bea4f9a81758ea6ea2691a30"
  subject_sha: "a664d144cbe7480087fe91c34d8241ced1e83091"
  implementation_tree_digest: "0903dcabdd625d7351ef56b46477b5ef025220604e21c720ebd0fce93a52485c"
  verified_pr_head_sha: "a664d144cbe7480087fe91c34d8241ced1e83091"
  verified_at: "2026-10-27T08:04:22+07:00"
owner_decision_binding:
  implementation_tree_digest: "0903dcabdd625d7351ef56b46477b5ef025220604e21c720ebd0fce93a52485c"
  decision_recorded_at: "2026-10-27T08:11:20+07:00"
---

# GAP-094 — Gate 3 release decision (Treasury S4b-1 via-route payments)

## OWNER GATE 3: APPROVED

Owner approved the re-presented Gate 3 in-session on 2026-10-27 ("APPROVE GAP-094 Gate 3"), bound to implementation subject
`a664d144cbe7480087fe91c34d8241ced1e83091` and implementation-tree digest `0903dcabdd625d7351ef56b46477b5ef025220604e21c720ebd0fce93a52485c`. No deployment or release is authorized.

## Gói quyết định phát hành (trình lại)

Owner đã duyệt Gate 3 tại digest `f1171331…` ("APPROVE GAP-094 Gate 3"). Sau đó Codex báo lỗi P2: đảo chặng chạy cùng
lúc với đối soát cùng tuyến có thể deadlock trên MySQL (hai bên khoá bút toán của chặng theo thứ tự khác nhau). Owner
chọn "Sửa trong PR này (Recommended)" (Owner amendment 2). Cây file đổi nên lần duyệt trên **không còn hiệu lực**.

**1. Vấn đề là gì?** Không ghi được tiền đi qua trung gian (TK công ty, người mang tiền); số dư ví lệch thực tế
trong lúc tiền đang đi (Gate 1, phần S4b-1).

**2. Sau thay đổi (Gate 2 Phương án A + Owner amendment 1, 2)** — subject `a97e7ec8`, rồi `a664d144`:
- Chủ DN / kế toán **tạo tuyến** cho tiền nhận, góp vốn, chuyển nội bộ; chứng từ "Đang trên đường".
- **Ghi chặng** một bước; số dư ví đổi ngay. PM/kỹ sư chỉ ghi chặng ra hoặc vào ví mình giữ.
- Tuyến **tự hoàn tất** khi ví đích đủ tiền và ví trung gian về 0 → chứng từ ghi sổ, tiền nhận mới cộng vào tổng.
- **Đảo chặng** (lý do), **huỷ tuyến** (khi chưa có chặng hiệu lực), **đảo chứng từ** khi tuyến đã hoàn tất.
- **Đối soát** bút toán chặng theo ví; chứng từ "đã đối soát" khi tuyến hoàn tất và mọi bút toán chặng đã đối soát.
- Đảo chặng khoá mọi bút toán của tuyến theo thứ tự id trước khi đọc số dư (cùng thứ tự với đối soát).
- Quyền mới `treasury.manage_routes` (chủ DN, kế toán). Không đổi cơ sở dữ liệu.
- API và trang web: mục "Tuyến tiền" ở trang ngân quỹ dự án, trang chi tiết tuyến, nhãn "Qua trung gian" ở sổ.

**3. Bằng chứng** — base `740b8450`; subject `a664d144cbe7480087fe91c34d8241ced1e83091`;
digest `0903dcabdd625d7351ef56b46477b5ef025220604e21c720ebd0fce93a52485c`.
- Test mới: 17 test service (thêm test thứ tự khoá, đỏ trước khi sửa), 3 API, 3 web; 2 test tranh chấp trên **MySQL
  thật** — gỡ khoá thì cả hai đỏ.
- Trên máy: 138 test Treasury, job tranh chấp MySQL 5/5; trước đó 806 test API, 50 Web, 290 Zena, Architecture,
  Deployment, Unit qua (trừ test chỉ hỏng khi chạy bằng root); PHPStan, php-cs-fixer, SSOT lint sạch.
- CI chạy đúng trên `a664d144`: **34/35 xanh**; Owner Governance Lint đỏ chỉ vì hồ sơ còn gắn digest cũ — gói trình
  lại này xử lý.

**4. Công khai**
- **Owner amendment 2** (Codex P2, deadlock đảo chặng / đối soát): sửa ở `a664d144`, lần duyệt Gate 3 trước bị thay thế.
- **Owner amendment 1:** sửa test S4a `test_route_leg_entries_are_rejected` thành bút toán chặng đối soát được (Owner
  chọn "Sửa test trong PR này (Recommended)"); test đếm quyền của kế toán 10 → 11 theo quyền mới đã duyệt.
- Trong lúc làm, chạy MySQL thật phát hiện vài lệnh đọc trong giao dịch đọc ảnh chụp cũ (có thể không thấy chặng
  vừa ghi); đã chuyển sang đọc có khoá **sau** khoá tuyến để giữ thứ tự khoá v17.
- Cảnh báo khai trùng tiền nhận giờ cũng so với tuyến đang trên đường (tránh khai hai lần cùng một khoản).
- CI ở `81697bee` đỏ một mục Code Quality (thứ tự `use` trong test tranh chấp), sửa ở `a97e7ec8`.
- Lệnh ẩn `treasury:concurrency-test-route-leg` chỉ dùng cho test.
- Register cập nhật ở đợt đối soát sau.

**5. Ngoài phạm vi** — Truy vết thanh toán hợp đồng (S4b-2), tạm ứng (S5), báo cáo (S6), nhập sao kê, khoá kỳ, deploy.

**6. Rủi ro còn lại** — Trung bình: thêm luồng ghi tiền mới có khoá đồng thời; đã có test MySQL thật và mutation.
Quyền mới cần chạy lại seeder phân quyền khi triển khai để chủ DN/kế toán có `treasury.manage_routes`.

**7. Hoàn tác** — Revert squash commit (không có migration).

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-094 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay release; không làm S4b-2, S5, S6.
