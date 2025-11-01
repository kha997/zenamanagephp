import { test, expect } from '@playwright/test';
import { AuthHelper } from '../../helpers/smoke-helpers';
import { testData } from '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/tests/e2e/helpers/data';

test.describe('@regression RBAC Comprehensive Testing', () => {
  let authHelper: AuthHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
  });

  test('@regression RBAC Matrix - Super Admin permissions', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Test Super Admin access to all modules
    const modules = [
      { url: '/admin/users', name: 'Admin Users' },
      { url: '/admin/tenants', name: 'Admin Tenants' },
      { url: '/admin/dashboard', name: 'Admin Dashboard' },
      { url: '/app/projects', name: 'Projects' },
      { url: '/app/tasks', name: 'Tasks' },
      { url: '/app/documents', name: 'Documents' },
      { url: '/app/alerts', name: 'Alerts' }
    ];
    
    for (const module of modules) {
      await page.goto(module.url);
      await page.waitForLoadState('networkidle');
      
      // Check if page loaded successfully
      const currentUrl = page.url();
      
      if (currentUrl.includes('login')) {
        console.log(`❌ Super Admin denied access to ${module.name}`);
      } else {
        console.log(`✅ Super Admin has access to ${module.name}`);
        
        // Check for admin-specific actions
        const adminActions = page.locator('button:has-text("Create"), button:has-text("Add"), button:has-text("Delete"), button:has-text("Edit")');
        const actionCount = await adminActions.count();
        
        if (actionCount > 0) {
          console.log(`✅ Super Admin can perform ${actionCount} actions in ${module.name}`);
        }
      }
    }
  });

  test('@regression RBAC Matrix - Project Manager permissions', async ({ page }) => {
    await authHelper.login(testData.projectManager.email, testData.projectManager.password);
    
    // Test Project Manager access
    const allowedModules = [
      { url: '/app/projects', name: 'Projects', shouldHaveAccess: true },
      { url: '/app/tasks', name: 'Tasks', shouldHaveAccess: true },
      { url: '/app/documents', name: 'Documents', shouldHaveAccess: true },
      { url: '/app/alerts', name: 'Alerts', shouldHaveAccess: true }
    ];
    
    const restrictedModules = [
      { url: '/admin/users', name: 'Admin Users', shouldHaveAccess: false },
      { url: '/admin/tenants', name: 'Admin Tenants', shouldHaveAccess: false },
      { url: '/admin/dashboard', name: 'Admin Dashboard', shouldHaveAccess: false }
    ];
    
    // Test allowed modules
    for (const module of allowedModules) {
      await page.goto(module.url);
      await page.waitForLoadState('networkidle');
      
      const currentUrl = page.url();
      
      if (currentUrl.includes('login') || currentUrl.includes('403') || currentUrl.includes('unauthorized')) {
        console.log(`❌ Project Manager denied access to ${module.name} (should have access)`);
      } else {
        console.log(`✅ Project Manager has access to ${module.name}`);
        
        // Check for appropriate actions
        const createButton = page.locator('button:has-text("Create"), button:has-text("Add"), button:has-text("New")');
        
        if (await createButton.isVisible()) {
          console.log(`✅ Project Manager can create items in ${module.name}`);
        } else {
          console.log(`⚠️ Project Manager cannot create items in ${module.name}`);
        }
      }
    }
    
    // Test restricted modules
    for (const module of restrictedModules) {
      await page.goto(module.url);
      await page.waitForLoadState('networkidle');
      
      const currentUrl = page.url();
      
      if (currentUrl.includes('login') || currentUrl.includes('403') || currentUrl.includes('unauthorized')) {
        console.log(`✅ Project Manager properly denied access to ${module.name}`);
      } else {
        console.log(`❌ Project Manager has unauthorized access to ${module.name}`);
      }
    }
  });

  test('@regression RBAC Matrix - Developer permissions', async ({ page }) => {
    await authHelper.login(testData.developer.email, testData.developer.password);
    
    // Test Developer access (should be read-only for most modules)
    const modules = [
      { url: '/app/projects', name: 'Projects', canCreate: false },
      { url: '/app/tasks', name: 'Tasks', canCreate: true },
      { url: '/app/documents', name: 'Documents', canCreate: false },
      { url: '/app/alerts', name: 'Alerts', canCreate: false }
    ];
    
    for (const module of modules) {
      await page.goto(module.url);
      await page.waitForLoadState('networkidle');
      
      const currentUrl = page.url();
      
      if (currentUrl.includes('login') || currentUrl.includes('403') || currentUrl.includes('unauthorized')) {
        console.log(`❌ Developer denied access to ${module.name}`);
      } else {
        console.log(`✅ Developer has access to ${module.name}`);
        
        // Check for create button
        const createButton = page.locator('button:has-text("Create"), button:has-text("Add"), button:has-text("New")');
        
        if (await createButton.isVisible()) {
          if (module.canCreate) {
            console.log(`✅ Developer can create items in ${module.name} (expected)`);
          } else {
            console.log(`❌ Developer can create items in ${module.name} (should be read-only)`);
          }
        } else {
          if (module.canCreate) {
            console.log(`⚠️ Developer cannot create items in ${module.name} (should be able to)`);
          } else {
            console.log(`✅ Developer cannot create items in ${module.name} (correct for read-only)`);
          }
        }
      }
    }
  });

  test('@regression RBAC Matrix - Client permissions', async ({ page }) => {
    await authHelper.login(testData.client.email, testData.client.password);
    
    // Test Client access (should be read-only)
    const modules = [
      { url: '/app/projects', name: 'Projects', shouldHaveAccess: true },
      { url: '/app/tasks', name: 'Tasks', shouldHaveAccess: true },
      { url: '/app/documents', name: 'Documents', shouldHaveAccess: true },
      { url: '/app/alerts', name: 'Alerts', shouldHaveAccess: true }
    ];
    
    for (const module of modules) {
      await page.goto(module.url);
      await page.waitForLoadState('networkidle');
      
      const currentUrl = page.url();
      
      if (currentUrl.includes('login') || currentUrl.includes('403') || currentUrl.includes('unauthorized')) {
        console.log(`❌ Client denied access to ${module.name}`);
      } else {
        console.log(`✅ Client has access to ${module.name}`);
        
        // Check for create/edit/delete buttons (should not be visible)
        const createButton = page.locator('button:has-text("Create"), button:has-text("Add"), button:has-text("New")');
        const editButton = page.locator('button:has-text("Edit"), button:has-text("Update"), button:has-text("Modify")');
        const deleteButton = page.locator('button:has-text("Delete"), button:has-text("Remove"), button:has-text("Destroy")');
        
        if (await createButton.isVisible()) {
          console.log(`❌ Client can create items in ${module.name} (should be read-only)`);
        } else {
          console.log(`✅ Client cannot create items in ${module.name} (correct for read-only)`);
        }
        
        if (await editButton.isVisible()) {
          console.log(`❌ Client can edit items in ${module.name} (should be read-only)`);
        } else {
          console.log(`✅ Client cannot edit items in ${module.name} (correct for read-only)`);
        }
        
        if (await deleteButton.isVisible()) {
          console.log(`❌ Client can delete items in ${module.name} (should be read-only)`);
        } else {
          console.log(`✅ Client cannot delete items in ${module.name} (correct for read-only)`);
        }
      }
    }
  });

  test('@regression RBAC Matrix - Guest permissions', async ({ page }) => {
    await authHelper.login(testData.guest.email, testData.guest.password);
    
    // Test Guest access (should be very limited)
    const modules = [
      { url: '/app/projects', name: 'Projects', shouldHaveAccess: false },
      { url: '/app/tasks', name: 'Tasks', shouldHaveAccess: false },
      { url: '/app/documents', name: 'Documents', shouldHaveAccess: false },
      { url: '/app/alerts', name: 'Alerts', shouldHaveAccess: false }
    ];
    
    for (const module of modules) {
      await page.goto(module.url);
      await page.waitForLoadState('networkidle');
      
      const currentUrl = page.url();
      
      if (currentUrl.includes('login') || currentUrl.includes('403') || currentUrl.includes('unauthorized')) {
        console.log(`✅ Guest properly denied access to ${module.name}`);
      } else {
        console.log(`❌ Guest has unauthorized access to ${module.name}`);
      }
    }
  });

  test('@regression Cross-tenant permission isolation', async ({ page }) => {
    // Login as ZENA admin
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to users page
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Check if only ZENA users are visible
    const userRows = page.locator('tr, .user-item, .user-row');
    const userCount = await userRows.count();
    
    console.log(`Found ${userCount} users for ZENA admin`);
    
    // Check for tenant-specific data
    const tenantInfo = page.locator('text=/ZENA|zena/i');
    
    if (await tenantInfo.isVisible()) {
      console.log('✅ ZENA admin sees ZENA tenant data');
    } else {
      console.log('⚠️ ZENA admin may not see tenant-specific data');
    }
    
    // Check for TTF data (should not be visible)
    const ttfInfo = page.locator('text=/TTF|ttf/i');
    
    if (await ttfInfo.isVisible()) {
      console.log('❌ ZENA admin can see TTF data (tenant isolation issue)');
    } else {
      console.log('✅ ZENA admin cannot see TTF data (tenant isolation working)');
    }
  });

  test('@regression API endpoint authorization', async ({ page }) => {
    await authHelper.login(testData.developer.email, testData.developer.password);
    
    // Test API endpoints that should be restricted for developers
    const restrictedEndpoints = [
      '/api/admin/users',
      '/api/admin/tenants',
      '/api/admin/dashboard'
    ];
    
    for (const endpoint of restrictedEndpoints) {
      const response = await page.request.get(endpoint);
      
      if (response.status() === 403 || response.status() === 401) {
        console.log(`✅ API endpoint ${endpoint} properly restricted for developers`);
      } else if (response.status() === 200) {
        console.log(`❌ API endpoint ${endpoint} accessible to developers (should be restricted)`);
      } else {
        console.log(`⚠️ API endpoint ${endpoint} returned status ${response.status()}`);
      }
    }
    
    // Test API endpoints that should be accessible to developers
    const allowedEndpoints = [
      '/api/projects',
      '/api/tasks',
      '/api/documents'
    ];
    
    for (const endpoint of allowedEndpoints) {
      const response = await page.request.get(endpoint);
      
      if (response.status() === 200) {
        console.log(`✅ API endpoint ${endpoint} accessible to developers`);
      } else if (response.status() === 403 || response.status() === 401) {
        console.log(`❌ API endpoint ${endpoint} restricted for developers (should be accessible)`);
      } else {
        console.log(`⚠️ API endpoint ${endpoint} returned status ${response.status()}`);
      }
    }
  });

  test('@regression UI element visibility based on roles', async ({ page }) => {
    const roles = [
      { email: testData.adminUser.email, password: testData.adminUser.password, role: 'Super Admin' },
      { email: testData.projectManager.email, password: testData.projectManager.password, role: 'Project Manager' },
      { email: testData.developer.email, password: testData.developer.password, role: 'Developer' },
      { email: testData.client.email, password: testData.client.password, role: 'Client' }
    ];
    
    for (const user of roles) {
      await authHelper.login(user.email, user.password);
      
      // Navigate to projects page
      await page.goto('/app/projects');
      await page.waitForLoadState('networkidle');
      
      // Check for role-specific UI elements
      const createButton = page.locator('button:has-text("Create"), button:has-text("Add"), button:has-text("New")');
      const editButton = page.locator('button:has-text("Edit"), button:has-text("Update")');
      const deleteButton = page.locator('button:has-text("Delete"), button:has-text("Remove")');
      const adminButton = page.locator('button:has-text("Admin"), button:has-text("Settings")');
      
      console.log(`\n--- ${user.role} UI Elements ---`);
      
      if (await createButton.isVisible()) {
        console.log(`✅ ${user.role} can see create button`);
      } else {
        console.log(`❌ ${user.role} cannot see create button`);
      }
      
      if (await editButton.isVisible()) {
        console.log(`✅ ${user.role} can see edit button`);
      } else {
        console.log(`❌ ${user.role} cannot see edit button`);
      }
      
      if (await deleteButton.isVisible()) {
        console.log(`✅ ${user.role} can see delete button`);
      } else {
        console.log(`❌ ${user.role} cannot see delete button`);
      }
      
      if (await adminButton.isVisible()) {
        console.log(`✅ ${user.role} can see admin button`);
      } else {
        console.log(`❌ ${user.role} cannot see admin button`);
      }
    }
  });

  test('@regression Permission inheritance and delegation', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to user management
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Look for permission management interface
    const permissionInterface = page.locator('.permissions, .role-management, .access-control');
    
    if (await permissionInterface.isVisible()) {
      console.log('✅ Permission management interface found');
      
      // Look for role assignment
      const roleAssignment = page.locator('select[name="role"], .role-selector, .permission-selector');
      
      if (await roleAssignment.isVisible()) {
        console.log('✅ Role assignment interface found');
        
        // Test role change
        const roleOptions = page.locator('option, .role-option');
        const roleCount = await roleOptions.count();
        
        console.log(`Found ${roleCount} role options`);
        
        // Check for permission inheritance
        const permissionInheritance = page.locator('text=/inherit|delegate|permission.*chain/i');
        
        if (await permissionInheritance.isVisible()) {
          console.log('✅ Permission inheritance interface found');
        } else {
          console.log('⚠️ Permission inheritance interface not found');
        }
        
      } else {
        console.log('⚠️ Role assignment interface not found');
      }
      
    } else {
      console.log('⚠️ Permission management interface not found - may need to implement');
    }
  });
});
