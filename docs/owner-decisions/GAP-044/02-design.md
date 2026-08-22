---
work_id: GAP-044
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/superpowers/specs/2026-08-22-gap-044-testcase-transaction-and-permission-lookup-design.md
  plan: null
  branch: docs/GAP-044-gate2-design
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-22T00:00:00+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-22T00:00:00+07:00"
  updated_at: "2026-08-22T00:00:00+07:00"
generated_by: agent
---

## Gate 2 — awaiting Owner decision

Gate 1 (PR #283, head `8ee9ec256f86aa291335768bd74abe0e1703f072`) is
Owner-approved (`docs/owner-decisions/GAP-044/01-request.md`). This PR
carries those approved Gate-1 artifacts forward byte-identically (first
commit) onto a fresh branch cut from canonical main (`4a89693b`), then adds
this Gate-2 design (second commit), per the GAP-039/GAP-040/GAP-043
precedent.

## Owner Summary

GAP-044's Gate 1 established a **compound test-infrastructure defect**:

- **(A) Transaction integrity:** `tests/TestCase.php`'s `ensureInteractionLogsTable()`/`ensureProjectPhasesTable()`/`ensureProjectTasksTable()` implicit-commit `RefreshDatabase`'s real-MySQL transaction, the same defect class GAP-040 fixed for a sibling method in the same file but never applied to these three.
- **(B) Permission fixture identity:** `TenantUserFactoryTrait::ensurePermissionAttached()` looks up `Permission` rows by `name`, but the database's actual unique constraint (and `RoleSeeder`'s own seeded data, which sets `code` without `name`, per `permissions.name` being nullable) is on `code` — so the lookup misses a pre-existing seeded row and a duplicate-`code` insert is attempted, throwing a genuine `UniqueConstraintViolationException` that (A)'s transaction defect then masks as `SAVEPOINT trans2 does not exist`.

**Recommended design: Option A — fix both surfaces.**

- Surface 1: extend GAP-040's already-Gate-3-proven isolated-connection pattern (`zena_ddl_bootstrap`) to the three remaining sibling methods in `tests/TestCase.php`.
- Surface 2: change `TenantUserFactoryTrait::ensurePermissionAttached()`'s `Permission::firstOrCreate()` lookup key from `name` to `code` — aligning it to the pattern `tests/Support/SSOT/FixtureFactory.php`'s own `createTenantUserWithRbac()` already uses correctly in the same repository.

**Option B (Surface 1 only) is demonstrated, per the required comparison,
to be incomplete:** with only the transaction defect fixed, the same
`UniqueConstraintViolationException` still occurs (the seeded-data
mismatch is untouched), and `createOrFirst()`'s own built-in recovery lookup
(also keyed by `name`) still misses — so the test still fails, merely with
MySQL error 1062 instead of 1305 on the same call site. **Option B does not
achieve GAP-044 acceptance.**

**Option C (alternative lifecycle architecture) carries materially higher
scope/regression risk for no demonstrated benefit over Option A** — full
comparison in the engineering spec §3.

**`database/seeders/RoleSeeder.php` is not modified.** The `name=NULL`
seeded-row condition it produces (matching the repository's pre-existing
AUD-28 finding) is documented and characterized, and Option A's Surface-2
fix makes the test fixture correctly tolerate it — RoleSeeder itself is
explicitly out of GAP-044's scope per Owner direction, unless separately
authorized.

Full option comparison, exact proposed diffs, RoleSeeder/AUD-28 treatment,
GAP-040 governance strategy, and the discriminating (false-green-resistant)
acceptance contract are in the engineering spec:
`docs/superpowers/specs/2026-08-22-gap-044-testcase-transaction-and-permission-lookup-design.md`.

## Vấn đề vận hành

Xem chi tiết đầy đủ tại Gate 1 (`docs/owner-decisions/GAP-044/01-request.md`,
`docs/audits/2026-08-22-gap-044-savepoint-trans2-root-cause-evidence.md`) và
engineering spec Gate 2 nêu trên. Tóm tắt: lỗi kép — (A) 3 helper DDL không
được cô lập connection làm vô hiệu hoá transaction thật trên MySQL; (B)
helper fixture tra cứu permission sai cột (dùng `name` thay vì `code`, cột
có unique constraint thật), va chạm với dữ liệu seed sẵn có (`RoleSeeder`
tạo `code='project.read'` nhưng không set `name`, do `permissions.name`
nullable) — gây `UniqueConstraintViolationException` thật, sau đó bị (A)
che khuất thành `SAVEPOINT trans2 does not exist`.

## Người dùng bị ảnh hưởng

Không đổi so với Gate 1 — đội kỹ thuật dựa vào `RefreshDatabase` isolation
trên MySQL thật cho toàn bộ 5 bề mặt GAP-040 đã duyệt, cộng thêm bất kỳ
test nào dùng `TenantUserFactoryTrait`/`FixtureFactory` để tạo permission
qua tên. GAP-041 tiếp tục bị chặn cho tới khi GAP-044 (và GAP-045, riêng
biệt) được xử lý.

## Bằng chứng

Kế thừa toàn bộ bằng chứng Gate 1 (LIVE MySQL, probe log, exact-match
harness xác định Throwable gốc) — không thu thập lại. Bổ sung ở Gate 2 này:
xác minh tĩnh trực tiếp qua migration/seeder (`2025_09_14_140000_create_zena_rbac_fixed.php`,
`2026_01_30_000001_add_name_to_permissions_table.php`, `RoleSeeder.php`,
`PermissionSeeder.php`, `DatabaseSeeder.php`) xác nhận đúng như Owner đã
reconciled ở Gate 1 approval: `RoleSeeder` chạy trước `PermissionSeeder`,
tạo `code='project.read'` không kèm `name`, `permissions.name` nullable
(thêm bởi migration riêng, không có trong bảng gốc); và xác nhận
`tests/Support/SSOT/FixtureFactory.php` đã sẵn có pattern tra cứu đúng
(`code`) làm tiền lệ trực tiếp trong cùng repo cho phương án sửa được đề
xuất.

## Tác động nếu không xử lý

Không đổi so với Gate 1 (xem `01-request.md`).

## Phạm vi đề xuất

Gate 2 đề xuất Owner phê duyệt Option A (sửa cả 2 bề mặt) làm hướng thiết
kế bắt buộc cho implementation, với hợp đồng nghiệm thu discriminating (7
mục, chống lặp lại lỗi false-green của GAP-040) nêu tại engineering spec
§5. Gate 2 KHÔNG chọn giữa 2 biến thể kỹ thuật A1/A2 của Surface 1 (đó là
quyết định lúc implementation). Gate 2 KHÔNG cấp phép implementation, test
code, hay thay đổi bất kỳ file production/workflow/migration/seeder nào.

## Loại trừ rõ ràng

Không sửa `database/seeders/RoleSeeder.php` hay bất kỳ seeder nào khác.
Không sửa bất kỳ artifact GAP-040/041/042/043/045 nào. Không điều tra liệu
có code path production nào khác cũng tra cứu `Permission` theo `name` hay
không (gắn cờ như câu hỏi mở, không tìm kiếm ở Gate 2 này). Không đổi
schema/migration/RBAC-authorization/tenant semantics production dưới bất
kỳ phương án nào. Không có implementation, không có test code, không có
Gate 3 nào được cấp phép bởi tài liệu này — chỉ thiết kế.

## Governance classification

Vẫn là vấn đề test-infrastructure thuần tuý — cả 2 bề mặt sửa đổi đề xuất
(`tests/TestCase.php`, `tests/Traits/TenantUserFactoryTrait.php`) đều là
file test-only. **Design Dependency Preflight KHÔNG được kích hoạt.** Nếu
implementation sau này phát hiện cần đụng đến schema/mã/authorization
production, phải DỪNG và chạy Design Dependency Preflight tương ứng trước
khi tiếp tục.

## GAP-040 governance

Không sửa bất kỳ artifact Gate nào của GAP-040. Nếu GAP-044 release thành
công với bằng chứng thiết kế ở §5 của engineering spec, kỳ vọng sẽ khôi
phục thuộc tính transaction-isolation end-to-end rộng hơn mà GAP-040 Gate 3
trước đây đã tuyên bố (nhưng chưa hoàn chỉnh, theo phát hiện §H1 của Gate 1
này). Việc đối chiếu hồi tố hồ sơ kỹ thuật Gate-3 lịch sử của chính GAP-040
là quyết định governance riêng của Owner sau khi GAP-044 có bằng chứng
release trung thực — không thuộc phạm vi GAP-044.

## Đề xuất

Đội kỹ thuật đề xuất Owner phê duyệt Option A làm hướng thiết kế Gate 2 cho
GAP-044, với hợp đồng nghiệm thu discriminating đầy đủ tại engineering spec
§5, để tiến hành implementation (Gate 3 sau đó, riêng biệt, cần bằng chứng
kỹ thuật đầy đủ trước khi xin quyết định release).

## What the owner is NOT being asked to decide

Owner is not being asked to approve any code change, test code, or specific
sub-shape of Surface 1's isolated-connection mechanism (A1 vs A2) — those
are implementation-time decisions within the approved Option A boundary.
Owner is not being asked to reopen or modify GAP-040's already-released
decision, or to decide on GAP-041/GAP-042/GAP-043/GAP-045 in this packet —
all remain separate, with their own governance lifecycles. Owner is not
being asked to authorize any change to `database/seeders/RoleSeeder.php`.
No production code, no Gate 3, no merge is authorized by this document.
