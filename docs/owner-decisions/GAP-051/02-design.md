---
work_id: GAP-051
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-09-09-gap-051-gate2-sanctum-bearer-fidelity-contract-design.md
  plan: null
  branch: design/GAP-051-gate2-sanctum-bearer-fidelity-contract
  pr: https://github.com/kha997/zenamanagephp/pull/307
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-09T11:22:41Z"
  owner_response_reference: "GAP-051 Gate 2 APPROVED — reviewed exact PR #307 head 551479a51aa0b6fdd575cd1a9706db00d04a3be3, canonical main at review time e2013751f41a3bb367168705e773f01bb434a641. Owner Governance Lint and Routes Guardrails both passed on this exact head in the prior review round. Owner's binding interpretation for the (not-yet-started) future Gate 3, recorded verbatim: final architecture is exactly TWO layers: (A) real-Bearer helper using createToken() + AuthManager::forgetGuards(); (B) universal pre-dispatch runtime contamination guard in Tests\\TestCase::call(). Runtime guard set = unique(config('sanctum.guard', []) + ['sanctum']). Detect pre-existing state with public hasUser(), NOT check(). Must cover both: plain actingAs() + raw Bearer request; Sanctum::actingAs() + raw Bearer request. Clean helper path must prove a genuine PersonalAccessToken/currentAccessToken() context. Static source-text tripwire is evaluated/rejected history only — NOT a Gate-3 implementation requirement. Behavioral 401 production-topology contract is authoritative; the static middleware-absence check is defense-in-depth only. No production auth/config/guard/middleware semantic changes. JWT-naming debt remains excluded from Gate-3 scope. Gate 3 implementation has NOT begun in this session and remains future work, to start in a NEW session after this approval-record merge."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-09T04:14:00Z"
  updated_at: "2026-09-09T11:22:41Z"
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

**Recommended architecture (corrected this revision — finalized at
exactly two layers):** a dedicated real-Bearer transport testing contract
(`actingAsSanctumBearerToken()` helper trait) as the primary, structural
mechanism — it purges cached guard state via Laravel's own public
`AuthManager::forgetGuards()` immediately before issuing a request with a
real Sanctum token, making it structurally impossible for a prior
`actingAs()`/`Sanctum::actingAs()` call in the same test method to leak
into that request — combined with a universal, test-only runtime guard
registered in `Tests\TestCase`'s existing request-dispatch override (it
already overrides `call()` for CSRF-token injection, so this extends an
established pattern rather than introducing a new one) that fails
loudly, at the moment any request carrying a Bearer `Authorization`
header is dispatched, if **any** guard in
`unique(config('sanctum.guard', []) + ['sanctum'])` already has a cached
user, checked via each guard's public `hasUser()` (not `check()`, which
can itself trigger new guard resolution as a side effect — the check
must observe pre-existing state without causing any).

A prior version of this correction also proposed a third mechanism — a
narrow PHPUnit static-source-text tripwire scanning for the specific
two-call anti-pattern (helper name co-occurring with `->actingAs(` in one
method) — as an additional secondary layer. **This final correction
removes that tripwire from Gate-3's required scope.** Once the helper
(Layer A) and a correctly-scoped runtime guard (Layer B, fixed by this
correction to check every guard actually configured for Sanctum plus
`sanctum` itself, not a hard-coded `web`-only list that excluded
`sanctum`) are in place, the source-text scan adds no unique safety
property — the runtime check already structurally catches both
contamination vectors (plain `actingAs()` on `web`, and
`Sanctum::actingAs()` on `sanctum` itself, which this correction newly
identified as a second hazard vector the earlier `web`-only check would
have missed) at the moment of actual request dispatch, regardless of
source-code shape. The static-tripwire approach (and Option 1, the
custom-PHPStan-rule idea it built on) remain documented in the spec as
evaluated and rejected — kept for history, not implemented, not part of
Gate-3 acceptance criteria. A documentation-only baseline (Option 4) was
separately evaluated and rejected for providing no regression signal —
exactly the condition that let this hazard go undetected until an
unrelated investigation (GAP-050) stumbled on it.

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

**Future-topology safeguard (softened previously, target class list
corrected this revision):** the original design asserted the exact full
`App\Http\Kernel::$middlewareGroups['api']` array composition, which
would fail loudly for any unrelated, harmless addition to that group.
This is replaced with a risk-focused invariant: an absence assertion
that no middleware which actually *enables* session/stateful
authentication — specifically `Illuminate\Session\Middleware\StartSession`
and Sanctum's own `EnsureFrontendRequestsAreStateful`, or a documented
equivalent that materially establishes session-auth state — is present
in the `api` group. **This correction removes `EncryptCookies` as a
target**: an earlier draft named it as (part of) what the absence
assertion checks for, but `EncryptCookies` only decrypts/encrypts
whatever cookies happen to be present — it does not itself start a
session or make `Auth::guard('web')` resolvable, so it is a
weakly-correlated proxy for the actual risk (the same over-brittle-proxy
problem the original exact-array-pin design had). The static absence
assertion fires only on the condition that actually reopens GAP-051's
production-exposure question; the **authoritative** protection remains
the behavioral contract test, kept as-is, proving directly that a
`web`-guard-authenticated user with no Bearer token still receives `401`
from a representative `auth:sanctum` route under the real, current `api`
middleware stack. Together these still turn the Gate-1 packet's "bound to
current topology, not eternal" caveat into an enforced tripwire, without
pinning an implementation detail unrelated future changes could trip for
no reason, and without relying on a weak proxy class for the static
signal.

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
   sửa lại ở bản này): kiến trúc cuối cùng chỉ còn **đúng 2 lớp** — helper
   (§2) + một kiểm tra runtime phổ quát trong `Tests\TestCase` (§4, mở
   rộng điểm ghi đè `call()` đã có sẵn cho CSRF). Bản sửa lần này (final
   correction) sửa 2 lỗi trong chính kiểm tra runtime đó: (a) danh sách
   guard cần kiểm tra không còn hard-code `web` mà lấy động từ
   `unique(config('sanctum.guard', []) + ['sanctum'])` — vì
   `Sanctum::actingAs()` set user thẳng lên guard `sanctum`, một bản nháp
   trước loại trừ `sanctum` khỏi danh sách nên bỏ sót đúng vector này; (b)
   dùng API `hasUser()` công khai thay vì `check()`, vì `check()` gọi nội
   bộ `user()` có thể tự kích hoạt resolution — kiểm tra GAP-051 cần quan
   sát state đã tồn tại từ trước mà không tự gây ra resolution mới. Lớp
   tripwire tĩnh (quét văn bản nguồn) mà bản sửa trước đề xuất làm lớp
   thứ ba **bị loại khỏi phạm vi Gate 3 bắt buộc** ở bản sửa này: một khi
   helper + kiểm tra runtime (đã sửa đúng cả 2 vector) đã có, tripwire
   tĩnh không thêm giá trị an toàn nào — kiểm tra runtime đã bắt cả 2
   hazard pattern ngay tại thời điểm dispatch request thật, bất kể hình
   dạng mã nguồn — trong khi vẫn mang rủi ro false-positive/chi phí bảo
   trì của một cơ chế heuristic thuần tuý. Phân tích AST/tripwire tĩnh vẫn
   được giữ lại trong tài liệu spec (§4.1) như lịch sử đã đánh giá và bị
   từ chối, không triển khai.
4. Quyết định vòng đời evidence-harness rõ ràng, không mơ hồ (§5, sửa lại
   ở bản này): tách thành 2 file thay vì gộp chung một "regression test"
   — Scenario 1 trở thành file canary đặc trưng hành vi framework (phải
   luôn xanh vì Constraint D cấm "sửa" ở tầng config/guard), các Scenario
   còn lại + test mới trở thành file regression-contract thật sự (phải đỏ
   nếu tái nhiễm). Không scenario nào bị xoá.
5. Rào chắn topology tương lai cụ thể, có thể chạy được (§6, sửa lại ở
   bản này): thay vì pin đúng mảng `api` middleware group hiện tại (dễ vỡ
   với thay đổi không liên quan), chuyển sang assert sự VẮNG MẶT của
   middleware thực sự kích hoạt session/stateful auth — cụ thể
   `Illuminate\Session\Middleware\StartSession` và
   `EnsureFrontendRequestsAreStateful` của Sanctum (không còn dùng
   `EncryptCookies` làm mục tiêu kiểm tra như bản nháp trước — Owner chỉ
   ra `EncryptCookies` là proxy yếu, không tự nó bật session auth), cộng
   thêm một test hành vi thật (401 cho user web-guard không có Bearer
   token, qua đúng stack `api` middleware hiện tại) — test hành vi này là
   bằng chứng có thẩm quyền (authoritative), test assert vắng mặt chỉ là
   lớp cảnh báo sớm bổ sung.
6. Nợ đặt tên "JWT" bị loại khỏi phạm vi có chủ đích, ghi nhận riêng (§8) —
   không đổi ở bản sửa này.

## Đề xuất Gate 3 (chưa được uỷ quyền triển khai)

Triển khai đúng theo các hạng mục Gate-3 scope/files ở §12 tài liệu spec
(bản sửa cuối): helper trait mới; tách evidence-harness thành file canary
(Scenario 1) + file regression-contract (Scenario 2/3/4-viết-lại/5); kiểm
tra runtime guard-state (§4) thêm vào `Tests\TestCase::call()` — danh
sách guard lấy động từ `unique(config('sanctum.guard', []) + ['sanctum'])`,
dùng `hasUser()`, chứng minh bắt được cả 2 vector (`actingAs()` và
`Sanctum::actingAs()`); rào chắn topology gồm assert vắng mặt
`StartSession`/`EnsureFrontendRequestsAreStateful` + test hành vi 401
(§6). **Không** bao gồm tripwire tĩnh quét văn bản nguồn (§4.1 — đã đánh
giá và bị từ chối, không nằm trong phạm vi Gate 3 bắt buộc). Không đụng
bất kỳ file `config/`, `app/Http/Kernel.php`, `app/Http/Middleware/`,
`app/Providers/RouteServiceProvider.php`, hay bất kỳ class guard/auth nào.

## Loại trừ rõ ràng

- Không đề xuất thay đổi bất kỳ config/guard/middleware/auth semantics
  production nào (xem §11 tài liệu spec — production-semantics
  non-impact statement).
- Không mở rộng sang dọn nợ đặt tên "JWT" (§8).
- Không tái mở phạm vi GAP-050.
- Không viết code triển khai thật ở Gate này — chỉ snippet minh hoạ cho
  mục đích thiết kế.

## Ghi chú quản trị: OPERATIONAL_GAP_REGISTER.md tạm thời chưa cập nhật

`OPERATIONAL_GAP_REGISTER.md` canonical **cố ý** giữ nguyên trạng thái cũ
(hiển thị Gate 1 với từ ngữ cũ hơn) trên nhánh này — đây là điều kiện đã
biết trước, không phải sai sót. Lý do: `OPERATIONAL_GAP_REGISTER.md` nằm
ngoài allowlist của OWN-2026-005 (`owner_governance_lint.php`'s
design-only exemption chỉ cho phép `docs/owner-decisions/**`,
`docs/superpowers/specs/**`, `docs/superpowers/plans/**`); nếu PR này bao
gồm cả thay đổi file đó, bộ thay đổi không còn "design-only" thuần tuý và
`--enforce-gate-ordering` sẽ fail dù thiết kế đúng là chưa được duyệt.
File register sẽ được đồng bộ lại (cập nhật đúng trạng thái Gate 1
merged / Gate 2) trong một commit riêng, SAU KHI Gate 2 này được Owner
duyệt và merge — lúc đó PR mang thay đổi register không còn cần đến
design-only exemption nữa. Không có thay đổi công cụ/lint nào
(`owner_governance_lint.php`'s allowlist) nằm trong phạm vi GAP-051 —
đó là nợ kỹ thuật riêng, cần Work ID quản trị riêng, không gộp vào đây.

## Quyết định Gate 2 cần Owner

`decision_requested: approve_or_changes_or_decline` — đề nghị Owner xác
nhận: (a) kiến trúc cuối cùng đúng 2 lớp (helper contract + kiểm tra
runtime guard-state phổ quát trong `Tests\TestCase`, không phải PHPStan
rule mới, không còn tripwire tĩnh) có đáp ứng đúng 7 ràng buộc A-G Owner
đã đặt ra, và cụ thể là đồng ý loại tripwire tĩnh khỏi phạm vi Gate 3 bắt
buộc (chỉ giữ làm lịch sử đã đánh giá/từ chối trong spec §4.1), đồng thời
đồng ý kiểm tra runtime đã sửa để bắt cả 2 vector nhiễm guard
(`actingAs()` trên `web` và `Sanctum::actingAs()` trên `sanctum`) dùng
`hasUser()`; (b) quyết định vòng đời evidence-harness đã sửa (tách thành
file canary + file regression-contract, không xoá scenario nào) có được
chấp nhận; (c) rào chắn topology tương lai ở §6 (assert vắng mặt
`StartSession`/`EnsureFrontendRequestsAreStateful` — không còn
`EncryptCookies` — + test hành vi 401 làm bằng chứng có thẩm quyền, thay
vì pin nguyên mảng `api` middleware group) có đủ cụ thể để coi là
"concrete tripwire" theo yêu cầu Constraint F; (d) có đồng ý loại trừ nợ
JWT-naming khỏi phạm vi Gate 3 này; (e) có chấp nhận điều kiện tạm thời
`OPERATIONAL_GAP_REGISTER.md` chưa đồng bộ (ghi chú ở mục trên) như một
hệ quả có chủ đích của quy tắc gate-ordering hiện tại, sẽ được đồng bộ
lại sau khi Gate 2 được duyệt.

Không có thay đổi code nào được thực hiện ở Gate 2 này ngoài tài liệu
thiết kế. Không có thay đổi hành vi tenant/RBAC/product/auth nào. Không mở
PR triển khai. Dừng tại Gate 2 chờ Owner xem xét.

## Owner Gate 2 Approval (permanent record, never erased)

**Owner Gate 2 decision: APPROVED.** This approval is bound to the exact
PR #307 head `551479a51aa0b6fdd575cd1a9706db00d04a3be3` and canonical
`main` at review time `e2013751f41a3bb367168705e773f01bb434a641`. Owner
Governance Lint and Routes Guardrails both passed on this exact head in
the prior review round.

All prior content above (original design, first correction, and the
final/second correction) is preserved verbatim and unmodified by this
approval record. Nothing above is rewritten or deleted.

The Owner's binding interpretation for the future Gate 3 — recorded here
verbatim, per the Owner's explicit direction — is:

- Final architecture is exactly TWO layers: (A) real-Bearer helper using
  `createToken()` + `AuthManager::forgetGuards()`; (B) universal
  pre-dispatch runtime contamination guard in `Tests\TestCase::call()`.
- Runtime guard set = `unique(config('sanctum.guard', []) + ['sanctum'])`.
- Detect pre-existing state with public `hasUser()`, NOT `check()`.
- Must cover both: plain `actingAs()` + raw Bearer request;
  `Sanctum::actingAs()` + raw Bearer request.
- Clean helper path must prove a genuine `PersonalAccessToken`/
  `currentAccessToken()` context.
- Static source-text tripwire is evaluated/rejected history only — NOT a
  Gate-3 implementation requirement.
- Behavioral 401 production-topology contract is authoritative; the
  static middleware-absence check is defense-in-depth only.
- No production auth/config/guard/middleware semantic changes.
- JWT-naming debt remains excluded from Gate-3 scope.

**Gate 3 implementation has NOT begun in this session and remains future
work.** This approval authorizes Gate 3 to start, but Gate 3 execution
(new trait, new test files, app code) must occur in a separate,
subsequent session — this session's scope ends at recording this
approval and verifying the resulting merge. JWT-naming debt remains
excluded from Gate-3 scope, confirmed again here.
