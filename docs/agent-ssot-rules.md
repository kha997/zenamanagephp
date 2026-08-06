# Agent SSOT Rules (Non-negotiable)

These rules apply to **all agents** (Codex / Continue / humans) when producing reports or implementing changes.

## 1) Evidence or UNKNOWN
- Every factual claim MUST include evidence:
  - **Command output snippet** (preferred), or
  - **file path + line range**.
- If evidence is missing, the claim MUST be labeled **UNKNOWN**.
- No guessing, no “assume”, no filling gaps.

## 2) Routes and Middleware = `route:list` SSOT
- Any statement about routes, HTTP methods, URIs, or middleware stack MUST be backed by:
  - `php artisan route:list --except-vendor -v --path='<prefix>'`
- Claims like “implicit via apiResource” are INVALID unless proven by `route:list`.

## 3) Schema = Migration SSOT
- Any statement about DB columns/nullable/defaults/keys MUST cite:
  - `database/migrations/<...>.php` (path + lines).
- If model/controller conflicts with migration, migration wins (then raise an issue).

## 4) Validation/Defaults = Controller/Request SSOT
- Any statement about request validation or defaults MUST cite:
  - controller/request class code (path + lines).
- Do not invent required fields or statuses.

## 5) Tenant Isolation & RBAC are invariants
- Never relax tenant isolation or RBAC to “make it work”.
- Cross-tenant by-id should prefer **404** (anti-enumeration) unless SSOT says otherwise.
- If a route lacks tenant isolation, treat as a security bug until SSOT proves it is system-scope.

## 6) Work isolation (branches)
- Work on a dedicated branch.
- Do NOT force-push/rebase someone else’s WIP branch.
- If another agent is mid-task, do not edit the same files on the same branch.

## 7) Minimal change principle
- Prefer smallest PR that fixes one thing.
- No new tooling/dependencies unless explicitly requested and justified.

## 8) What to include in any agent report
- Commands executed (exact) + short outputs.
- File/line citations for key claims.
- Clear “UNKNOWN” list (if any).

## 9) Owner-facing content is a distinct artifact, not a technical report
Khi tạo Owner Decision Packet (`docs/owner-decisions/<WORK-ID>/0X-*.md`), không trích `route:list`, số dòng migration, hay code controller làm **nội dung** packet — trích chúng trong Engineering Evidence Layer đã liên kết (`references.spec`/`references.plan`) thay vào đó. Packet phải đọc được bởi người không hiểu bất kỳ quy tắc nào từ 1 đến 8 ở trên.

Mọi báo cáo agent (stop report) phải có đủ 5 phần: Owner Summary, Technical Evidence Appendix, Decision Needed, Residual Risk, và phần nêu rõ owner KHÔNG được yêu cầu quyết định điều gì. Trước khi đưa một phát hiện lên owner, áp dụng bài kiểm tra 4 câu hỏi ở `docs/owner-governance/OWNER_DECISION_RULES.md` — nếu cả 4 câu đều "không," đây là chi tiết triển khai, giải quyết kỹ thuật, không đưa lên owner.
