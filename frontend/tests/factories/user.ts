export interface Role {
  id: string;
  name: string;
  scope: string;
  description?: string;
  permissions: string[];
  created_at: string;
  updated_at: string;
}

export interface TestUser {
  id: string;
  name: string;
  display_name: string;
  email: string;
  tenant_id: string;
  tenant_name: string;
  avatar: string | null;
  roles: Role[];
  permissions: string[];
  created_at: string;
  updated_at: string;
}

export const createTestRole = (overrides: Partial<Role> = {}): Role => {
  const timestamp = overrides.created_at ?? new Date().toISOString();

  return {
    id: overrides.id ?? 'role-admin',
    name: overrides.name ?? 'admin',
    scope: overrides.scope ?? 'system',
    description: overrides.description ?? 'Test role',
    permissions: overrides.permissions ?? ['dashboard.view', 'projects.view'],
    created_at: timestamp,
    updated_at: overrides.updated_at ?? timestamp,
  };
};

export const createTestUser = (overrides: Partial<TestUser> = {}): TestUser => {
  const timestamp = overrides.created_at ?? new Date().toISOString();

  return {
    id: overrides.id ?? 'user-1',
    name: overrides.name ?? 'Test User',
    display_name: overrides.display_name ?? 'Test User',
    email: overrides.email ?? 'test@example.com',
    tenant_id: overrides.tenant_id ?? 'tenant-1',
    tenant_name: overrides.tenant_name ?? 'Test Tenant',
    avatar: overrides.avatar ?? null,
    roles: overrides.roles ?? [createTestRole()],
    permissions: overrides.permissions ?? ['dashboard.view', 'projects.view', 'users.view'],
    created_at: timestamp,
    updated_at: overrides.updated_at ?? timestamp,
  };
};
