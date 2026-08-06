# ZENA WEBAPP — PROJECT CONSTITUTION

*Version: 1.0*
*Effective: 2026-07-23*
*Status: canonical — supersedes `AI_RULES.md`, `.cursorrules`, `PROJECT_RULES.md` for all governance/principle content. Those three files are kept only as deprecated pointers (see bottom).*
*Review: quarterly, or immediately after any incident that exposes a gap in this document.*

---

## 1. Ultimate Mission

Xây dựng ZENA WebApp thành hệ thống vận hành thực tế, xuyên suốt và đáng tin cậy cho các hoạt động kiến trúc, xây dựng, nội thất, kiểm định và các nghiệp vụ liên quan.

Hệ thống phải giúp:

* Giảm thất thoát thông tin và nhập liệu trùng lặp.
* Kiểm soát khách hàng, báo giá, hợp đồng, dự án, công việc, hồ sơ, chi phí, thanh toán, nghiệm thu và bảo hành.
* Làm rõ người chịu trách nhiệm, trạng thái, thời hạn và bước tiếp theo.
* Cung cấp dữ liệu quản trị chính xác cho Ban lãnh đạo.
* Giảm phụ thuộc vào Excel, tin nhắn cá nhân, trí nhớ và trao đổi miệng.
* Bảo đảm dữ liệu có thể truy vết, kiểm tra và đối chiếu.

Xem `docs/product-purpose-ssot.md` cho phạm vi sản phẩm chi tiết theo runtime hiện tại (product verticals, in/out of scope) — tài liệu đó là SSOT vận hành cụ thể; văn bản này là gốc rễ mục tiêu và nguyên tắc ra quyết định.

## 2. Product Principle

Không đánh giá thành công bằng số lượng tính năng.

Một thay đổi chỉ có giá trị khi nó:

1. Giải quyết một vấn đề vận hành có thật.
2. Có người dùng và tình huống sử dụng cụ thể.
3. Giảm thời gian, sai sót, rủi ro hoặc chi phí.
4. Tạo ra dữ liệu hữu ích cho bước tiếp theo.
5. Có tiêu chí kiểm chứng được.
6. Không phá vỡ các workflow đang hoạt động.

## 3. Mandatory Alignment Check

Trước khi đề xuất hoặc triển khai bất kỳ thay đổi nào, phải xác định:

* Mục tiêu kinh doanh được hỗ trợ.
* Vai trò người dùng chịu tác động.
* Workflow hiện tại.
* Điểm đau hoặc lỗ thủng hiện tại.
* Bằng chứng đang có và phần còn là giả thuyết.
* Kết quả vận hành mong muốn.
* Chỉ số đo lường.
* Phạm vi không thực hiện.
* Rủi ro nếu triển khai sai.
* Phương án nhỏ nhất có thể giải quyết vấn đề.

Nếu chưa đủ thông tin, không được tự coi giả định là sự thật. Phải ghi rõ `ASSUMPTION`, `EVIDENCE NEEDED` hoặc `UNKNOWN`.

Quy tắc bằng chứng cụ thể (route:list, migration, controller/request là SSOT tương ứng) đã được đặc tả sẵn ở `docs/agent-ssot-rules.md` — áp dụng trực tiếp cho mọi claim kỹ thuật.

## 3a. Owner Gates

Mọi thay đổi tiến tới lập kế hoạch triển khai hoặc code phải đi qua đúng ba cổng quyết định của chủ doanh nghiệp (owner), theo `docs/owner-governance/OWNER_OPERATING_MODEL.md`:

* **Gate 1 — Business Request Approval**: owner xác nhận vấn đề vận hành có thật, quan trọng, đúng phạm vi. Trước khi Gate 1 được duyệt, agent chỉ được nghiên cứu (đọc code, không viết plan, không viết code sản phẩm).
* **Gate 2 — Business Design Approval**: owner duyệt workflow trước/sau, vai trò, quy tắc. Trước khi Gate 2 được duyệt, không được tạo `docs/superpowers/plans/*` cho work ID đó, không viết code sản phẩm.
* **Gate 3 — Release Approval**: owner quyết định một thay đổi đã được kiểm chứng có được phát hành hay không. **Gate 3 không chặn việc triển khai, kiểm thử, review kỹ thuật, hay chuẩn bị demo** — các việc đó được phép ngay khi Gate 2 duyệt xong, không cần chờ Gate 3. Gate 3 chỉ chặn: merge (khi owner approval là điều kiện bắt buộc), deploy, thay đổi dữ liệu production, phát hành cho người dùng thật, và việc tuyên bố thay đổi "đã được owner duyệt."

`technical_readiness` (bằng chứng kỹ thuật) và `owner_decision` (quyết định của owner) là hai trường độc lập — không agent nào được suy ra quyết định owner từ trạng thái kỹ thuật sẵn sàng, và owner không thể override một cổng kỹ thuật đỏ (toàn vẹn dữ liệu, tenant isolation, bảo mật, phân quyền, CI bắt buộc).

SSOT cho cơ chế cổng: `docs/owner-governance/OWNER_OPERATING_MODEL.md`. Điều khoản §8 (Evidence Before Claims) và Phụ lục A của văn bản này **vẫn có hiệu lực đầy đủ, không đổi** — các cổng owner là một lớp quyết định bổ sung, không thay thế kỷ luật bằng chứng kỹ thuật hiện có.

## 4. Operational Gap Detection

Khi phân tích workflow, chủ động tìm:

* Trạng thái không có bước tiếp theo.
* Công việc không có người chịu trách nhiệm.
* Handoff không có xác nhận.
* Dữ liệu phải nhập lại ở nhiều nơi.
* Quy trình phụ thuộc vào tin nhắn, Excel hoặc trí nhớ.
* Trường hợp ngoại lệ chưa được xử lý.
* Thiếu phê duyệt, nhật ký hoặc bằng chứng.
* Thiếu nhắc việc, cảnh báo, escalation hoặc SLA.
* Sai lệch giữa trạng thái nghiệp vụ và trạng thái tài chính.
* Tính năng có giao diện nhưng chưa khép kín workflow.
* Dữ liệu được lưu nhưng không tạo ra quyết định quản trị.
* Rủi ro phân quyền, tenant isolation hoặc lộ dữ liệu.
* Thiếu reconciliation, rollback, recovery hoặc audit trail.
* Điểm mà nhân viên có thể né quy trình hoặc ghi nhận ngoài hệ thống.

Không tự động triển khai mọi lỗ thủng phát hiện được. Phải ghi nhận vào `OPERATIONAL_GAP_REGISTER.md`, chấm điểm và đề xuất thứ tự ưu tiên.

*(Ghi chú trạng thái hiện tại: `OPERATIONAL_GAP_REGISTER.md` đã được tạo 2026-07-23, hợp nhất 28 gap từ `docs/audits/*` và `docs/roadmap/backlog.yaml`, xếp theo 8 tier ở mục 5. Cập nhật register đó khi phát hiện gap mới hoặc khi một gap được sửa — không tạo file gap-tracking rời rạc khác.)*

## 5. Priority Rule

Ưu tiên theo thứ tự:

1. Tính toàn vẹn dữ liệu và tenant isolation.
2. Bảo mật, phân quyền và auditability.
3. Workflow cốt lõi tạo doanh thu và thu tiền.
4. Tiến độ, trách nhiệm và cảnh báo.
5. Chi phí, lợi nhuận và đối soát.
6. Trải nghiệm người sử dụng.
7. Tự động hóa và tối ưu nâng cao.
8. Tính năng trang trí hoặc ít giá trị vận hành.

## 6. Hard Constraints

* Không thay đổi domain logic chỉ để làm test chạy qua.
* Không xóa, bỏ qua hoặc vô hiệu hóa test để che lỗi.
* Không tạo public debug route.
* Lỗi tenant isolation phải được ưu tiên trước lỗi RBAC.
* Không để token, mật khẩu hoặc bí mật xuất hiện trong audit hoặc metadata.
* Không sửa ngoài phạm vi nếu chưa giải thích và được phê duyệt.
* Không tự ý merge, deploy hoặc thay đổi dữ liệu production.
* Không tuyên bố hoàn thành nếu chưa có bằng chứng kiểm chứng.
* Không biến một yêu cầu nhỏ thành cuộc đại tu kiến trúc không cần thiết.

*(Ghi chú nợ đã biết: PR#220 — 22/23-07-2026 — đã dùng `merge --admin` để vượt CI đỏ do browser-tests segfault lặp lại 100%. Đây là ngoại lệ có lý do hạ tầng (segfault môi trường CI, không phải lỗi domain logic), nhưng vẫn là vi phạm hình thức của điều khoản "không tự ý merge khi CI đỏ" và cần được coi là nợ hạ tầng ưu tiên xử lý, không lặp lại như thói quen.)*

## 7. Working Loop

Mọi nhiệm vụ phải đi theo vòng lặp:

Goal
→ Inspect current reality
→ Map affected workflow
→ Identify gaps and risks
→ Define acceptance criteria
→ Propose minimal solution
→ Plan
→ Implement
→ Test
→ Adversarial review
→ Operational simulation
→ Update documentation
→ Stop report

## 8. Evidence Before Claims

Khi báo cáo hoàn thành, phải cung cấp:

* Các file đã thay đổi.
* Lý do thay đổi.
* Các lệnh kiểm tra đã chạy.
* Kết quả test, lint, build hoặc smoke test.
* Workflow thực tế đã mô phỏng.
* Trường hợp biên đã kiểm tra.
* Rủi ro còn lại.
* Việc chưa thực hiện.
* Commit hoặc PR liên quan.

Không được dùng các câu như "có vẻ ổn", "nên hoạt động" hoặc "đã hoàn tất" nếu chưa có bằng chứng.

## 9. Scope Discipline

Khi phát hiện vấn đề ngoài phạm vi:

* Không âm thầm sửa.
* Ghi nhận vào danh sách phát hiện.
* Đánh giá mức độ nghiêm trọng.
* Nêu tác động và bằng chứng.
* Đề xuất task riêng.
* Chỉ sửa ngay nếu đó là lỗi nghiêm trọng ngăn cản tính đúng đắn, bảo mật hoặc toàn vẹn dữ liệu của nhiệm vụ hiện tại.

## 10. Completion Definition

Một task chỉ được coi là hoàn thành khi:

* Acceptance criteria đều đạt.
* Test liên quan đều chạy thành công.
* Không làm hỏng workflow lân cận.
* Phân quyền và tenant isolation được kiểm tra khi có liên quan.
* Dữ liệu và audit trail đúng.
* Tài liệu và progress được cập nhật.
* Có stop report trung thực, bao gồm cả phần chưa làm và rủi ro còn lại.

---

## Appendix A — Technical Non-Negotiables

*Hợp nhất từ `PROJECT_RULES.md` (v1.0, 2025-09-24) — chi tiết hoá phần "Hard Constraints" (mục 6) và "Priority Rule" (mục 5) cho lớp kỹ thuật cụ thể của ZenaManage. Nếu tài liệu này (PROJECT_CONSTITUTION.md) và các chi tiết bên dưới xung đột với thực tế route/migration/controller hiện tại, thực tế code thắng — ghi nhận xung đột như một finding, không tự ý sửa constitution.*

### A.1 Architecture & Scope
- UI renders only — business logic sống trong API.
- Web (Blade/React): session auth + tenant scope.
- API `/api/v1/*`, `/api/zena/*`: token `auth:sanctum` + ability (admin/tenant).
- Không side-effect trong UI routes — mọi write qua POST/PATCH/DELETE API.
- `/admin/*` (system-wide) ≠ `/app/*` (tenant-scoped); `/_debug/*` chỉ cho test, có DebugGate (env + IP).
- Legacy phải có kế hoạch gỡ bỏ (Announce → 301 → 410), theo dõi trong `legacy-map.json`.

### A.2 Naming & Standardization
- Routes: kebab-case, số nhiều cho danh sách, số ít+id cho chi tiết.
- Controllers/Services: PascalCase với động từ (`ProjectService.updateBudget`).
- DB schema: snake_case; FK bắt buộc; soft delete (`deleted_at`); mã duy nhất (`project_code`…).
- Enum là tập cố định (vd `status ∈ {planning, active, on_hold, completed, canceled}`).

### A.3 Error Handling & API Contracts
- Error envelope chuẩn có `error.id`, `code` (DOMAIN.CODE, ổn định), `message` (i18n).
- HTTP mapping bắt buộc: 400/401/403/404/409/422/429/500/503; `Retry-After` cho 429/503.
- Không lộ chi tiết nội bộ trong message; dùng `error.id` để tra log.

### A.4 Logging, Monitoring & Incident Response
- Structured JSON logs: timestamp, level, tenant_id, user_id, X-Request-Id, route, latency, result, PII đã redact.
- 500/CRITICAL auto-notify kèm error.id, route, tenant, p95 snapshot, link runbook.
- Metrics per-tenant: QPS, error rate, p95 latency.

### A.5 Testing Strategy
- Unit (services/mappers/validators) + Integration (controller+DB+auth+RBAC+tenant) + E2E (critical path).
- Isolation tests chứng minh tenant A không đọc được tenant B.
- CI gate bắt buộc: test phải pass; flakiness = build fail (không phải merge --admin theo thói quen — xem ghi chú ở mục 6).

### A.6 Documentation & Versioning
- OpenAPI/Swagger cho `/api/v1/*`; docs versioned theo API version.
- Deprecation có ngày + migration note; changelog người đọc được.

### A.7 Multi-Tenant Scalability & Isolation
- Mọi query filter theo `tenant_id`, enforce ở repository/service layer.
- Composite index `(tenant_id, foreign_key)` cho bảng nóng (tasks, projects, documents).
- Background jobs: queue key có tenant_id; worker idempotent.

### A.8 Performance & UX
- Budget: page p95 < 500ms (20–50 rows); API p95 < 300ms.
- Cache KPI/insights 60s per tenant, invalidate khi write.
- Universal Page Frame: Header → Global Nav → Page Nav → KPI Strip → Alert Bar → Main Content → Activity.
- Mobile-first, WCAG 2.1 AA.

### A.9 Security & Permissions
- RBAC tường minh: super_admin / PM / Member / Client…
- Admin API: `ability:admin`. App API: `ability:tenant`.
- Web dùng CSRF; API dùng token; CSP/CORS/HSTS/secure cookies.
- Rate-limit endpoint public; secrets không nằm trong repo.

### A.10 No Duplicates / No Orphans
- Một chức năng = một route/view. Không tạo hai màn hình cùng mục đích.
- FK + ON DELETE rule tường minh cho mỗi quan hệ.
- Mọi bản ghi có tenant_id.

### A.11 CI/CD & Deployment
- Pipeline: Lint → Unit → Integration → Build → OpenAPI gen → E2E (staging) → Security checks → Manual approval → Deploy.
- Zero-downtime migration: additive trước, backfill, rồi mới drop field cũ.
- Post-deploy smoke: health, dashboard, list, create+rollback test entity.

### A.12 Definition of Done
- Không TODO/console log/test route còn sót.
- Route map không có side-effect ở UI.
- Lint/format/i18n/a11y pass; docs & diagram khớp code; OpenAPI cập nhật.

---

## Appendix B — Governance Map (nguồn sự thật theo chủ đề)

| Chủ đề | Nguồn SSOT |
|---|---|
| Mission, nguyên tắc ra quyết định, working loop | **File này** |
| Bằng chứng kỹ thuật (route/schema/validation) | `docs/agent-ssot-rules.md` |
| Phạm vi sản phẩm runtime hiện tại | `docs/product-purpose-ssot.md` |
| Trình tự roadmap & backlog có ID | `docs/roadmap/canonical-roadmap.md`, `docs/roadmap/backlog.yaml` |
| Gap vận hành đã phát hiện, chấm điểm, xếp ưu tiên | `OPERATIONAL_GAP_REGISTER.md` (nguồn thô: `docs/audits/*`) |
| Route legacy đang gỡ bỏ | `legacy-map.json` |
| Cổng quyết định owner và gói quyết định (packet) | `docs/owner-governance/OWNER_OPERATING_MODEL.md` |

## Deprecated Files

Các file sau **không còn là nguồn sự thật** — giữ lại chỉ vì lịch sử/tương thích công cụ đọc chúng, nội dung của chúng đã được hợp nhất vào file này:

- `AI_RULES.md` (v1.0, 2025-09-24)
- `.cursorrules`
- `PROJECT_RULES.md` (v1.0, 2025-09-24) — nội dung kỹ thuật giữ nguyên ở Appendix A phía trên.

Khi có xung đột giữa các file trên và `PROJECT_CONSTITUTION.md`, file này thắng.
