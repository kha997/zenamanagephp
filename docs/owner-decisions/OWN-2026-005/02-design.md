---
work_id: OWN-2026-005
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: null
  plan: null
  branch: fix/OWN-2026-005-gate2-design-only-exemption
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-07T08:01:21+07:00"
  owner_response_reference: "ChatGPT project conversation — owner directive 2026-08-06/07 dictating the mandatory design principles for this correction verbatim; owner review round 2 (2026-08-07) confirmed the overall design is accepted ('Thiết kế tổng thể được chấp nhận') while requiring an implementation correction (full-pagination changed-files evidence, lossless JSON transport, spec-path workflow trigger) — Gate 2 approval for OWN-2026-005 is not in doubt, only the implementation detail was revised."
  reconciliation_required: true
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-07T08:01:21+07:00"
  updated_at: "2026-08-07T08:42:44+07:00"
generated_by: agent
---

## OWNER GATE 2: APPROVED

Owner đã tự chỉ đạo các nguyên tắc thiết kế bắt buộc (verbatim, trong chỉ thị 2026-08-06/07) — không phải đội kỹ thuật đề xuất rồi owner duyệt theo quy trình thông thường. Ghi nhận Gate 2 approved trực tiếp theo đúng các nguyên tắc đó.

## Trước / Sau

**Trước:** `--enforce-gate-ordering` coi `owner_gate_2_record` là bằng chứng Gate 2 đã duyệt — bất kỳ spec nào tham chiếu một gói Gate 2 chưa `approved` đều bị chặn vô điều kiện, kể cả khi spec đó chính là tài liệu đang chờ owner ra quyết định Gate 2 đó.

**Sau:** `--enforce-gate-ordering` chấp nhận nhận `owner_gate_2_record` là một THAM CHIẾU, không mặc nhiên là bằng chứng đã duyệt. Một submission được miễn trừ khỏi `gate-2-not-approved` CHỈ KHI: (a) gói Gate 2 đang đúng ở `awaiting_owner`, VÀ (b) toàn bộ file thay đổi trong PR đều là tài liệu quản trị/thiết kế (`docs/owner-decisions/`, `docs/superpowers/specs/`, `docs/superpowers/plans/`). Bất kỳ file nào khác trong diff (mã ứng dụng, script, workflow, test, schema) làm mất quyền miễn trừ ngay lập tức.

## Nguyên tắc thiết kế bắt buộc (do owner chỉ đạo, ghi lại nguyên văn ý)

1. `owner_gate_2_record` là tham chiếu, không mặc nhiên là bằng chứng đã duyệt.
2. Một spec được phép tham chiếu đến Gate 2 packet của chính work_id đang `awaiting_owner`, khi spec và packet đó là tài liệu đang trình owner xem xét.
3. Trạng thái `awaiting_owner` chỉ cho phép tạo và kiểm tra tài liệu thiết kế/quản trị — không cho phép bất kỳ thay đổi triển khai nào đi kèm.
4. Mọi thay đổi triển khai ứng dụng vẫn bị chặn cho đến khi `owner_decision.value` là `approved`.
5. Gói Gate 2 bị `rejected`/thiếu/sai `work_id`/sai đường dẫn/tham chiếu chéo sang work item khác vẫn phải bị từ chối vô điều kiện — không có ngoại lệ nào cho các trường hợp này.

## Cơ chế phân biệt design-only vs. implementation

Dựa trên phạm vi file thay đổi thực tế của PR. Hàm `owner_governance_changed_files_are_design_only()` chỉ coi một diff là "design-only" khi TOÀN BỘ file đều nằm trong `docs/owner-decisions/`, `docs/superpowers/specs/`, hoặc `docs/superpowers/plans/` — cố tình KHÔNG bao gồm `docs/owner-governance/**` (thay đổi schema/tooling là loại rủi ro khác, phải qua đúng work item riêng, như chính lần sửa này). Danh sách trống cũng KHÔNG được coi là design-only.

## Vòng review 2 của owner (2026-08-07) — sửa lỗ hổng bằng chứng changed-files

Owner chấp nhận thiết kế tổng thể nhưng yêu cầu sửa cơ chế lấy danh sách file thay đổi trước khi trình Gate 3:

- **Nguồn dữ liệu:** không dùng `gh pr view --json files` nữa (trường `files(first: 100)` GraphQL, giới hạn ngầm 100 file, có thể bỏ sót file thứ 101 trở đi và phân loại sai thành design-only). Thay bằng `scripts/ci/fetch-pr-changed-files.sh`: dùng REST API có phân trang (`gh api --paginate`), đối chiếu số file lấy được với tổng `changedFiles` (trường số nguyên riêng, không bị giới hạn 100), **fail closed** (thoát mã khác 0, không in JSON) nếu API lỗi, phân trang lỗi, JSON lỗi, danh sách rỗng, hoặc số lượng không khớp.
- **Định dạng truyền dữ liệu:** không dùng chuỗi phân cách dấu phẩy nữa (tên file hợp lệ có thể chứa dấu phẩy). Thay bằng file JSON (`--changed-files-json=<path>`), đọc bằng `json_decode(..., JSON_THROW_ON_ERROR)`, xác nhận là mảng chuỗi không rỗng, cũng fail closed nếu không hợp lệ.
- **Workflow trigger:** thêm `docs/superpowers/specs/**` vào cả `on.pull_request.paths` và `on.push.paths` — một thay đổi chỉ riêng spec phải tự kích hoạt Owner Governance Lint, không phụ thuộc giả định luôn có gói owner-decision đi kèm.
- **Test:** thay test tĩnh cũ (chỉ xác nhận gọi `gh pr view`) bằng bộ test xác nhận nguồn phân trang đầy đủ + fail-closed (`FetchPrChangedFilesTest.php`, dùng `gh` giả để test xác định không cần mạng thật), cộng thêm test end-to-end 101-file và tên file có dấu phẩy qua chính hàm enforcement (`GateOrderingDesignOnlyExemptionTest.php`).

Thiết kế tổng thể (5 nguyên tắc bắt buộc ở trên) không đổi — đây là sửa lỗ hổng trong cách LẤY bằng chứng, không phải đổi logic quyết định design-only vs. implementation.

## Được phép / Không được phép

**Được phép (design-only, khi awaiting_owner):** tạo/sửa gói Gate 1/2/3, tạo/sửa spec/plan tham chiếu gói đó.

**Không được phép (vẫn bị chặn cho đến khi approved):** bất kỳ thay đổi nào ngoài 3 thư mục quản trị/thiết kế nêu trên — mã ứng dụng, route, migration, script CI, workflow, test, schema quản trị.

## Trạng thái và bước tiếp theo

Gate 1 + Gate 2 đã duyệt cùng lúc (theo đúng chỉ thị owner). Tiếp theo: triển khai đúng theo 5 nguyên tắc trên, viết test hồi quy, mở PR riêng, KHÔNG merge — chờ owner Gate 3 xem xét PR thật trước khi merge.

## Ngoại lệ

Không đụng đến GAP-010b, GAP-034, hay bất kỳ work item nghiệp vụ nào khác. Không tạo hệ thống trạng thái mới.
