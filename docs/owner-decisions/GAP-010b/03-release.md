---
work_id: GAP-010b
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
  spec: docs/superpowers/specs/2026-08-06-gap-010b-legacy-csv-export-safety-design.md
  plan: docs/superpowers/plans/2026-08-08-gap-010b-legacy-csv-export-safety-implementation.md
  branch: impl/GAP-034-export-tenant-isolation
  pr: https://github.com/kha997/zenamanagephp/pull/253
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
  created_at: "2026-08-09T20:19:29+07:00"
  updated_at: "2026-08-09T20:19:29+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Mandatory technical gates passed for GAP-010b on combined PR #253: formula-injection safety via explicit fputcsv and type-aware neutralization, bounded-memory chunked streaming for Task and Project, atomic temp-file publication with cleanup, correct written-row counts, tags serialization matching system convention, Request import restored, and full CSV/Excel/JSON compatibility verified."
technical_evidence:
  subject_sha: "3c91bbad6a26f2c28f64ccb96b7ad8e233a2d4b5"
  implementation_tree_digest: "8b24faec138f71c0d6713fa0639999a77f2a9bd77878cd0e89b430464e1b6620"
  verified_pr_head_sha: "3c91bbad6a26f2c28f64ccb96b7ad8e233a2d4b5"
  verified_at: "2026-08-09T20:19:29+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

## Gói quyết định phát hành — GAP-010b: Legacy CSV Export Safety

GAP-010b và GAP-034 là một ứng viên phát hành nguyên tử trên PR #253. OWN-2026-006 đã bảo đảm cô lập digest giữa hai gói Gate 3, nên việc cập nhật quyết định phát hành của GAP-010b không làm mất hiệu lực bằng chứng của GAP-034 và ngược lại. Chủ doanh nghiệp phải phê duyệt cả hai cùng lúc; không thể phát hành GAP-010b một mình.

**1. Vấn đề đã xảy ra là gì?**
Hai endpoint xuất CSV cũ (`POST /api/tasks/bulk/export` và `POST /api/projects/bulk/export`) thiếu bảo vệ chống tiêm công thức bảng tính (formula injection), dùng eager-load `with('tasks')` không giới hạn bộ nhớ, lỗi serialization `tags` (mảng bị ép thành chuỗi `"Array"`), thiếu escape CSV đúng chuẩn, có thể trả về file dở dang nếu có lỗi giữa chừng, và thiếu import `Illuminate\Http\Request` khiến route không gọi được. Đồng thời, số dòng thực tế được ghi không được đếm chính xác.

**2. Người dùng nào bị ảnh hưởng?**
Bất kỳ ai sử dụng chức năng xuất bulk CSV cho Task và Project — thường là quản lý dự án và thành viên có quyền xem báo cáo.

**3. Bây giờ người dùng có thể làm gì?**
Xuất CSV an toàn với header chính xác theo thứ tự cố định, ô văn bản được vô hiệu hoá công thức đúng loại dữ liệu (không đụng số/null/ngày), `tags` serialize theo quy ước `implode(', ', $tags)` của hệ thống, bộ nhớ giới hạn thật sự ở mọi tầng (Task CSV chỉ load `project`, Project CSV/Excel dùng `withCount()` aggregates không hydrate `tasks`), file được xuất bản nguyên tử qua stream tạm (không bao giờ file dở dang), và số dòng thực tế được ghi được đếm chính xác.

**4. Rủi ro nào đã được đóng lại?**
Tiêm công thức vào CSV, cạn kiệt bộ nhớ với project lớn, lỗi serialization `tags`, file dở dang bị báo thành công, thiếu escape chuẩn CSV, route không gọi được vì thiếu import, và số dòng thực tế bị đếm sai.

**5. Đã kiểm thử những gì?**
Đầy đủ ma trận kiểm tra tự động bắt buộc cho GAP-010b: formula/type/tag/parser, bounded-memory seams, count/atomicity, non-CSV compatibility, CSV round-trip, Storage::put/move false-return cleanup, và mid-export failure cleanup — tất cả đều đạt trên head PR #253.

**6. Điều gì KHÔNG nằm trong phạm vi lần này?**
Sửa chữa Task Excel writer, thay đổi JSON writer, mở lại hoạt động route trên production — các thay đổi này vẫn bị chặn bởi GAP-034 cho đến khi cả hai gói được phê duyệt cùng lúc.

**7. Vì sao GAP-034 vẫn để riêng?**
GAP-034 bảo vệ quyền riêng tư tenant cho cùng hai endpoint bằng cách giới hạn query, relation, aggregate và scalar references theo tenant. Cả hai là hard release blockers và phải được duyệt cùng lúc như một ứng viên phát hành nguyên tử trên PR #253; không thể phát hành GAP-010b mà chưa có GAP-034.

**8. Rủi ro còn lại là gì?**
Không có rủi ro mất/lộ dữ liệu. Route vẫn chưa hoạt động trên production cho đến khi Gate 3 của cả hai gói được phê duyệt.

**9. Có thể hoàn tác không?**
Có — không đổi cấu trúc dữ liệu, không thêm migration; có thể quay lại phiên bản trước an toàn bằng cách revert các thay đổi trong `ExportController.php`.

**10. Đề xuất của đội kỹ thuật:** Phát hành (Approve) — như một phần của ứng viên phát hành nguyên tử chung với GAP-034 trên PR #253.

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide
Không được yêu cầu mở pull request kỹ thuật, đọc nhật ký kiểm tra tự động, xem mã nguồn, hay đọc bình luận review — mọi kết luận trên đã được đội kỹ thuật xác minh; owner chỉ quyết định có phát hành hay không. Quyết định này phải bao gồm cả GAP-034; phê duyệt GAP-010b một mình không được phép.
