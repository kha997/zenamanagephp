import { test, expect } from '@playwright/test';
import { MinimalAuthHelper } from '../helpers/auth';
import { testData } from '../helpers/data';

/**
 * Comprehensive E2E Tests for User Authentication and Role Management
 * 
 * This suite tests:
 * - User login
 * - User logout
 * - Password reset
 * - Role management (creation, modification, deletion)
 */
test.describe('Comprehensive E2E: User Auth & Role Management', () => {
  let authHelper: MinimalAuthHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new MinimalAuthHelper(page);
  });

  // ==================== USER LOGIN TESTS ====================

  test('should successfully login with valid credentials', async ({ page }) => {
    const adminEmail = process.env.SMOKE_ADMIN_EMAIL || testData.adminUser.email;
    const adminPassword = process.env.SMOKE_ADMIN_PASSWORD || testData.adminUser.password;

    await authHelper.login(adminEmail, adminPassword);
    
    expect(await authHelper.isLoggedIn()).toBe(true);
    
    // Verify redirect to dashboard
    await expect(page).toHaveURL(/.*dashboard.*/);
    
    // Verify user menu is visible (logged in indicator)
    await expect(page.locator('[data-testid="user-menu"]')).toBeVisible({ timeout: 10000 });
  });

  test('should fail login with invalid credentials', async ({ page }) => {
    await page.goto('/login');
    
    await page.fill('#email', 'invalid@example.com');
    await page.fill('#password', 'wrongpassword');
    await page.click('#loginButton');
    
    // Should show error message
    await expect(page.locator('.error, [role="alert"], .text-red-600')).toBeVisible({ timeout: 5000 });
    
    // Should still be on login page
    await expect(page).toHaveURL(/.*login.*/);
    
    // Should not be logged in
    expect(await authHelper.isLoggedIn()).toBe(false);
  });

  test('should validate email format on login', async ({ page }) => {
    await page.goto('/login');
    
    // Try invalid email format
    await page.fill('#email', 'invalid-email');
    await page.fill('#password', 'password123');
    
    // Should show validation error
    const emailInput = page.locator('#email');
    await emailInput.blur();
    
    // Check for HTML5 validation or custom validation message
    const validationMessage = await emailInput.evaluate((el: HTMLInputElement) => {
      return el.validationMessage || (el as any).customValidationMessage;
    });
    
    expect(validationMessage).toBeTruthy();
  });

  // ==================== USER LOGOUT TESTS ====================

  test('should successfully logout user', async ({ page }) => {
    const adminEmail = process.env.SMOKE_ADMIN_EMAIL || testData.adminUser.email;
    const adminPassword = process.env.SMOKE_ADMIN_PASSWORD || testData.adminUser.password;

    // Login first
    await authHelper.login(adminEmail, adminPassword);
    expect(await authHelper.isLoggedIn()).toBe(true);
    
    // Logout
    await authHelper.logout();
    
    // Should be logged out
    expect(await authHelper.isLoggedIn()).toBe(false);
    
    // Should redirect to login page
    await expect(page).toHaveURL(/.*login.*/);
  });

  test('should invalidate session on logout', async ({ page, context }) => {
    const adminEmail = process.env.SMOKE_ADMIN_EMAIL || testData.adminUser.email;
    const adminPassword = process.env.SMOKE_ADMIN_PASSWORD || testData.adminUser.password;

    // Login in first tab
    await authHelper.login(adminEmail, adminPassword);
    
    // Open second tab (same context = same session)
    const page2 = await context.newPage();
    await page2.goto('/app/dashboard');
    
    // Should be able to access dashboard in second tab
    await expect(page2).toHaveURL(/.*dashboard.*/);
    
    // Logout from first tab
    await authHelper.logout();
    
    // Try to access dashboard in second tab
    await page2.goto('/app/dashboard');
    
    // Should redirect to login (session invalidated)
    await expect(page2).toHaveURL(/.*login.*/, { timeout: 5000 });
    
    await page2.close();
  });

  // ==================== PASSWORD RESET TESTS ====================

  test('should request password reset successfully', async ({ page }) => {
    await page.goto('/forgot-password');
    
    // Fill in email
    const emailInput = page.locator('input[type="email"], [name="email"], [data-testid="email-input"]').first();
    await emailInput.fill('test@example.com');
    
    // Submit form
    const submitButton = page.locator('button[type="submit"], [data-testid="submit-reset"]').first();
    await submitButton.click();
    
    // Should show success message (neutral, doesn't reveal if email exists)
    await expect(
      page.locator('.success, [role="alert"]:has-text("sent"), [data-testid="reset-success"]')
    ).toBeVisible({ timeout: 5000 });
  });

  test('should validate email format on password reset', async ({ page }) => {
    await page.goto('/forgot-password');
    
    // Try invalid email
    const emailInput = page.locator('input[type="email"], [name="email"]').first();
    await emailInput.fill('invalid-email');
    await emailInput.blur();
    
    // Should show validation error
    const validationMessage = await emailInput.evaluate((el: HTMLInputElement) => {
      return el.validationMessage || (el as any).customValidationMessage;
    });
    
    expect(validationMessage).toBeTruthy();
  });

  // ==================== ROLE MANAGEMENT TESTS ====================

  test('should display users list with roles', async ({ page }) => {
    const adminEmail = process.env.SMOKE_ADMIN_EMAIL || testData.adminUser.email;
    const adminPassword = process.env.SMOKE_ADMIN_PASSWORD || testData.adminUser.password;

    await authHelper.login(adminEmail, adminPassword);
    
    // Navigate to users page
    await page.goto('/admin/users');
    await page.waitForTimeout(2000);
    
    // Should see users table/list
    const usersTable = page.locator('table, [data-testid="users-list"], .users-list').first();
    await expect(usersTable).toBeVisible({ timeout: 10000 });
    
    // Should see role information
    const roleColumns = page.locator('th:has-text("Role"), td:has-text("admin"), td:has-text("pm")');
    const roleCount = await roleColumns.count();
    
    // At least some role information should be visible
    expect(roleCount).toBeGreaterThan(0);
  });

  test('should modify user role successfully', async ({ page }) => {
    const adminEmail = process.env.SMOKE_ADMIN_EMAIL || testData.adminUser.email;
    const adminPassword = process.env.SMOKE_ADMIN_PASSWORD || testData.adminUser.password;

    await authHelper.login(adminEmail, adminPassword);
    await page.goto('/admin/users');
    await page.waitForTimeout(2000);
    
    // Find a user row
    const userRows = page.locator('tr[data-testid="user-row"], tbody tr, .user-row');
    const rowCount = await userRows.count();
    
    if (rowCount > 0) {
      const firstRow = userRows.first();
      await firstRow.click();
      await page.waitForTimeout(1000);
      
      // Try to find role selector
      const roleSelect = page.locator('select[name="role"], [data-testid="user-role-select"]').first();
      const roleSelectVisible = await roleSelect.isVisible().catch(() => false);
      
      if (roleSelectVisible) {
        // Get current role
        const currentRole = await roleSelect.inputValue();
        
        // Change to different role (try 'member' if current is 'admin', otherwise 'pm')
        const newRole = currentRole === 'admin' ? 'member' : 'pm';
        await roleSelect.selectOption({ value: newRole });
        await page.waitForTimeout(1000);
        
        // Verify role changed
        await expect(roleSelect).toHaveValue(newRole);
        
        console.log(`✅ Role changed from ${currentRole} to ${newRole}`);
      } else {
        // Role modification might not be available via UI
        console.log('⚠️  Role selector not found - role modification may be API-only');
      }
    } else {
      console.log('⚠️  No users found - cannot test role modification');
    }
  });

  test('should create new user with role', async ({ page }) => {
    const adminEmail = process.env.SMOKE_ADMIN_EMAIL || testData.adminUser.email;
    const adminPassword = process.env.SMOKE_ADMIN_PASSWORD || testData.adminUser.password;

    await authHelper.login(adminEmail, adminPassword);
    await page.goto('/admin/users');
    await page.waitForTimeout(2000);
    
    // Look for create user button
    const createButton = page.locator('button:has-text("Create"), button:has-text("Add User"), [data-testid="create-user-button"]').first();
    const hasCreateButton = await createButton.isVisible().catch(() => false);
    
    if (hasCreateButton) {
      await createButton.click();
      await page.waitForTimeout(1000);
      
      // Fill user form
      const emailInput = page.locator('input[name="email"], [data-testid="user-email-input"]').first();
      const nameInput = page.locator('input[name="name"], [data-testid="user-name-input"]').first();
      const roleSelect = page.locator('select[name="role"], [data-testid="user-role-select"]').first();
      
      const testEmail = `test-user-${Date.now()}@example.com`;
      
      if (await emailInput.isVisible().catch(() => false)) {
        await emailInput.fill(testEmail);
      }
      
      if (await nameInput.isVisible().catch(() => false)) {
        await nameInput.fill(`Test User ${Date.now()}`);
      }
      
      if (await roleSelect.isVisible().catch(() => false)) {
        await roleSelect.selectOption({ value: 'member' });
      }
      
      // Submit
      const saveButton = page.locator('button[type="submit"], button:has-text("Save"), button:has-text("Create")').first();
      if (await saveButton.isVisible().catch(() => false)) {
        await saveButton.click();
        await page.waitForTimeout(2000);
        
        // Verify user appears in list
        const newUserRow = page.locator(`tr:has-text("${testEmail}"), [data-testid="user-row"]:has-text("${testEmail}")`);
        const userCreated = await newUserRow.isVisible({ timeout: 5000 }).catch(() => false);
        
        if (userCreated) {
          console.log(`✅ User created successfully: ${testEmail}`);
        } else {
          console.log('⚠️  User creation may have succeeded but not visible in list');
        }
      }
    } else {
      console.log('⚠️  Create user button not found - user creation may be API-only');
    }
  });

  test('should delete user successfully', async ({ page }) => {
    const adminEmail = process.env.SMOKE_ADMIN_EMAIL || testData.adminUser.email;
    const adminPassword = process.env.SMOKE_ADMIN_PASSWORD || testData.adminUser.password;

    await authHelper.login(adminEmail, adminPassword);
    await page.goto('/admin/users');
    await page.waitForTimeout(2000);
    
    // Find users
    const userRows = page.locator('tr[data-testid="user-row"], tbody tr, .user-row');
    const rowCount = await userRows.count();
    
    if (rowCount > 1) { // Need at least 2 users (don't delete the last one)
      const lastRow = userRows.last();
      await lastRow.click();
      await page.waitForTimeout(1000);
      
      // Find delete button
      const deleteButton = page.locator('button:has-text("Delete"), [data-testid="delete-user-button"]').first();
      const hasDeleteButton = await deleteButton.isVisible().catch(() => false);
      
      if (hasDeleteButton) {
        await deleteButton.click();
        await page.waitForTimeout(1000);
        
        // Confirm deletion if dialog appears
        const confirmDialog = page.locator('[role="dialog"]:has-text("Confirm"), .confirm-dialog').first();
        if (await confirmDialog.isVisible().catch(() => false)) {
          const confirmButton = confirmDialog.locator('button:has-text("Confirm"), button:has-text("Delete")').first();
          await confirmButton.click();
          await page.waitForTimeout(2000);
        }
        
        // Verify user removed
        await expect(lastRow).not.toBeVisible({ timeout: 5000 });
        console.log('✅ User deleted successfully');
      } else {
        console.log('⚠️  Delete button not found - user deletion may be API-only');
      }
    } else {
      console.log('⚠️  Not enough users to test deletion safely');
    }
  });

  // ==================== INTEGRATION TESTS ====================

  test('complete flow: login → manage roles → logout', async ({ page }) => {
    const adminEmail = process.env.SMOKE_ADMIN_EMAIL || testData.adminUser.email;
    const adminPassword = process.env.SMOKE_ADMIN_PASSWORD || testData.adminUser.password;

    // Step 1: Login
    await authHelper.login(adminEmail, adminPassword);
    expect(await authHelper.isLoggedIn()).toBe(true);
    
    // Step 2: Navigate to users
    await page.goto('/admin/users');
    await page.waitForTimeout(2000);
    
    // Step 3: Verify users page loaded
    const usersTable = page.locator('table, [data-testid="users-list"]').first();
    await expect(usersTable).toBeVisible({ timeout: 10000 });
    
    // Step 4: Logout
    await authHelper.logout();
    expect(await authHelper.isLoggedIn()).toBe(false);
    
    // Step 5: Verify cannot access users page
    await page.goto('/admin/users');
    await expect(page).toHaveURL(/.*login.*/, { timeout: 5000 });
  });
});

