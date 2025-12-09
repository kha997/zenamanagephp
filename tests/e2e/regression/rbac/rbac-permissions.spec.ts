import { test, expect } from '@playwright/test';
import { AuthHelper } from '../../helpers/smoke-helpers';
import { testData } from '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/tests/e2e/helpers/data';

test.describe('@regression RBAC Permissions Testing', () => {
  let authHelper: AuthHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
  });

  test('@regression API endpoint authorization - Super Admin', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Test all API endpoints for Super Admin
    const endpoints = [
      { url: '/api/admin/users', method: 'GET', expectedStatus: 200, description: 'Admin Users List' },
      { url: '/api/admin/tenants', method: 'GET', expectedStatus: 200, description: 'Admin Tenants List' },
      { url: '/api/admin/dashboard', method: 'GET', expectedStatus: 200, description: 'Admin Dashboard' },
      { url: '/api/projects', method: 'GET', expectedStatus: 200, description: 'Projects List' },
      { url: '/api/projects', method: 'POST', expectedStatus: 200, description: 'Create Project' },
      { url: '/api/tasks', method: 'GET', expectedStatus: 200, description: 'Tasks List' },
      { url: '/api/tasks', method: 'POST', expectedStatus: 200, description: 'Create Task' },
      { url: '/api/documents', method: 'GET', expectedStatus: 200, description: 'Documents List' },
      { url: '/api/documents', method: 'POST', expectedStatus: 200, description: 'Upload Document' },
      { url: '/api/users', method: 'GET', expectedStatus: 200, description: 'Users List' },
      { url: '/api/users', method: 'POST', expectedStatus: 200, description: 'Create User' }
    ];
    
    for (const endpoint of endpoints) {
      let response;
      
      if (endpoint.method === 'GET') {
        response = await page.request.get(endpoint.url);
      } else if (endpoint.method === 'POST') {
        response = await page.request.post(endpoint.url, { data: {} });
      }
      
      if (response.status() === endpoint.expectedStatus) {
        console.log(`✅ Super Admin: ${endpoint.description} - Status ${response.status()}`);
      } else {
        console.log(`❌ Super Admin: ${endpoint.description} - Expected ${endpoint.expectedStatus}, got ${response.status()}`);
      }
    }
  });

  test('@regression API endpoint authorization - Project Manager', async ({ page }) => {
    await authHelper.login(testData.projectManager.email, testData.projectManager.password);
    
    // Test API endpoints for Project Manager
    const allowedEndpoints = [
      { url: '/api/projects', method: 'GET', expectedStatus: 200, description: 'Projects List' },
      { url: '/api/projects', method: 'POST', expectedStatus: 200, description: 'Create Project' },
      { url: '/api/tasks', method: 'GET', expectedStatus: 200, description: 'Tasks List' },
      { url: '/api/tasks', method: 'POST', expectedStatus: 200, description: 'Create Task' },
      { url: '/api/documents', method: 'GET', expectedStatus: 200, description: 'Documents List' },
      { url: '/api/documents', method: 'POST', expectedStatus: 200, description: 'Upload Document' }
    ];
    
    const restrictedEndpoints = [
      { url: '/api/admin/users', method: 'GET', expectedStatus: 403, description: 'Admin Users List' },
      { url: '/api/admin/tenants', method: 'GET', expectedStatus: 403, description: 'Admin Tenants List' },
      { url: '/api/admin/dashboard', method: 'GET', expectedStatus: 403, description: 'Admin Dashboard' },
      { url: '/api/users', method: 'POST', expectedStatus: 403, description: 'Create User' }
    ];
    
    // Test allowed endpoints
    for (const endpoint of allowedEndpoints) {
      let response;
      
      if (endpoint.method === 'GET') {
        response = await page.request.get(endpoint.url);
      } else if (endpoint.method === 'POST') {
        response = await page.request.post(endpoint.url, { data: {} });
      }
      
      if (response.status() === endpoint.expectedStatus) {
        console.log(`✅ Project Manager: ${endpoint.description} - Status ${response.status()}`);
      } else {
        console.log(`❌ Project Manager: ${endpoint.description} - Expected ${endpoint.expectedStatus}, got ${response.status()}`);
      }
    }
    
    // Test restricted endpoints
    for (const endpoint of restrictedEndpoints) {
      let response;
      
      if (endpoint.method === 'GET') {
        response = await page.request.get(endpoint.url);
      } else if (endpoint.method === 'POST') {
        response = await page.request.post(endpoint.url, { data: {} });
      }
      
      if (response.status() === endpoint.expectedStatus) {
        console.log(`✅ Project Manager: ${endpoint.description} - Properly restricted (Status ${response.status()})`);
      } else {
        console.log(`❌ Project Manager: ${endpoint.description} - Should be restricted, got ${response.status()}`);
      }
    }
  });

  test('@regression API endpoint authorization - Developer', async ({ page }) => {
    await authHelper.login(testData.devUser.email, testData.devUser.password);
    
    // Test API endpoints for Developer
    const allowedEndpoints = [
      { url: '/api/projects', method: 'GET', expectedStatus: 200, description: 'Projects List' },
      { url: '/api/tasks', method: 'GET', expectedStatus: 200, description: 'Tasks List' },
      { url: '/api/tasks', method: 'POST', expectedStatus: 200, description: 'Create Task' },
      { url: '/api/documents', method: 'GET', expectedStatus: 200, description: 'Documents List' },
      { url: '/api/documents', method: 'POST', expectedStatus: 200, description: 'Upload Document' }
    ];
    
    const restrictedEndpoints = [
      { url: '/api/admin/users', method: 'GET', expectedStatus: 403, description: 'Admin Users List' },
      { url: '/api/admin/tenants', method: 'GET', expectedStatus: 403, description: 'Admin Tenants List' },
      { url: '/api/admin/dashboard', method: 'GET', expectedStatus: 403, description: 'Admin Dashboard' },
      { url: '/api/projects', method: 'POST', expectedStatus: 403, description: 'Create Project' },
      { url: '/api/users', method: 'POST', expectedStatus: 403, description: 'Create User' }
    ];
    
    // Test allowed endpoints
    for (const endpoint of allowedEndpoints) {
      let response;
      
      if (endpoint.method === 'GET') {
        response = await page.request.get(endpoint.url);
      } else if (endpoint.method === 'POST') {
        response = await page.request.post(endpoint.url, { data: {} });
      }
      
      if (response.status() === endpoint.expectedStatus) {
        console.log(`✅ Developer: ${endpoint.description} - Status ${response.status()}`);
      } else {
        console.log(`❌ Developer: ${endpoint.description} - Expected ${endpoint.expectedStatus}, got ${response.status()}`);
      }
    }
    
    // Test restricted endpoints
    for (const endpoint of restrictedEndpoints) {
      let response;
      
      if (endpoint.method === 'GET') {
        response = await page.request.get(endpoint.url);
      } else if (endpoint.method === 'POST') {
        response = await page.request.post(endpoint.url, { data: {} });
      }
      
      if (response.status() === endpoint.expectedStatus) {
        console.log(`✅ Developer: ${endpoint.description} - Properly restricted (Status ${response.status()})`);
      } else {
        console.log(`❌ Developer: ${endpoint.description} - Should be restricted, got ${response.status()}`);
      }
    }
  });

  test('@regression API endpoint authorization - Client', async ({ page }) => {
    await authHelper.login(testData.guestUser.email, testData.guestUser.password);
    
    // Test API endpoints for Client (should be read-only)
    const allowedEndpoints = [
      { url: '/api/projects', method: 'GET', expectedStatus: 200, description: 'Projects List' },
      { url: '/api/tasks', method: 'GET', expectedStatus: 200, description: 'Tasks List' },
      { url: '/api/documents', method: 'GET', expectedStatus: 200, description: 'Documents List' }
    ];
    
    const restrictedEndpoints = [
      { url: '/api/admin/users', method: 'GET', expectedStatus: 403, description: 'Admin Users List' },
      { url: '/api/admin/tenants', method: 'GET', expectedStatus: 403, description: 'Admin Tenants List' },
      { url: '/api/admin/dashboard', method: 'GET', expectedStatus: 403, description: 'Admin Dashboard' },
      { url: '/api/projects', method: 'POST', expectedStatus: 403, description: 'Create Project' },
      { url: '/api/tasks', method: 'POST', expectedStatus: 403, description: 'Create Task' },
      { url: '/api/documents', method: 'POST', expectedStatus: 403, description: 'Upload Document' },
      { url: '/api/users', method: 'POST', expectedStatus: 403, description: 'Create User' }
    ];
    
    // Test allowed endpoints
    for (const endpoint of allowedEndpoints) {
      let response;
      
      if (endpoint.method === 'GET') {
        response = await page.request.get(endpoint.url);
      } else if (endpoint.method === 'POST') {
        response = await page.request.post(endpoint.url, { data: {} });
      }
      
      if (response.status() === endpoint.expectedStatus) {
        console.log(`✅ Client: ${endpoint.description} - Status ${response.status()}`);
      } else {
        console.log(`❌ Client: ${endpoint.description} - Expected ${endpoint.expectedStatus}, got ${response.status()}`);
      }
    }
    
    // Test restricted endpoints
    for (const endpoint of restrictedEndpoints) {
      let response;
      
      if (endpoint.method === 'GET') {
        response = await page.request.get(endpoint.url);
      } else if (endpoint.method === 'POST') {
        response = await page.request.post(endpoint.url, { data: {} });
      }
      
      if (response.status() === endpoint.expectedStatus) {
        console.log(`✅ Client: ${endpoint.description} - Properly restricted (Status ${response.status()})`);
      } else {
        console.log(`❌ Client: ${endpoint.description} - Should be restricted, got ${response.status()}`);
      }
    }
  });

  test('@regression API endpoint authorization - Guest', async ({ page }) => {
    await authHelper.login(testData.guestUser.email, testData.guestUser.password);
    
    // Test API endpoints for Guest (should be very limited)
    const restrictedEndpoints = [
      { url: '/api/admin/users', method: 'GET', expectedStatus: 403, description: 'Admin Users List' },
      { url: '/api/admin/tenants', method: 'GET', expectedStatus: 403, description: 'Admin Tenants List' },
      { url: '/api/admin/dashboard', method: 'GET', expectedStatus: 403, description: 'Admin Dashboard' },
      { url: '/api/projects', method: 'GET', expectedStatus: 403, description: 'Projects List' },
      { url: '/api/projects', method: 'POST', expectedStatus: 403, description: 'Create Project' },
      { url: '/api/tasks', method: 'GET', expectedStatus: 403, description: 'Tasks List' },
      { url: '/api/tasks', method: 'POST', expectedStatus: 403, description: 'Create Task' },
      { url: '/api/documents', method: 'GET', expectedStatus: 403, description: 'Documents List' },
      { url: '/api/documents', method: 'POST', expectedStatus: 403, description: 'Upload Document' },
      { url: '/api/users', method: 'GET', expectedStatus: 403, description: 'Users List' },
      { url: '/api/users', method: 'POST', expectedStatus: 403, description: 'Create User' }
    ];
    
    // Test restricted endpoints
    for (const endpoint of restrictedEndpoints) {
      let response;
      
      if (endpoint.method === 'GET') {
        response = await page.request.get(endpoint.url);
      } else if (endpoint.method === 'POST') {
        response = await page.request.post(endpoint.url, { data: {} });
      }
      
      if (response.status() === endpoint.expectedStatus) {
        console.log(`✅ Guest: ${endpoint.description} - Properly restricted (Status ${response.status()})`);
      } else {
        console.log(`❌ Guest: ${endpoint.description} - Should be restricted, got ${response.status()}`);
      }
    }
  });

  test('@regression Method-level permissions testing', async ({ page }) => {
    await authHelper.login(testData.devUser.email, testData.devUser.password);
    
    // Test different HTTP methods for the same endpoint
    const endpoint = '/api/projects';
    const methods = [
      { method: 'GET', expectedStatus: 200, description: 'Read projects' },
      { method: 'POST', expectedStatus: 403, description: 'Create project' },
      { method: 'PUT', expectedStatus: 403, description: 'Update project' },
      { method: 'DELETE', expectedStatus: 403, description: 'Delete project' }
    ];
    
    for (const methodTest of methods) {
      let response;
      
      if (methodTest.method === 'GET') {
        response = await page.request.get(endpoint);
      } else if (methodTest.method === 'POST') {
        response = await page.request.post(endpoint, { data: {} });
      } else if (methodTest.method === 'PUT') {
        response = await page.request.put(endpoint, { data: {} });
      } else if (methodTest.method === 'DELETE') {
        response = await page.request.delete(endpoint);
      }
      
      if (response.status() === methodTest.expectedStatus) {
        console.log(`✅ Developer: ${methodTest.description} - Status ${response.status()}`);
      } else {
        console.log(`❌ Developer: ${methodTest.description} - Expected ${methodTest.expectedStatus}, got ${response.status()}`);
      }
    }
  });

  test('@regression Resource-level permissions testing', async ({ page }) => {
    await authHelper.login(testData.projectManager.email, testData.projectManager.password);
    
    // Test access to specific resources
    const resources = [
      { url: '/api/projects/1', method: 'GET', expectedStatus: 200, description: 'Access project 1' },
      { url: '/api/projects/1', method: 'PUT', expectedStatus: 200, description: 'Update project 1' },
      { url: '/api/projects/1', method: 'DELETE', expectedStatus: 200, description: 'Delete project 1' },
      { url: '/api/tasks/1', method: 'GET', expectedStatus: 200, description: 'Access task 1' },
      { url: '/api/tasks/1', method: 'PUT', expectedStatus: 200, description: 'Update task 1' },
      { url: '/api/tasks/1', method: 'DELETE', expectedStatus: 200, description: 'Delete task 1' }
    ];
    
    for (const resource of resources) {
      let response;
      
      if (resource.method === 'GET') {
        response = await page.request.get(resource.url);
      } else if (resource.method === 'PUT') {
        response = await page.request.put(resource.url, { data: {} });
      } else if (resource.method === 'DELETE') {
        response = await page.request.delete(resource.url);
      }
      
      if (response.status() === resource.expectedStatus) {
        console.log(`✅ Project Manager: ${resource.description} - Status ${response.status()}`);
      } else {
        console.log(`❌ Project Manager: ${resource.description} - Expected ${resource.expectedStatus}, got ${response.status()}`);
      }
    }
  });
});
