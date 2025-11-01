/**
 * Seeding helpers for test data
 */

const API_BASE_URL = process.env.API_BASE_URL || process.env.BASE_URL || 'http://127.0.0.1:8000';

export interface UserData {
  email: string;
  password?: string;
  name: string;
  tenant?: string;
  role?: string;
  verified?: boolean;
  locked?: boolean;
  twoFA?: boolean;
}

export interface TenantData {
  slug: string;
  name: string;
}

/**
 * Create a test user via API
 */
export async function createUser(userData: UserData): Promise<any> {
  const response = await fetch(`${API_BASE_URL}/__test__/seed/user`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(userData),
  });
  
  if (!response.ok) {
    throw new Error(`Failed to create user: ${response.statusText}`);
  }
  
  return response.json();
}

/**
 * Create a test tenant via API
 */
export async function createTenant(tenantData: TenantData): Promise<any> {
  const response = await fetch(`${API_BASE_URL}/__test__/seed/tenant`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(tenantData),
  });
  
  if (!response.ok) {
    throw new Error(`Failed to create tenant: ${response.statusText}`);
  }
  
  return response.json();
}

/**
 * Seed canonical test users
 */
export async function seedCanonicalUsers(): Promise<void> {
  const users = [
    {
      email: 'admin@zena.test',
      password: 'password',
      name: 'Admin User',
      tenant: 'zena',
      role: 'admin',
      verified: true,
      twoFA: true,
    },
    {
      email: 'manager@zena.test',
      password: 'password',
      name: 'Manager User',
      tenant: 'zena',
      role: 'manager',
      verified: true,
    },
    {
      email: 'member@zena.test',
      password: 'password',
      name: 'Member User',
      tenant: 'zena',
      role: 'member',
      verified: true,
    },
    {
      email: 'locked@zena.test',
      password: 'password',
      name: 'Locked User',
      tenant: 'zena',
      role: 'member',
      verified: true,
      locked: true,
    },
    {
      email: 'unverified@zena.test',
      password: 'password',
      name: 'Unverified User',
      tenant: 'zena',
      role: 'member',
      verified: false,
    },
  ];
  
  for (const userData of users) {
    try {
      await createUser(userData);
    } catch (error) {
      console.warn(`Failed to seed user ${userData.email}, may already exist`);
    }
  }
}

/**
 * Clean up test users
 */
export async function cleanupUsers(): Promise<void> {
  try {
    await fetch(`${API_BASE_URL}/__test__/seed/cleanup`, {
      method: 'DELETE',
    });
  } catch (error) {
    console.warn('Cleanup endpoint not available');
  }
}

/**
 * Generate unique test user
 */
export function generateTestUser(overrides: Partial<UserData> = {}): UserData {
  const timestamp = Date.now();
  const random = Math.random().toString(36).substring(7);
  
  return {
    email: `test-${timestamp}-${random}@test.com`,
    password: 'TestPassword123!',
    name: `Test User ${random}`,
    tenant: 'zena',
    role: 'member',
    verified: true,
    ...overrides,
  };
}

/**
 * Get user by email
 */
export async function getUser(email: string): Promise<any> {
  const response = await fetch(`${API_BASE_URL}/__test__/seed/user/${encodeURIComponent(email)}`, {
    method: 'GET',
  });
  
  if (!response.ok) {
    return null;
  }
  
  return response.json();
}

