---
work_id: OWN-2026-005
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
  spec: null
  plan: null
  branch: fix/OWN-2026-005-gate2-design-only-exemption
  pr: https://github.com/kha997/zenamanagephp/pull/247
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-07T12:08:00+07:00"
  owner_response_reference: "ChatGPT project conversation — explicit Owner Gate 3 release approval for OWN-2026-005 on 2026-08-07"
  reconciliation_required: true
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-07T09:15:08+07:00"
  updated_at: "2026-08-07T12:08:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "32/32 kiểm tra CI bắt buộc trên PR #247 đã đạt (7 workflow độc lập), gồm 147/147 test Owner Governance mới và có sẵn, không có review nào yêu cầu chỉnh sửa, không có review thread chưa xử lý."
technical_evidence:
  subject_sha: "de7060ba1396fe7fd922d859e2204d637da0af0a"
  implementation_tree_digest: "5db8f94b40fb40b7af45ea7ab3c20520e47a73b306eaf729a7ac95094f1cbd64"
  verified_pr_head_sha: "de7060ba1396fe7fd922d859e2204d637da0af0a"
  verified_at: "2026-08-07T09:15:08+07:00"
owner_decision_binding:
  implementation_tree_digest: "5db8f94b40fb40b7af45ea7ab3c20520e47a73b306eaf729a7ac95094f1cbd64"
  decision_recorded_at: "2026-08-07T12:08:00+07:00"
---

## Gói quyết định phát hành — OWN-2026-005: Sửa công cụ quản trị Gate 2 (design-only exemption)

**1. Vấn đề đã xảy ra là gì?**
Khi đội kỹ thuật chuẩn bị hồ sơ Gate 2 cho GAP-010b, công cụ kiểm tra quản trị (`owner_governance_lint.php --enforce-gate-ordering`) từ chối chính bản thiết kế đang được trình lên owner — chỉ vì gói Gate 2 đi kèm còn ở trạng thái "đang chờ owner" (`awaiting_owner`) chứ chưa "đã duyệt". Đây là một vòng lặp không thể nào thoát ra được: không thể có gói Gate 2 nào "đã duyệt" trước khi owner từng thấy bản thiết kế đi kèm nó, nhưng công cụ lại yêu cầu đúng điều đó trước khi chấp nhận commit bản thiết kế.

**2. Người dùng nào bị ảnh hưởng?**
Đội kỹ thuật (không thể trình bất kỳ bản thiết kế Gate 2 nào cho GAP-010b, GAP-034, hay bất kỳ work item quản trị nào trong tương lai); owner (không nhận được bản thiết kế đầy đủ để xem xét, vì CI luôn báo đỏ trước khi owner kịp quyết định).

**3. Bây giờ người dùng có thể làm gì? — cách sửa và phạm vi**

*Vòng 1:* Hàm kiểm tra được tách riêng (`owner_governance_enforce_gate_ordering()`) và nhận thêm thông tin phạm vi file thay đổi thực tế của PR. Chỉ miễn trừ lỗi `gate-2-not-approved` khi ĐỒNG THỜI: (a) gói Gate 2 được tham chiếu có `gate_status === awaiting_owner` (không áp dụng cho bất kỳ trạng thái nào khác — gói bị hoãn/từ chối/đang soạn vẫn bị chặn như cũ), VÀ (b) toàn bộ file thay đổi trong PR chỉ thuộc `docs/owner-decisions/**`, `docs/superpowers/specs/**`, hoặc `docs/superpowers/plans/**` — không có bất kỳ file mã ứng dụng, công cụ, workflow, test, hay schema quản trị nào trong diff. `docs/owner-governance/**` (thay đổi schema/công cụ) bị loại trừ có chủ đích khỏi phạm vi "design-only" — vì đó là loại rủi ro khác, phải qua đúng work item quản trị riêng (như chính lần sửa này).

*Lỗi phát hiện ở vòng review 2 (owner tự phát hiện khi xem xét PR #247):* Cơ chế lấy danh sách file thay đổi ban đầu dùng `gh pr view --json files`, vốn chỉ bảo đảm tối đa 100 file (giới hạn của truy vấn GraphQL `files(first: 100)`) — một PR có hơn 100 file thay đổi có thể khiến file triển khai thật (ví dụ mã ứng dụng) nằm ngoài danh sách được kiểm tra và bị phân loại nhầm thành "design-only".

*Cách sửa vòng 2:* Thay bằng REST API có phân trang đầy đủ (`gh api --paginate`, tự gộp mọi trang thành một danh sách), đối chiếu số file lấy được với tổng số file thay đổi thật của PR (trường `changedFiles`, một số nguyên riêng không bị giới hạn 100). Nếu API lỗi, phân trang lỗi, JSON hỏng, danh sách rỗng, hoặc số lượng không khớp — công cụ **dừng ngay, báo lỗi rõ ràng, không cho qua** (fail closed), không bao giờ tự coi dữ liệu thiếu là "design-only". Danh sách file được truyền qua file JSON (`--changed-files-json=`) thay vì chuỗi phân cách dấu phẩy, vì tên file hợp lệ có thể tự chứa dấu phẩy. Đồng thời thêm `docs/superpowers/specs/**` vào nơi kích hoạt workflow kiểm tra, để một thay đổi chỉ riêng bản thiết kế cũng tự động được kiểm tra, không phụ thuộc giả định luôn có gói owner-decision đi kèm trong cùng PR.

**4. Rủi ro nào đã được đóng lại?**
Rủi ro "một PR lớn (>100 file) có thể lách qua kiểm tra Gate 2 bằng cách giấu file triển khai sau file thứ 100" — đã đóng hoàn toàn bằng cơ chế phân trang + đối chiếu số lượng + fail closed. Rủi ro "tên file có dấu phẩy bị hiểu sai" — đã đóng bằng cách truyền JSON thay vì chuỗi phân cách dấu phẩy.

**5. Đã kiểm thử những gì?**
147/147 test trong bộ Owner Governance đạt (521 assertion), gồm: test mô phỏng PR có 150 file trải trên nhiều trang API (xác nhận toàn bộ 150 file được thu thập đầy đủ); test PR có đúng 101 file, 100 file đầu là tài liệu thiết kế và file thứ 101 là mã ứng dụng thật (xác nhận vẫn bị từ chối đúng); test các tình huống lỗi API, JSON hỏng, danh sách rỗng, số lượng lệch (xác nhận công cụ dừng lại đúng cách, không in ra dữ liệu dùng được); test tên file chứa dấu phẩy được truyền và phân loại chính xác theo cả hai chiều. Toàn bộ 7 workflow CI độc lập trên đúng commit này (`de7060ba1396fe7fd922d859e2204d637da0af0a`) đều đạt thành công thật trên GitHub Actions, không phải chạy thử cục bộ.

**6. Điều gì KHÔNG nằm trong phạm vi lần này?**
Không sửa mã nguồn ứng dụng. Không sửa route xuất dữ liệu (GAP-010b). Không thêm import `Illuminate\Http\Request` còn thiếu (GAP-010b). Không sửa câu truy vấn lọc theo tenant (GAP-034). Không đổi hành vi sản phẩm. Không đụng đến PR #243 (GAP-010b) hay PR #246 (GAP-034) — cả hai vẫn nguyên trạng Draft, chưa đổi head, trong suốt toàn bộ quá trình sửa công cụ này.

**7. Vì sao GAP-010b và GAP-034 vẫn để riêng?**
Đây thuần tuý là sửa công cụ quản trị (governance tooling) để công cụ có thể chấp nhận đúng loại hồ sơ hợp lệ mà trước đây bị chặn nhầm. Việc phê duyệt Gate 3 của OWN-2026-005 **không** đồng nghĩa với việc phê duyệt bất kỳ nội dung nghiệp vụ nào của GAP-010b hay GAP-034 — hai work item đó vẫn cần chu trình Gate 1/2/3 riêng của chính chúng.

**8. Rủi ro còn lại là gì?**
Đánh giá **thấp (low)**, vì: (a) thay đổi chỉ thuộc phạm vi công cụ quản trị nội bộ, không chạm mã ứng dụng hay dữ liệu người dùng; (b) cơ chế mặc định là "dừng lại khi không chắc chắn" (fail closed) ở mọi điểm có thể xảy ra lỗi bằng chứng; (c) có bộ test hồi quy cụ thể, bao phủ đúng các tình huống owner yêu cầu, chạy thật trên CI; (d) có thể hoàn tác đơn giản bằng cách revert PR #247, không có dữ liệu hay cấu trúc nào cần khôi phục thêm.

**9. Có thể hoàn tác không?**
Có. Không có migration cơ sở dữ liệu, không đổi cấu trúc dữ liệu nào. Hoàn tác bằng cách revert đúng PR #247 — công cụ quay lại hành vi cũ (nghiêm ngặt như trước khi sửa) ngay lập tức.

**10. Đề xuất của đội kỹ thuật:** Phát hành (Approve).

**Quyết định của chủ doanh nghiệp:** ☒ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide
Owner không được yêu cầu đọc mã nguồn, mở pull request kỹ thuật, xem nhật ký CI, hay review từng dòng thay đổi — mọi kết luận trên đã được đội kỹ thuật xác minh trực tiếp qua CI thật và bằng chứng đính kèm. Owner cũng không được yêu cầu quyết định bất kỳ điều gì về GAP-010b hay GAP-034 — hai work item đó hoàn toàn tách biệt và chưa được yêu cầu quyết định ở đây.

## OWNER GATE 3: APPROVED

Owner approves release of OWN-2026-005.

Approval is bound to implementation-tree digest:

5db8f94b40fb40b7af45ea7ab3c20520e47a73b306eaf729a7ac95094f1cbd64

This approval applies only to OWN-2026-005 and does not authorize GAP-010b, GAP-034, or any other work item.
