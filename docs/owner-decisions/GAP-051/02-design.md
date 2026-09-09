---
work_id: GAP-051
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_changes_or_decline"
references:
  spec: docs/superpowers/specs/2026-09-09-gap-051-gate2-sanctum-bearer-fidelity-contract-design.md
  plan: null
  branch: design/GAP-051-gate2-sanctum-bearer-fidelity-contract
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-09T04:14:00Z"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-09T04:14:00Z"
  updated_at: "2026-09-09T04:14:00Z"
generated_by: agent
---

## Owner Summary

Per Owner Gate-1 approval (`01-request.md`, head
`8b5174821df7ccdc53a202f54a4e80c1aa0ba6a5`, PR #306, merged as
`e2013751f41a3bb367168705e773f01bb434a641`), this Gate 2 is a
design/decision investigation only — **no implementation**. It compares
four regression-prevention approaches for the GAP-051 defect class (a test
claiming real Bearer-token/Sanctum transport actually passing via
`web`-guard/`actingAs()` session leakage) and recommends one, per the
Owner's binding constraints A-G recorded in `01-request.md`'s
`decision_provenance.owner_response_reference`.

**Recommended architecture:** a dedicated real-Bearer transport testing
contract (`actingAsSanctumBearerToken()` helper trait) as the primary,
structural mechanism — it purges cached guard state via Laravel's own
public `AuthManager::forgetGuards()` immediately before issuing a request
with a real Sanctum token, making it structurally impossible for a prior
`actingAs()` call in the same test method to leak into that request —
combined with a narrow, existing-pattern-consistent PHPUnit static
tripwire test (in the same family as this repo's existing
`RouteMiddlewareSecurityContractTest`/`ZenaRouteSurfaceInvariantTest`) that
catches the one specific anti-pattern the helper alone cannot structurally
prevent: someone hand-rolling the vulnerable pattern instead of adopting
the helper. A custom PHPStan/AST rule (Option 1 alone) and a test-only
runtime guard-resolution instrumentation mechanism (Option 3) were both
seriously evaluated and rejected as the primary mechanism — the former for
being purely heuristic with real false-negative exposure and zero existing
precedent in this repo's PHPStan setup, the latter for fragility/cost
disproportionate to this repo's actually-traced test population. A
documentation-only baseline (Option 4) was evaluated and rejected for
providing no regression signal — exactly the condition that let this
hazard go undetected until an unrelated investigation (GAP-050) stumbled
on it.

**Evidence-harness lifecycle decision:** `tests/Feature/Gap051SanctumWebGuardLeakEvidenceTest.php`
is converted into a **permanent regression test** (renamed, not removed,
no scenario deleted) — its Scenario 1 becomes a permanent canary proving
Sanctum's guard-check-order behavior hasn't silently changed upstream
(since Constraint D forbids ever "fixing" it at the config/guard level),
and its Scenario 4 is rewritten to exercise the new helper.

**Future-topology safeguard:** a new static-invariant test asserts the
exact current composition of `App\Http\Kernel::$middlewareGroups['api']`
and fails loudly, citing this GAP by name, if session/stateful middleware
is ever added to it — turning the Gate-1 packet's "bound to current
topology, not eternal" caveat into an enforced tripwire.

**JWT-naming debt:** excluded from this Gate-2's scope per Constraint G;
recorded as separately-trackable debt, not carried into Gate-3 acceptance
criteria.

Full alternatives comparison, false-positive/false-negative analysis,
RED/GREEN regression strategy, Gate-3 acceptance criteria, exact
scope/files, and the production-semantics non-impact statement:
`docs/superpowers/specs/2026-09-09-gap-051-gate2-sanctum-bearer-fidelity-contract-design.md`.

## Vấn đề vận hành

Gate 1 chứng minh cơ chế có thật (Sanctum mặc định kiểm tra guard `web`
trước Bearer token, `actingAs()` để lại state trên guard đó) nhưng chưa
chọn cơ chế phòng ngừa hồi quy. Gate 2 này so sánh 4 phương án theo đúng
ràng buộc Owner (không mặc định grep/lint, ưu tiên positive contract, tách
rõ 3 loại actingAs/Sanctum::actingAs/Bearer-thật, không đụng cấu hình
Sanctum production) và chọn một kiến trúc cụ thể.

## Bằng chứng / Phân tích

Xem đầy đủ trong tài liệu spec đã dẫn. Tóm tắt cốt lõi:

1. 4 phương án được so sánh trung thực (kể cả phương án "không làm gì"),
   không phương án nào bị loại vì lý do hời hợt — mỗi phương án có bảng
   pros/cons riêng (§2 tài liệu spec).
2. Phương án được chọn (`actingAsSanctumBearerToken()` + `forgetGuards()`)
   là một **positive contract**: nó không cần "đoán" test đang cố làm gì
   từ văn bản nguồn — nó đảm bảo cấu trúc rằng guard state sạch trước mỗi
   request Bearer thật, dùng đúng API công khai, ổn định của Laravel
   (`AuthManager::forgetGuards()`, xác nhận tồn tại trong
   `laravel/framework: v12.63.0` đang dùng ở repo này).
3. Phân tích false-positive/false-negative trung thực (§7 tài liệu spec):
   thừa nhận rõ một lỗ hổng còn sót (ai đó viết tay pattern nguy hiểm mà
   không dùng helper mới sẽ không bị bắt tự động) và giải thích tại sao đó
   là đánh đổi chấp nhận được so với chi phí/độ giòn của phương án bao phủ
   rộng hơn (Option 1/3).
4. Quyết định vòng đời evidence-harness rõ ràng, không mơ hồ (§5): chuyển
   thành regression test vĩnh viễn, không xoá.
5. Rào chắn topology tương lai cụ thể, có thể chạy được (§6): assert đúng
   thành phần `api` middleware group hiện tại, thất bại to nếu ai đó thêm
   session middleware, trỏ thẳng về GAP-051.
6. Nợ đặt tên "JWT" bị loại khỏi phạm vi có chủ đích, ghi nhận riêng (§8).

## Đề xuất Gate 3 (chưa được uỷ quyền triển khai)

Triển khai đúng theo 4 hạng mục Gate-3 scope/files ở §12 tài liệu spec:
helper trait mới, đổi tên + cập nhật evidence-harness thành regression test
vĩnh viễn, tripwire test cho anti-pattern cụ thể, tripwire test cho
topology `api` middleware group. Không đụng bất kỳ file `config/`,
`app/Http/Kernel.php`, `app/Http/Middleware/`,
`app/Providers/RouteServiceProvider.php`, hay bất kỳ class guard/auth nào.

## Loại trừ rõ ràng

- Không đề xuất thay đổi bất kỳ config/guard/middleware/auth semantics
  production nào (xem §11 tài liệu spec — production-semantics
  non-impact statement).
- Không mở rộng sang dọn nợ đặt tên "JWT" (§8).
- Không tái mở phạm vi GAP-050.
- Không viết code triển khai thật ở Gate này — chỉ snippet minh hoạ cho
  mục đích thiết kế.

## Quyết định Gate 2 cần Owner

`decision_requested: approve_or_changes_or_decline` — đề nghị Owner xác
nhận: (a) kiến trúc được chọn (helper contract + tripwire hẹp, không phải
PHPStan rule mới, không phải runtime guard-instrumentation) có đáp ứng
đúng 7 ràng buộc A-G Owner đã đặt ra; (b) quyết định vòng đời
evidence-harness (chuyển vĩnh viễn, không xoá) có được chấp nhận; (c) rào
chắn topology tương lai ở §6 có đủ cụ thể để coi là "concrete tripwire"
theo yêu cầu Constraint F; (d) có đồng ý loại trừ nợ JWT-naming khỏi phạm
vi Gate 3 này.

Không có thay đổi code nào được thực hiện ở Gate 2 này ngoài tài liệu
thiết kế. Không có thay đổi hành vi tenant/RBAC/product/auth nào. Không mở
PR triển khai. Dừng tại Gate 2 chờ Owner xem xét.
