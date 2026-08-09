---
work_id: GAP-034
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
  spec: docs/superpowers/specs/2026-08-07-gap-034-export-tenant-isolation-design.md
  plan: docs/superpowers/plans/2026-08-07-gap-034-export-tenant-isolation-implementation.md
  branch: impl/GAP-034-export-tenant-isolation
  pr: https://github.com/kha997/zenamanagephp/pull/253
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-09T20:54:00+07:00"
  owner_response_reference: "ChatGPT project conversation — Owner approved GAP-010b Gate 3 and GAP-034 Gate 3 together on 2026-08-09 at 20:54 +07:00, bound to atomic implementation-tree digest 8b24faec138f71c0d6713fa0639999a77f2a9bd77878cd0e89b430464e1b6620."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-09T20:19:29+07:00"
  updated_at: "2026-08-09T20:54:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Mandatory technical gates passed for GAP-034 on combined PR #253: trusted-tenant resolution from middleware attributes, fail-closed when tenant context is missing, tenant predicates on all base queries/relations/aggregates, explicit tenant-safe projections for CSV/Excel/JSON, bounded reference validation via ExportTenantProjectionService, and 28+ security scenarios verified with zero cross-tenant leakage."
technical_evidence:
  subject_sha: "3c91bbad6a26f2c28f64ccb96b7ad8e233a2d4b5"
  implementation_tree_digest: "8b24faec138f71c0d6713fa0639999a77f2a9bd77878cd0e89b430464e1b6620"
  verified_pr_head_sha: "3c91bbad6a26f2c28f64ccb96b7ad8e233a2d4b5"
  verified_at: "2026-08-09T20:19:29+07:00"
owner_decision_binding:
  implementation_tree_digest: "8b24faec138f71c0d6713fa0639999a77f2a9bd77878cd0e89b430464e1b6620"
  decision_recorded_at: "2026-08-09T20:54:00+07:00"
---

## Gói quyết định phát hành — GAP-034: Export Tenant Isolation

GAP-010b và GAP-034 là một ứng viên phát hành nguyên tử trên PR #253. OWN-2026-006 đã bảo đảm cô lập digest giữa hai gói Gate 3, nên việc cập nhật quyết định phát hành của GAP-034 không làm mất hiệu lực bằng chứng của GAP-010b và ngược lại. Chủ doanh nghiệp phải phê duyệt cả hai cùng lúc; không thể phát hành GAP-034 một mình.

**1. Vấn đề đã xảy ra là gì?**
Hai endpoint bulk export (`POST /api/tasks/bulk/export` và `POST /api/projects/bulk/export`) không sử dụng tenant đã xác minh bởi middleware để giới hạn query; caller có thể gửi ID/filter của tenant khác, eager-loaded relation data và scalar foreign identifiers (`assignee_id`, `client_id`, `pm_id`, `created_by`) có thể mang dữ liệu tenant khác, và aggregates/relation serialization có thể lộ dữ liệu cross-tenant.

**2. Người dùng nào bị ảnh hưởng?**
Mọi tenant trong hệ thống — Tenant A có thể nhận được Task/Project của Tenant B, bao gồm cả thông tin quan hệ, aggregate, và scalar references.

**3. Bây giờ người dùng có thể làm gì?**
Xuất chỉ dữ liệu thuộc tenant đã xác minh: query luôn có tenant predicate trước mọi ID/filter, Task chỉ eligible nếu Project tồn tại và thuộc cùng tenant, relation/aggregate được giới hạn cùng tenant, scalar references cross-tenant hoặc stale được xuất là `Unassigned` (CSV/Excel) hoặc `null` (JSON), và Project JSON giữ nguyên `tasks` key với child Task dùng projection an toàn.

**4. Rủi ro nào đã được đóng lại?**
Lộ dữ liệu cross-tenant qua query scoping, eager-loaded relations, unrestricted `toArray()`, scalar foreign identifiers, aggregate counts, và existence oracle (mixed IDs không tiết lộ record có tồn tại).

**5. Đã kiểm thử những gì?**
Đầy đủ 28+ kịch bản bảo mật tự động bắt buộc cho GAP-034: tenant-scoped selection, mixed/cross-tenant ID filtering, relation/aggregate isolation, scalar reference sanitization, Project JSON projection, missing-tenant fail-closed, và writer-format matrix — tất cả đều đạt trên head PR #253.

**6. Điều gì KHÔNG nằm trong phạm vi lần này?**
Global model scope, `TenantScope` rollout, RBAC redesign, migration, thay đổi route, sửa mã nguồn GAP-010b (CSV safety, bounded memory, atomic publication) — các thay đổi này thuộc về GAP-010b hoặc nằm ngoài phạm vi cả hai gói.

**7. Vì sao GAP-010b vẫn để riêng?**
GAP-010b sở hữu Request import, CSV injection mitigation, streaming/chunking, tags serialization, `fputcsv()`, atomic publication, exported-row counting, và bounded Project tabular source. Cả hai là hard release blockers và phải được duyệt cùng lúc như một ứng viên phát hành nguyên tử trên PR #253; không thể phát hành GAP-034 một mình.

**8. Rủi ro còn lại là gì?**
Không có rủi ro mất/lộ dữ liệu. Route vẫn chưa hoạt động trên production cho đến khi Gate 3 của cả hai gói được phê duyệt.

**9. Có thể hoàn tác không?**
Có — không đổi cấu trúc dữ liệu, không thêm migration; có thể quay lại phiên bản trước an toàn bằng cách revert các thay đổi trong `ExportController.php` và xóa `ExportTenantProjectionService.php`.

**10. Đề xuất của đội kỹ thuật:** Phát hành (Approve) — như một phần của ứng viên phát hành nguyên tử chung với GAP-010b trên PR #253.

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide
Không được yêu cầu mở pull request kỹ thuật, đọc nhật ký kiểm tra tự động, xem mã nguồn, hay đọc bình luận review — mọi kết luận trên đã được đội kỹ thuật xác minh; owner chỉ quyết định có phát hành hay không. Quyết định này phải bao gồm cả GAP-010b; phê duyệt GAP-034 một mình không được phép.
