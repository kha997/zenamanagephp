---
work_id: GAP-094
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
  spec: docs/audits/2026-10-26-gap-094-treasury-s4b-routes-readiness.md
  plan: docs/superpowers/plans/2026-10-27-gap-094-treasury-s4b-routes.md
  branch: docs/GAP-094-treasury-s4b-routes
  pr: https://github.com/kha997/zenamanagephp/pull/373
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
  created_at: "2026-10-27T07:27:03+07:00"
  updated_at: "2026-10-27T07:27:03+07:00"
generated_by: agent
residual_risk_rating: medium
mandatory_technical_gate_summary: "GAP-094 (Treasury S4b-1) at subject a97e7ec81be807dabc229018233327d2191f1913 (Gate 2 Option A plus Owner amendment 1). New TreasuryRouteService: createRoute (funding / owner_contribution / internal_transfer; document via_route + approved, amount frozen; route planned with total = amount, Case B link; S2 validation incl. owner-party rule and duplicate warning, which now also sees routes on their way); recordLeg (one step, settled; wallet-to-wallet debit/credit pair or a lone credit from outside on funding routes only; from-wallet balance guard; money from outside <= route amount; transfer net out of the source <= amount; no leg on completed/cancelled routes; occurred_at not in the future; permission treasury.manage_routes, or treasury.create_transfer when the user holds the from- or to-wallet); completion per v17 4.3 after every leg and leg reversal (destination +amount, transfer source -amount, other wallets 0) posts the document (posted_unreconciled, posted_at); reverseLeg (reason; leg reversed + one compensating entry per original entry; to-wallet must still hold the amount; completed route back to partial; reconciled document regresses); cancelRoute (reason; only while the document is approved and no leg is in force; route cancelled, document rejected). Locks: leg 0 -> shared 4 -> 5 -> 6, settled legs read with a locking read once the route is held; reversal 0 -> 4 -> 5 -> 6; cancel 5 -> 6. TreasuryPostingService reverses a via_route original only when its route is completed (read under the route lock, 5 -> 6) and posts the swapped shape directly. TreasuryReconciliationService accepts leg entries, maps them to the route's document, and reconciles a via_route document when its route is completed and every leg entry has an active apply; leg entries are re-read with locking reads only after the route lock. New permission treasury.manage_routes (owner, accountant); gates treasury.manage-routes, treasury.record-route-leg. API TreasuryRouteController (6 endpoints) and operator pages (route section + create form on the project page, route page, register badge). Owner amendment 1: the S4a test that rejected leg entries now asserts they reconcile with the route's document. Tests: TreasuryRouteServiceTest 16, TreasuryRouteApiTest 3, TreasuryRouteWebTest 3, two real-MySQL races (transit wallet drained twice; money from outside over the route amount) whose lock-removal mutation goes red on both. Local: tests/Feature/Treasury 137 OK; tests/Feature/Api 806 OK; tests/Feature/Web 50 OK; Zena 290 OK; Architecture, Deployment OK; Unit OK except the known root-only GrandfatherLoaderTest; route guard and RouteHygieneTest OK; PHPStan no errors; php-cs-fixer 0 files; SSOT lint, governance and docs lints pass. Exact-head CI at 81697bee: Code Quality Analysis red for import order in the concurrency test, fixed in a97e7ec8; exact-head CI at a97e7ec8: 35/35 green."
technical_evidence:
  base_sha: "740b84500a805805bea4f9a81758ea6ea2691a30"
  subject_sha: "a97e7ec81be807dabc229018233327d2191f1913"
  implementation_tree_digest: "f117133143eae0de9d309f211be49df9fa00867828dba51de3c6ce611837c98a"
  verified_pr_head_sha: "a97e7ec81be807dabc229018233327d2191f1913"
  verified_at: "2026-10-27T07:27:03+07:00"
owner_decision_binding:
  implementation_tree_digest: "f117133143eae0de9d309f211be49df9fa00867828dba51de3c6ce611837c98a"
  decision_recorded_at: null
---

# GAP-094 — Gate 3 release decision (Treasury S4b-1 via-route payments)

## OWNER GATE 3: AWAITING OWNER

## Gói quyết định phát hành

**1. Vấn đề là gì?** Không ghi được tiền đi qua trung gian (TK công ty, người mang tiền); số dư ví lệch thực tế
trong lúc tiền đang đi (Gate 1, phần S4b-1).

**2. Sau thay đổi (Gate 2 Phương án A + Owner amendment 1)** — subject `a97e7ec8`:
- Chủ DN / kế toán **tạo tuyến** cho tiền nhận, góp vốn, chuyển nội bộ; chứng từ "Đang trên đường".
- **Ghi chặng** một bước; số dư ví đổi ngay. PM/kỹ sư chỉ ghi chặng ra hoặc vào ví mình giữ.
- Tuyến **tự hoàn tất** khi ví đích đủ tiền và ví trung gian về 0 → chứng từ ghi sổ, tiền nhận mới cộng vào tổng.
- **Đảo chặng** (lý do), **huỷ tuyến** (khi chưa có chặng hiệu lực), **đảo chứng từ** khi tuyến đã hoàn tất.
- **Đối soát** bút toán chặng theo ví; chứng từ "đã đối soát" khi tuyến hoàn tất và mọi bút toán chặng đã đối soát.
- Quyền mới `treasury.manage_routes` (chủ DN, kế toán). Không đổi cơ sở dữ liệu.
- API và trang web: mục "Tuyến tiền" ở trang ngân quỹ dự án, trang chi tiết tuyến, nhãn "Qua trung gian" ở sổ.

**3. Bằng chứng** — base `740b8450`; subject `a97e7ec81be807dabc229018233327d2191f1913`;
digest `f117133143eae0de9d309f211be49df9fa00867828dba51de3c6ce611837c98a`.
- Test mới: 16 test service, 3 API, 3 web; 2 test tranh chấp trên **MySQL thật** (hai người cùng rút một ví trung
  chuyển; hai chặng "từ bên ngoài" vượt số tiền tuyến) — gỡ khoá thì cả hai test đỏ.
- Trên máy: 137 test Treasury, 806 test API, 50 Web, 290 Zena, Architecture, Deployment, Unit qua (trừ test chỉ hỏng
  khi chạy bằng root); PHPStan, php-cs-fixer, SSOT lint, governance/docs lint sạch.
- CI chạy đúng trên `a97e7ec8`: **35/35 xanh**.

**4. Công khai**
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
