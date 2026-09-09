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
  pr: https://github.com/kha997/zenamanagephp/pull/307
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
  updated_at: "2026-09-09T04:28:06Z"
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

**Recommended architecture (corrected this revision — three layers, not
two):** a dedicated real-Bearer transport testing contract
(`actingAsSanctumBearerToken()` helper trait) as the primary, structural
mechanism — it purges cached guard state via Laravel's own public
`AuthManager::forgetGuards()` immediately before issuing a request with a
real Sanctum token, making it structurally impossible for a prior
`actingAs()` call in the same test method to leak into that request —
combined with a narrow, existing-pattern-consistent PHPUnit static
tripwire test (in the same family as this repo's existing
`RouteMiddlewareSecurityContractTest`/`ZenaRouteSurfaceInvariantTest`) that
catches the specific anti-pattern of hand-rolling the vulnerable pattern
*while also referencing* the new helper's name. The first version of this
packet stopped there and merely acknowledged, without seriously
evaluating a fix, that a test author who ignores the helper entirely
(raw `withHeaders(Authorization)` alongside `actingAs()`, no reference to
the helper anywhere) is invisible to both layers. This correction adds a
**third layer**: a runtime guard-state check added to `Tests\TestCase`'s
existing request-dispatch override (it already overrides `call()` for
CSRF-token injection, so this extends an established pattern rather than
introducing a new one) that fails loudly, at the moment any request
carrying a Bearer header is dispatched, if the `web` guard (or any other
non-`sanctum` guard in Sanctum's configured fallback chain) is already
authenticated from cached test state — this closes the exact
hand-rolled-hazard gap the original design left open, using only the
core, stable `Auth::guard()->check()` API. A custom PHPStan/AST rule
(Option 1 alone) and this same runtime-check idea considered as the
*sole* replacement mechanism (rather than a third layer) were both
seriously evaluated: the PHPStan rule was rejected as the primary
mechanism for being purely heuristic with real false-negative exposure
and zero existing precedent in this repo's PHPStan setup; the runtime
check was judged strong enough on its own to close the specific
false-negative it targets, but is added as a third layer rather than a
replacement, since the helper's structural prevention and the static
tripwire's early, source-visible signal remain independently valuable. A
documentation-only baseline (Option 4) was evaluated and rejected for
providing no regression signal — exactly the condition that let this
hazard go undetected until an unrelated investigation (GAP-050) stumbled
on it.

**Evidence-harness lifecycle decision (corrected this revision):**
`tests/Feature/Gap051SanctumWebGuardLeakEvidenceTest.php`'s five scenarios
are all kept, none removed, but the file is **split in two** rather than
renamed as one undifferentiated "permanent regression test" as the
original packet proposed — that framing blurred a real distinction.
Scenario 1 becomes its own **framework-characterization/canary test**:
it intentionally keeps proving the *hazardous* framework behavior (200
with no Bearer token, guard cached from `actingAs()`) still happens,
because Constraint D forbids ever "fixing" it at the config/guard level —
calling this a "regression test" risked a reviewer misreading permanent
green here as "GAP-051 fixed" when the opposite is true. The remaining
scenarios (2, 3, rewritten 4, 5) plus the new helper/tripwire/runtime-check
proofs move into a separate file that genuinely is a GAP-051 regression
contract — it must fail if contamination or a missing defense layer is
reintroduced. Scenario 4 is also strengthened: it now asserts genuine
Sanctum state (`currentAccessToken()` resolves to a real
`PersonalAccessToken` matching the issued token's id/abilities), not
merely a matching response user ID, since a matching user ID alone is
also what the hazardous web-guard fallback would produce.

**Future-topology safeguard (softened this revision):** the original
design asserted the exact full `App\Http\Kernel::$middlewareGroups['api']`
array composition, which would fail loudly for any unrelated, harmless
addition to that group. This is replaced with a risk-focused invariant:
an absence assertion (no session/stateful-authentication middleware —
`StartSession`, `EncryptCookies`, or similar — may be present in the
`api` group), which only fires on the condition that actually reopens
GAP-051's production-exposure question, plus a new behavioral contract
test proving directly that a `web`-guard-authenticated user with no
Bearer token still receives `401` from a representative `auth:sanctum`
route under the real, current `api` middleware stack. Together these
still turn the Gate-1 packet's "bound to current topology, not eternal"
caveat into an enforced tripwire, without pinning an implementation
detail unrelated future changes could trip for no reason.

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
3. Phân tích false-positive/false-negative trung thực (§7 tài liệu spec,
   sửa lại ở bản này): bản gốc thừa nhận một lỗ hổng còn sót (ai đó viết
   tay pattern nguy hiểm mà không dùng helper mới sẽ không bị bắt tự
   động) nhưng chưa đánh giá nghiêm túc một giải pháp tự động cụ thể. Bản
   sửa này bổ sung lớp thứ ba (§4.2): một kiểm tra runtime trong
   `Tests\TestCase` (mở rộng điểm ghi đè `call()` đã có sẵn cho CSRF) bắt
   đúng trường hợp viết tay đó, dùng API ổn định `Auth::guard()->check()`.
4. Quyết định vòng đời evidence-harness rõ ràng, không mơ hồ (§5, sửa lại
   ở bản này): tách thành 2 file thay vì gộp chung một "regression test"
   — Scenario 1 trở thành file canary đặc trưng hành vi framework (phải
   luôn xanh vì Constraint D cấm "sửa" ở tầng config/guard), các Scenario
   còn lại + test mới trở thành file regression-contract thật sự (phải đỏ
   nếu tái nhiễm). Không scenario nào bị xoá.
5. Rào chắn topology tương lai cụ thể, có thể chạy được (§6, sửa lại ở
   bản này): thay vì pin đúng mảng `api` middleware group hiện tại (dễ vỡ
   với thay đổi không liên quan), chuyển sang assert sự VẮNG MẶT của
   session/stateful middleware, cộng thêm một test hành vi thật (401 cho
   user web-guard không có Bearer token, qua đúng stack `api` middleware
   hiện tại).
6. Nợ đặt tên "JWT" bị loại khỏi phạm vi có chủ đích, ghi nhận riêng (§8) —
   không đổi ở bản sửa này.

## Đề xuất Gate 3 (chưa được uỷ quyền triển khai)

Triển khai đúng theo các hạng mục Gate-3 scope/files ở §12 tài liệu spec
(bản sửa): helper trait mới; tách evidence-harness thành file canary
(Scenario 1) + file regression-contract (Scenario 2/3/4-viết-lại/5); test
runtime guard-state check thêm vào `Tests\TestCase::call()`; tripwire test
cho anti-pattern cụ thể (§4.1); rào chắn topology gồm assert vắng mặt
session middleware + test hành vi 401 (§6). Không đụng bất kỳ file
`config/`, `app/Http/Kernel.php`, `app/Http/Middleware/`,
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
nhận: (a) kiến trúc 3 lớp được chọn (helper contract + tripwire tĩnh hẹp
+ runtime guard-state check mới trong `Tests\TestCase`, không phải PHPStan
rule mới) có đáp ứng đúng 7 ràng buộc A-G Owner đã đặt ra, và cụ thể là
đồng ý với việc thêm lớp runtime thứ ba làm defense-in-depth (không thay
thế 2 lớp kia); (b) quyết định vòng đời evidence-harness đã sửa (tách
thành file canary + file regression-contract, không xoá scenario nào) có
được chấp nhận; (c) rào chắn topology tương lai đã làm mềm ở §6 (assert
vắng mặt session middleware + test hành vi 401, thay vì pin nguyên mảng
`api` middleware group) có đủ cụ thể để coi là "concrete tripwire" theo
yêu cầu Constraint F; (d) có đồng ý loại trừ nợ JWT-naming khỏi phạm vi
Gate 3 này.

Không có thay đổi code nào được thực hiện ở Gate 2 này ngoài tài liệu
thiết kế. Không có thay đổi hành vi tenant/RBAC/product/auth nào. Không mở
PR triển khai. Dừng tại Gate 2 chờ Owner xem xét.
