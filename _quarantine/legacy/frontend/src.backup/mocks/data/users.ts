export interface MockUser {
  id: number;
  name: string;
  email: string;
  tenant_id: number;
  system_roles: string[];
  created_at: string;
  updated_at: string;
}

export const mockUsers: MockUser[] = [
  {
    id: 1,
    name: 'Admin User',
    email: 'admin@zenamanage.com',
    tenant_id: 1,
    system_roles: ['super_admin'],
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z'
  },
  {
    id: 2,
    name: 'Project Manager',
    email: 'pm@zenamanage.com',
    tenant_id: 1,
    system_roles: ['project_manager'],
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z'
  },
  {
    id: 3,
    name: 'Team Lead',
    email: 'lead@zenamanage.com',
    tenant_id: 1,
    system_roles: ['team_lead'],
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z'
  },
  {
    id: 4,
    name: 'Developer',
    email: 'dev@zenamanage.com',
    tenant_id: 1,
    system_roles: ['developer'],
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z'
  },
  {
    id: 5,
    name: 'Client User',
    email: 'client@example.com',
    tenant_id: 2,
    system_roles: ['client'],
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z'
  }
];