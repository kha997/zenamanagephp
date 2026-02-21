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
