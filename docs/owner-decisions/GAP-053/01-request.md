---
work_id: GAP-053
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-09-15-gap-053-dashboard-rbac-performance-fixture-evidence.md
  plan: null
  branch: docs/GAP-053-dashboard-rbac-performance-fixture-gate1
  pr: https://github.com/kha997/zenamanagephp/pull/317
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
  created_at: "2026-09-15T16:39:38+07:00"
  updated_at: "2026-09-15T16:43:07+07:00"
generated_by: agent
---

## Owner Summary

Lỗi 403 của Client Representative trong bài test hiệu năng là lỗi dựng user
test cũ, không phải lỗi ứng dụng hay lỗi bảo mật. User có phân quyền RBAC chuẩn
đã được chạy thật và nhận 200 đúng hợp đồng dashboard đã phát hành.

## Vấn đề vận hành

GAP-041 làm bài test hiệu năng dashboard chạy thật thay vì báo xanh với 0 test.
Khi chạy thật, vòng lặp tạo bốn user chỉ ghi vai trò vào cột `users.role`,
không tạo assignment RBAC chuẩn. Ba vai trò tình cờ qua được nhánh tương thích
cũ; `client_rep` bị chặn 403 vì vai trò RBAC chuẩn tương ứng là `client`.

## Người dùng bị ảnh hưởng

Không phát hiện người dùng production có assignment chuẩn bị ảnh hưởng. Tác
động đã xác nhận nằm ở bài test hiệu năng và check CI của Draft PR #316. Một
Client Representative được dựng đúng với dashboard role `client_rep` và RBAC
role `client` truy cập route thành công.

## Bằng chứng

Đội đã chạy lại bài test gốc trên đúng main chính tắc: ba vai trò đầu trả 200,
`client_rep` trả 403. Một probe kiểm soát dùng đăng nhập API và Bearer token
thật cho thấy user scalar-only có hai bảng assignment rỗng và trả
`RBAC_ACCESS_DENIED`; user tương đương dùng helper RBAC chuẩn có assignment
`client` ở cả hai pivot và trả 200. Control `project_manager` trả 200 theo cả
hai cách, chứng minh kết quả xanh scalar-only chỉ là nhánh tương thích. Bộ test
hợp đồng GAP-052 hiện tại cũng chạy xanh 6 test/153 assertion cho đủ bảy vai
trò và hai route được giữ lại. Chi tiết file, dòng, lệnh và call graph nằm trong
evidence audit liên kết ở frontmatter.

## Tác động nếu không xử lý

Check hiệu năng Dashboard của GAP-041 tiếp tục đỏ dù ứng dụng đang thực thi
đúng chính sách RBAC. Điều này chặn Gate 3 của GAP-041 và khiến phép đo vai trò
`client_rep` không bao giờ đi qua middleware để đo endpoint thực sự.

## Phạm vi đề xuất

Nếu Owner duyệt Gate 1, Gate 2 chỉ cần thiết kế correction test-only nhỏ nhất:
dùng fixture RBAC chuẩn đã phát hành trong đúng vòng lặp performance, giữ nguyên
dashboard role scalar, và map `client_rep` sang RBAC role `client`. Không thay
đổi application semantics, security policy, route, middleware, permission,
threshold hay expected status.

## Loại trừ rõ ràng

- Không sửa `DashboardPerformanceTest` hoặc code ứng dụng tại Gate 1.
- Không thêm `client_rep` vào middleware, không nới RBAC, không cấp quyền giả.
- Không đổi GAP-041, Draft PR #316, GAP-045 threshold hay evidence-freshness.
- Không chuẩn bị Gate 2, implementation plan, merge, release hoặc deploy.
- Hai fixture scalar-only lân cận trong E2E/FinalSystem chỉ được ghi nhận; không
  tự động đưa vào phạm vi correction này khi chưa có Gate-2 decision.

## Đề xuất

Đề xuất Owner phê duyệt chuyển sang Gate 2 cho correction test-only tối thiểu
theo helper `createTenantUserWithRbac()`, với mapping least-privilege
`client_rep -> client`.

## Quan hệ với GAP-041 PR #316

GAP-041 chỉ làm CI chọn và chạy test trung thực; nó không tạo lỗi này. Sau khi
GAP-053 được phê duyệt, triển khai và release riêng, GAP-041 có thể giữ nguyên
toàn bộ thay đổi đã viết, chỉ cần tích hợp main mới và chạy lại evidence/checks.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Owner chưa được yêu cầu duyệt cách sửa code/test, Gate 2, merge hay release.
Gate 1 chỉ hỏi liệu lỗi fixture test đã được chứng minh này có đáng chuyển sang
thiết kế correction test-only hay không.
