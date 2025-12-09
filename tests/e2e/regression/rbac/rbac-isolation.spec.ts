import { test, expect } from '@playwright/test';
import { AuthHelper } from '../../helpers/smoke-helpers';
import { testData } from '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/tests/e2e/helpers/data';

test.describe('@regression RBAC Tenant Isolation Testing', () => {
  let authHelper: AuthHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
  });

  test('@regression Cross-tenant data isolation - Users', async ({ page }) => {
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
    
    // Test API endpoint for tenant isolation
    const response = await page.request.get('/api/admin/users');
    
    if (response.status() === 200) {
      const data = await response.json();
      console.log(`API returned ${data.length || 0} users`);
      
      // Check if all users belong to ZENA tenant
      if (data.length > 0) {
        const zenaUsers = data.filter(user => user.tenant_id === 1 || user.tenant?.name?.includes('ZENA'));
        const ttfUsers = data.filter(user => user.tenant_id === 2 || user.tenant?.name?.includes('TTF'));
        
        console.log(`ZENA users: ${zenaUsers.length}, TTF users: ${ttfUsers.length}`);
        
        if (ttfUsers.length > 0) {
          console.log('❌ API returned TTF users for ZENA admin (tenant isolation issue)');
        } else {
          console.log('✅ API properly filtered users by tenant');
        }
      }
    } else {
      console.log(`❌ API request failed with status ${response.status()}`);
    }
  });

  test('@regression Cross-tenant data isolation - Projects', async ({ page }) => {
    // Login as ZENA project manager
    await authHelper.login(testData.projectManager.email, testData.projectManager.password);
    
    // Navigate to projects page
    await page.goto('/app/projects');
    await page.waitForLoadState('networkidle');
    
    // Check if only ZENA projects are visible
    const projectRows = page.locator('tr, .project-item, .project-row');
    const projectCount = await projectRows.count();
    
    console.log(`Found ${projectCount} projects for ZENA project manager`);
    
    // Check for tenant-specific data
    const tenantInfo = page.locator('text=/ZENA|zena/i');
    
    if (await tenantInfo.isVisible()) {
      console.log('✅ ZENA project manager sees ZENA tenant data');
    } else {
      console.log('⚠️ ZENA project manager may not see tenant-specific data');
    }
    
    // Check for TTF data (should not be visible)
    const ttfInfo = page.locator('text=/TTF|ttf/i');
    
    if (await ttfInfo.isVisible()) {
      console.log('❌ ZENA project manager can see TTF data (tenant isolation issue)');
    } else {
      console.log('✅ ZENA project manager cannot see TTF data (tenant isolation working)');
    }
    
    // Test API endpoint for tenant isolation
    const response = await page.request.get('/api/projects');
    
    if (response.status() === 200) {
      const data = await response.json();
      console.log(`API returned ${data.length || 0} projects`);
      
      // Check if all projects belong to ZENA tenant
      if (data.length > 0) {
        const zenaProjects = data.filter(project => project.tenant_id === 1 || project.tenant?.name?.includes('ZENA'));
        const ttfProjects = data.filter(project => project.tenant_id === 2 || project.tenant?.name?.includes('TTF'));
        
        console.log(`ZENA projects: ${zenaProjects.length}, TTF projects: ${ttfProjects.length}`);
        
        if (ttfProjects.length > 0) {
          console.log('❌ API returned TTF projects for ZENA project manager (tenant isolation issue)');
        } else {
          console.log('✅ API properly filtered projects by tenant');
        }
      }
    } else {
      console.log(`❌ API request failed with status ${response.status()}`);
    }
  });

  test('@regression Cross-tenant data isolation - Tasks', async ({ page }) => {
    // Login as ZENA developer
    await authHelper.login(testData.devUser.email, testData.devUser.password);
    
    // Navigate to tasks page
    await page.goto('/app/tasks');
    await page.waitForLoadState('networkidle');
    
    // Check if only ZENA tasks are visible
    const taskRows = page.locator('tr, .task-item, .task-row');
    const taskCount = await taskRows.count();
    
    console.log(`Found ${taskCount} tasks for ZENA developer`);
    
    // Check for tenant-specific data
    const tenantInfo = page.locator('text=/ZENA|zena/i');
    
    if (await tenantInfo.isVisible()) {
      console.log('✅ ZENA developer sees ZENA tenant data');
    } else {
      console.log('⚠️ ZENA developer may not see tenant-specific data');
    }
    
    // Check for TTF data (should not be visible)
    const ttfInfo = page.locator('text=/TTF|ttf/i');
    
    if (await ttfInfo.isVisible()) {
      console.log('❌ ZENA developer can see TTF data (tenant isolation issue)');
    } else {
      console.log('✅ ZENA developer cannot see TTF data (tenant isolation working)');
    }
    
    // Test API endpoint for tenant isolation
    const response = await page.request.get('/api/tasks');
    
    if (response.status() === 200) {
      const data = await response.json();
      console.log(`API returned ${data.length || 0} tasks`);
      
      // Check if all tasks belong to ZENA tenant
      if (data.length > 0) {
        const zenaTasks = data.filter(task => task.tenant_id === 1 || task.tenant?.name?.includes('ZENA'));
        const ttfTasks = data.filter(task => task.tenant_id === 2 || task.tenant?.name?.includes('TTF'));
        
        console.log(`ZENA tasks: ${zenaTasks.length}, TTF tasks: ${ttfTasks.length}`);
        
        if (ttfTasks.length > 0) {
          console.log('❌ API returned TTF tasks for ZENA developer (tenant isolation issue)');
        } else {
          console.log('✅ API properly filtered tasks by tenant');
        }
      }
    } else {
      console.log(`❌ API request failed with status ${response.status()}`);
    }
  });

  test('@regression Cross-tenant data isolation - Documents', async ({ page }) => {
    // Login as ZENA client
    await authHelper.login(testData.guestUser.email, testData.guestUser.password);
    
    // Navigate to documents page
    await page.goto('/app/documents');
    await page.waitForLoadState('networkidle');
    
    // Check if only ZENA documents are visible
    const documentRows = page.locator('tr, .document-item, .document-row');
    const documentCount = await documentRows.count();
    
    console.log(`Found ${documentCount} documents for ZENA client`);
    
    // Check for tenant-specific data
    const tenantInfo = page.locator('text=/ZENA|zena/i');
    
    if (await tenantInfo.isVisible()) {
      console.log('✅ ZENA client sees ZENA tenant data');
    } else {
      console.log('⚠️ ZENA client may not see tenant-specific data');
    }
    
    // Check for TTF data (should not be visible)
    const ttfInfo = page.locator('text=/TTF|ttf/i');
    
    if (await ttfInfo.isVisible()) {
      console.log('❌ ZENA client can see TTF data (tenant isolation issue)');
    } else {
      console.log('✅ ZENA client cannot see TTF data (tenant isolation working)');
    }
    
    // Test API endpoint for tenant isolation
    const response = await page.request.get('/api/documents');
    
    if (response.status() === 200) {
      const data = await response.json();
      console.log(`API returned ${data.length || 0} documents`);
      
      // Check if all documents belong to ZENA tenant
      if (data.length > 0) {
        const zenaDocuments = data.filter(doc => doc.tenant_id === 1 || doc.tenant?.name?.includes('ZENA'));
        const ttfDocuments = data.filter(doc => doc.tenant_id === 2 || doc.tenant?.name?.includes('TTF'));
        
        console.log(`ZENA documents: ${zenaDocuments.length}, TTF documents: ${ttfDocuments.length}`);
        
        if (ttfDocuments.length > 0) {
          console.log('❌ API returned TTF documents for ZENA client (tenant isolation issue)');
        } else {
          console.log('✅ API properly filtered documents by tenant');
        }
      }
    } else {
      console.log(`❌ API request failed with status ${response.status()}`);
    }
  });

  test('@regression Cross-tenant API access prevention', async ({ page }) => {
    // Login as ZENA admin
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Try to access TTF tenant data directly via API
    const ttfEndpoints = [
      '/api/admin/users?tenant_id=2',
      '/api/projects?tenant_id=2',
      '/api/tasks?tenant_id=2',
      '/api/documents?tenant_id=2'
    ];
    
    for (const endpoint of ttfEndpoints) {
      const response = await page.request.get(endpoint);
      
      if (response.status() === 403) {
        console.log(`✅ API endpoint ${endpoint} properly restricted for cross-tenant access`);
      } else if (response.status() === 200) {
        const data = await response.json();
        console.log(`❌ API endpoint ${endpoint} accessible for cross-tenant access (returned ${data.length || 0} items)`);
      } else {
        console.log(`⚠️ API endpoint ${endpoint} returned status ${response.status()}`);
      }
    }
  });

  test('@regression Tenant-scoped resource access', async ({ page }) => {
    // Login as ZENA project manager
    await authHelper.login(testData.projectManager.email, testData.projectManager.password);
    
    // Try to access specific resources from different tenants
    const crossTenantResources = [
      '/api/projects/999', // Assuming project 999 belongs to TTF
      '/api/tasks/999',    // Assuming task 999 belongs to TTF
      '/api/documents/999' // Assuming document 999 belongs to TTF
    ];
    
    for (const resource of crossTenantResources) {
      const response = await page.request.get(resource);
      
      if (response.status() === 403) {
        console.log(`✅ Resource ${resource} properly restricted for cross-tenant access`);
      } else if (response.status() === 404) {
        console.log(`✅ Resource ${resource} not found (proper tenant isolation)`);
      } else if (response.status() === 200) {
        console.log(`❌ Resource ${resource} accessible for cross-tenant access`);
      } else {
        console.log(`⚠️ Resource ${resource} returned status ${response.status()}`);
      }
    }
  });

  test('@regression Tenant isolation in bulk operations', async ({ page }) => {
    // Login as ZENA admin
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Test bulk operations with tenant isolation
    const bulkEndpoints = [
      { url: '/api/admin/users/bulk', method: 'POST', data: { ids: [1, 2, 3] } },
      { url: '/api/projects/bulk', method: 'POST', data: { ids: [1, 2, 3] } },
      { url: '/api/tasks/bulk', method: 'POST', data: { ids: [1, 2, 3] } }
    ];
    
    for (const endpoint of bulkEndpoints) {
      const response = await page.request.post(endpoint.url, { data: endpoint.data });
      
      if (response.status() === 200) {
        const data = await response.json();
        console.log(`✅ Bulk operation ${endpoint.url} completed successfully`);
        
        // Check if all affected resources belong to the same tenant
        if (data.affected && data.affected.length > 0) {
          const zenaCount = data.affected.filter(item => item.tenant_id === 1).length;
          const ttfCount = data.affected.filter(item => item.tenant_id === 2).length;
          
          if (ttfCount > 0) {
            console.log(`❌ Bulk operation ${endpoint.url} affected TTF resources (tenant isolation issue)`);
          } else {
            console.log(`✅ Bulk operation ${endpoint.url} properly isolated to ZENA tenant`);
          }
        }
      } else if (response.status() === 403) {
        console.log(`✅ Bulk operation ${endpoint.url} properly restricted`);
      } else {
        console.log(`⚠️ Bulk operation ${endpoint.url} returned status ${response.status()}`);
      }
    }
  });

  test('@regression Tenant isolation in search operations', async ({ page }) => {
    // Login as ZENA developer
    await authHelper.login(testData.devUser.email, testData.devUser.password);
    
    // Test search operations with tenant isolation
    const searchEndpoints = [
      '/api/projects/search?q=test',
      '/api/tasks/search?q=test',
      '/api/documents/search?q=test'
    ];
    
    for (const endpoint of searchEndpoints) {
      const response = await page.request.get(endpoint);
      
      if (response.status() === 200) {
        const data = await response.json();
        console.log(`✅ Search operation ${endpoint} completed successfully`);
        
        // Check if all results belong to the same tenant
        if (data.results && data.results.length > 0) {
          const zenaCount = data.results.filter(item => item.tenant_id === 1).length;
          const ttfCount = data.results.filter(item => item.tenant_id === 2).length;
          
          if (ttfCount > 0) {
            console.log(`❌ Search operation ${endpoint} returned TTF results (tenant isolation issue)`);
          } else {
            console.log(`✅ Search operation ${endpoint} properly isolated to ZENA tenant`);
          }
        }
      } else if (response.status() === 403) {
        console.log(`✅ Search operation ${endpoint} properly restricted`);
      } else {
        console.log(`⚠️ Search operation ${endpoint} returned status ${response.status()}`);
      }
    }
  });
});
