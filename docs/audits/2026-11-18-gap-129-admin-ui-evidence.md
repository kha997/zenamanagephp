# GAP-129 — Admin UI for companies, roles and users: discovery evidence

Date: 2026-11-18. Base: `main` at `a3743ac6289e4ba4c21ee6d6352c4922a1628c81` (after GAP-126). Production went live the same day
(`https://app.zena.com.vn`, one company, one Owner account).

## 1. What an Owner can do today

| Task | Web UI | Only way today |
|---|---|---|
| Create a staff account | none | `php artisan production:user --email --name --role` over SSH |
| Reset a password | none ("quên mật khẩu" only redirects to `/login`) | `production:user --reset-password` |
| Change a user's role | none | none (CLI cannot change a role either) |
| Deactivate / reactivate a user | none | none (direct SQL) |
| See roles and what each can do | none | `production:rbac --dry-run` |
| Edit the company's name | none | none |
| Create another company | none | `production:bootstrap` (refuses once any company exists) |

## 2. Existing admin pages

- `routes/web.php` group `prefix('admin')->name('admin-')`, middleware `auth`, `tenant.isolation`, `rbac:admin`.
- `/admin/users` → `resources/views/admin/users-content.blade.php` (606 lines): Alpine `adminUsers()` with
  "Mock data for demonstration" (line 393). Reads nothing from the database.
- `/admin/tenants` → `resources/views/admin/tenants-content.blade.php` (588 lines): Alpine `adminTenants()`, same mock data
  (line 370).
- `/admin/security`, `/admin/alerts`, `/admin/activities` return one-line HTML placeholders.
- `/admin/maintenance` is real (GAP-055 hardened clear-cache).

## 3. Existing tenant-side pages

- `/app/team` lists the company's real users (name, email, role column, active), read-only.
- `/app/team/invite` (`resources/views/team/invite.blade.php`) posts to `/team/invite`, which has no route.

## 4. Reusable domain logic

- `App\Console\Commands\ProductionUserCommand`:
  - only the 5 canonical roles of `ProductionRbacCommand::ROLES` (`super_admin`, `Finance`, `Project Manager`,
    `site_engineer`, `Project Member`), and only when the role has permissions loaded;
  - email unique;
  - password `Str::password(20, symbols: true)` shown once;
  - audit events `production.user.created` and `production.user.password_reset`.
- Web login refuses `is_active = false` (GAP-126 B-1), so deactivation is already enforced at sign-in.
- Roles and permissions: `roles`, `permissions`, `role_permissions`, user ↔ role via `user_roles`. Production counts after
  `production:rbac`: super_admin 221, Finance 15, Project Manager 62, site_engineer 18, Project Member 32.
- Permission Matrix API (GAP-042) exists for role ↔ permission edits; no web page uses it.

## 5. Gaps

1. Every account action needs SSH and a terminal.
2. A role cannot be changed and a user cannot be deactivated without SQL. A departing employee keeps access until someone
   runs SQL.
3. The two admin pages that look like user / company management show fake data, which is misleading.
