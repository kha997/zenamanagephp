-- OWN-2026-024 (O-17h) — H0 production RBAC inventory. STRICTLY READ-ONLY. NOT EXECUTED.
-- Execution requires a separate, explicit Owner authorization (see 101 §2). Prepared 2026-11-24 against head c94ff67c0.
-- Target: MySQL 8 (production schema as migrated by the deploy workflow). Output is aggregate only:
-- no names, e-mails, password hashes, tokens or raw user/tenant ids are selected. Tenants are reported as ordinals.
-- Every statement is a SELECT. The session is forced read-only first; any write would fail with ER_CANT_EXECUTE_IN_READ_ONLY_TRANSACTION.

SET SESSION TRANSACTION READ ONLY;
START TRANSACTION READ ONLY;

-- Q1. Tenant count (multi-tenancy decides whether host-wide operations cross company boundaries).
SELECT COUNT(*) AS tenants_total,
       SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) AS tenants_not_deleted
FROM tenants;

-- Q2. Owner / admin-like role instances (UNIQUE roles.name means at most one row per name).
SELECT name, scope, (tenant_id IS NULL) AS is_global, is_active
FROM roles
WHERE LOWER(name) IN ('super_admin', 'system admin', 'system_admin', 'admin', 'owner')
ORDER BY name;

-- Q3. Users holding the super_admin role, per tenant ordinal (canonical user_roles), and active count.
SELECT t.tenant_ordinal, COUNT(*) AS super_admin_users, SUM(u.is_active = 1) AS active_super_admin_users
FROM user_roles ur
JOIN roles r ON r.id = ur.role_id AND r.name = 'super_admin'
JOIN users u ON u.id = ur.user_id
JOIN (SELECT id, DENSE_RANK() OVER (ORDER BY created_at, id) AS tenant_ordinal FROM tenants) t ON t.id = u.tenant_id
GROUP BY t.tenant_ordinal
ORDER BY t.tenant_ordinal;

-- Q3b. super_admin holders without a tenant (platform-level accounts), and ledger/projection drift (S2: system_user_roles vs user_roles).
SELECT
  (SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id AND r.name = 'super_admin'
     JOIN users u ON u.id = ur.user_id WHERE u.tenant_id IS NULL) AS super_admin_without_tenant,
  (SELECT COUNT(*) FROM system_user_roles s JOIN roles r ON r.id = s.role_id AND r.name = 'super_admin'
     WHERE NOT EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = s.user_id AND ur.role_id = s.role_id)) AS ledger_rows_missing_projection,
  (SELECT COUNT(*) FROM users WHERE role = 'super_admin') AS legacy_users_role_super_admin;

-- Q4. Canonical grants held by super_admin (count + exact code list; codes are not PII).
SELECT COUNT(*) AS super_admin_grant_count
FROM role_permissions rp JOIN roles r ON r.id = rp.role_id AND r.name = 'super_admin';

SELECT p.code
FROM role_permissions rp
JOIN roles r ON r.id = rp.role_id AND r.name = 'super_admin'
JOIN permissions p ON p.id = rp.permission_id
ORDER BY p.code;

-- Q5. Codes the Owner role does NOT hold (compare with the 26 missing codes of 101 §7 on the synthetic production seed).
SELECT p.code
FROM permissions p
WHERE p.id NOT IN (SELECT rp.permission_id FROM role_permissions rp JOIN roles r ON r.id = rp.role_id AND r.name = 'super_admin')
ORDER BY p.code;

-- Q6. Platform-like and RBAC-administration codes: which roles hold them and how many users hold each role.
SELECT p.code, r.name AS role_name, r.scope, (r.tenant_id IS NULL) AS role_is_global,
       (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) AS users_with_role
FROM permissions p
JOIN role_permissions rp ON rp.permission_id = p.id
JOIN roles r ON r.id = rp.role_id
WHERE p.code LIKE 'system.%' OR p.code LIKE 'platform.%' OR p.code IN (
  'role.assign', 'role.create', 'role.edit', 'role.delete', 'permission.create', 'permission.edit', 'permission.delete',
  'permission.import', 'admin.company.manage', 'auth.bulk.manage', 'project.assign_manager', 'user.manage_roles')
ORDER BY p.code, r.name;

-- Q7. Does a permission literally named/coded "admin" exist? (101: no -> the 80 rbac:admin routes are super_admin-only.)
SELECT COUNT(*) AS admin_permission_rows FROM permissions WHERE code = 'admin' OR name = 'admin';

-- Q8. Custom / tenant roles that can administer roles (RBAC-ESC-1 exposure) and how many users hold them.
SELECT r.scope, (r.tenant_id IS NULL) AS role_is_global, COUNT(DISTINCT r.id) AS roles, COUNT(DISTINCT ur.user_id) AS users
FROM roles r
JOIN role_permissions rp ON rp.role_id = r.id
JOIN permissions p ON p.id = rp.permission_id AND p.code IN ('role.assign', 'role.edit', 'role.create')
LEFT JOIN user_roles ur ON ur.role_id = r.id
WHERE r.name <> 'super_admin'
GROUP BY r.scope, (r.tenant_id IS NULL);

-- Q9. Seeder-state evidence: per role, how many grants exist and the earliest/latest grant timestamp
-- (shows whether production:rbac re-ran and whether hand-made grants exist).
SELECT r.name AS role_name, COUNT(*) AS grants, MIN(rp.created_at) AS first_grant_at, MAX(rp.created_at) AS last_grant_at
FROM role_permissions rp JOIN roles r ON r.id = rp.role_id
GROUP BY r.name
ORDER BY r.name;

-- Q10. O16-1: projects without a PM (no System Admin role exists in production to appoint one).
SELECT COUNT(*) AS projects_total, SUM(pm_id IS NULL) AS projects_without_pm FROM projects WHERE deleted_at IS NULL;

ROLLBACK;
