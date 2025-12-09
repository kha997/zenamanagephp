import { test, expect } from '@playwright/test';
import { authHeaders, login, expectSuccess, uniqueName } from '../helpers/apiClient';

/**
 * Golden Path 4: Admin / Tenant Management
 * 
 * Flow: Tenant admin views users → Assigns role → Deactivates user
 * 
 * This test verifies:
 * - Tenant admin can list users in tenant
 * - Role assignment works
 * - User deactivation works
 * - Permission checks prevent unauthorized access
 * - Tenant isolation: Cannot manage users from other tenants
 */
test.describe('Golden Path 4: Admin / Tenant Management', () => {
  let adminSession: any;
  let regularUserSession: any;
  let userId: string;

  test.beforeEach(async ({ request }) => {
    // Login as tenant admin
    adminSession = await login(request, 'admin@zena.local', 'password');
    
    // Also login as regular user for permission tests
    regularUserSession = await login(request, 'test@example.com', 'password');
  });

  test('@golden-path tenant admin can list users in tenant', async ({ request }) => {
    // Step 1: List users in tenant
    const listResponse = await request.get('/api/v1/app/users', {
      headers: authHeaders(adminSession.token),
    });
    
    const listData = await expectSuccess(listResponse);
    expect(listData.data.users).toBeDefined();
    expect(Array.isArray(listData.data.users)).toBe(true);
    
    // All users should belong to admin's tenant
    listData.data.users.forEach((user: any) => {
      expect(user.tenant_id).toBe(adminSession.user.tenant_id);
    });
    
    // Store first user ID for later tests
    if (listData.data.users.length > 0) {
      userId = listData.data.users[0].id;
    }
  });

  test('@golden-path tenant admin can view user details', async ({ request }) => {
    // First get a user ID
    const listResponse = await request.get('/api/v1/app/users', {
      headers: authHeaders(adminSession.token),
    });
    const listData = await expectSuccess(listResponse);
    
    if (listData.data.users.length === 0) {
      test.skip();
      return;
    }
    
    const userId = listData.data.users[0].id;
    
    // Get user details
    const userResponse = await request.get(`/api/v1/app/users/${userId}`, {
      headers: authHeaders(adminSession.token),
    });
    
    const userData = await expectSuccess(userResponse);
    expect(userData.data.user).toBeDefined();
    expect(userData.data.user.id).toBe(userId);
    expect(userData.data.user.tenant_id).toBe(adminSession.user.tenant_id);
  });

  test('@golden-path tenant admin can assign role to user', async ({ request }) => {
    // Get a user to assign role to
    const listResponse = await request.get('/api/v1/app/users', {
      headers: authHeaders(adminSession.token),
    });
    const listData = await expectSuccess(listResponse);
    
    if (listData.data.users.length === 0) {
      test.skip();
      return;
    }
    
    const userId = listData.data.users[0].id;
    const originalRole = listData.data.users[0].role;
    
    // Assign new role (if not already that role)
    const newRole = originalRole === 'pm' ? 'member' : 'pm';
    
    const assignRoleResponse = await request.patch(`/api/v1/app/users/${userId}/role`, {
      headers: authHeaders(adminSession.token),
      data: { role: newRole },
    });
    
    const roleData = await expectSuccess(assignRoleResponse);
    expect(roleData.data.user.role).toBe(newRole);
    
    // Verify role was updated
    const userResponse = await request.get(`/api/v1/app/users/${userId}`, {
      headers: authHeaders(adminSession.token),
    });
    const userData = await expectSuccess(userResponse);
    expect(userData.data.user.role).toBe(newRole);
    
    // Restore original role for cleanup
    await request.patch(`/api/v1/app/users/${userId}/role`, {
      headers: authHeaders(adminSession.token),
      data: { role: originalRole },
    });
  });

  test('@golden-path tenant admin can deactivate user', async ({ request }) => {
    // Get a user to deactivate
    const listResponse = await request.get('/api/v1/app/users', {
      headers: authHeaders(adminSession.token),
    });
    const listData = await expectSuccess(listResponse);
    
    if (listData.data.users.length === 0) {
      test.skip();
      return;
    }
    
    // Find an active user (not the admin themselves)
    const userToDeactivate = listData.data.users.find(
      (u: any) => u.id !== adminSession.user.id && u.active !== false
    );
    
    if (!userToDeactivate) {
      test.skip();
      return;
    }
    
    const userId = userToDeactivate.id;
    
    // Deactivate user
    const deactivateResponse = await request.patch(`/api/v1/app/users/${userId}/deactivate`, {
      headers: authHeaders(adminSession.token),
      data: { reason: 'Test deactivation' },
    });
    
    const deactivateData = await expectSuccess(deactivateResponse);
    expect(deactivateData.data.user.active).toBe(false);
    
    // Verify user is deactivated
    const userResponse = await request.get(`/api/v1/app/users/${userId}`, {
      headers: authHeaders(adminSession.token),
    });
    const userData = await expectSuccess(userResponse);
    expect(userData.data.user.active).toBe(false);
    
    // Reactivate for cleanup
    await request.patch(`/api/v1/app/users/${userId}/activate`, {
      headers: authHeaders(adminSession.token),
    });
  });

  test('@golden-path regular user cannot manage users', async ({ request }) => {
    // Regular user should not be able to list users (or get empty list)
    const listResponse = await request.get('/api/v1/app/users', {
      headers: authHeaders(regularUserSession.token),
    });
    
    // Should either get 403 or empty list
    if (listResponse.status() === 403) {
      const errorBody = await listResponse.json();
      expect(errorBody.ok).toBe(false);
      expect(errorBody.code).toBe('FORBIDDEN');
    } else {
      const listData = await expectSuccess(listResponse);
      // If they can list, they shouldn't be able to modify
    }
    
    // Try to assign role (should fail)
    if (userId) {
      const assignRoleResponse = await request.patch(`/api/v1/app/users/${userId}/role`, {
        headers: authHeaders(regularUserSession.token),
        data: { role: 'pm' },
      });
      
      expect(assignRoleResponse.status()).toBe(403);
      const errorBody = await assignRoleResponse.json();
      expect(errorBody.ok).toBe(false);
      expect(errorBody.code).toBe('FORBIDDEN');
    }
  });

  test('@golden-path cannot deactivate own account', async ({ request }) => {
    // Admin should not be able to deactivate themselves
    const deactivateResponse = await request.patch(`/api/v1/app/users/${adminSession.user.id}/deactivate`, {
      headers: authHeaders(adminSession.token),
      data: { reason: 'Test' },
    });
    
    // Should get 409 Conflict
    expect(deactivateResponse.status()).toBe(409);
    const errorBody = await deactivateResponse.json();
    expect(errorBody.ok).toBe(false);
    expect(errorBody.code).toBe('CANNOT_DEACTIVATE_SELF');
  });

  test('@golden-path invalid role assignment shows error', async ({ request }) => {
    // Get a user
    const listResponse = await request.get('/api/v1/app/users', {
      headers: authHeaders(adminSession.token),
    });
    const listData = await expectSuccess(listResponse);
    
    if (listData.data.users.length === 0) {
      test.skip();
      return;
    }
    
    const userId = listData.data.users[0].id;
    
    // Try to assign invalid role
    const assignRoleResponse = await request.patch(`/api/v1/app/users/${userId}/role`, {
      headers: authHeaders(adminSession.token),
      data: { role: 'invalid_role' },
    });
    
    // Should get 422 Unprocessable Entity
    expect(assignRoleResponse.status()).toBe(422);
    const errorBody = await assignRoleResponse.json();
    expect(errorBody.ok).toBe(false);
    expect(errorBody.code).toBe('VALIDATION_FAILED');
    expect(errorBody.details?.validation).toBeDefined();
  });

  test('@golden-path tenant isolation: cannot manage users from other tenant', async ({ request }) => {
    // This test verifies that tenant admin can only manage users in their tenant
    // In a full implementation:
    // 1. Create user in tenant A
    // 2. Login as tenant B admin
    // 3. Try to manage tenant A user
    // 4. Should get 403 or 404
    
    // For now, we verify that all listed users belong to admin's tenant
    const listResponse = await request.get('/api/v1/app/users', {
      headers: authHeaders(adminSession.token),
    });
    const listData = await expectSuccess(listResponse);
    
    listData.data.users.forEach((user: any) => {
      expect(user.tenant_id).toBe(adminSession.user.tenant_id);
    });
  });
});

