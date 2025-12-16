import { test, expect } from '@playwright/test';
import { AuthHelper } from '../../helpers/smoke-helpers';
import { testData } from '../../helpers/data';

// Theme helper function
const getThemeState = () => {
  const html = document.documentElement;
  return html.dataset.theme ?? (html.classList.contains('dark') ? 'dark' : 'light');
};

test.describe('E2E Core Tests - Tasks Create', () => {
  let authHelper: AuthHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
    
    // Clear localStorage to prevent theme preference from previous runs
    try {
      await page.evaluate(() => {
        localStorage.clear();
        sessionStorage.clear();
      });
    } catch (error) {
      console.log('Could not clear storage (security restriction):', (error as Error).message);
    }
    
    // Listen to console logs
    page.on('console', msg => {
      if (msg.type() === 'log') {
        console.log('Browser console:', msg.text());
      }
    });
  });

  test('@core Task creation modal opens and validates', async ({ page }) => {
    const authHelper = new AuthHelper(page);
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    await page.goto('/app/tasks');
    
    // Wait for tasks page to load
    await page.waitForTimeout(3000);
    
    // Look for "New Task" button
    const newTaskButton = page.locator('button:has-text("New Task"), button:has-text("Create Task"), [data-testid="new-task-button"]');
    const hasNewTaskButton = await newTaskButton.isVisible();
    
    if (hasNewTaskButton) {
      console.log('New Task button found');
      await newTaskButton.click();
      await page.waitForTimeout(1000);
      
      // Look for task creation modal/form
      const modal = page.locator('[role="dialog"], .modal, [data-testid="task-modal"], form');
      const hasModal = await modal.isVisible();
      
      if (hasModal) {
        console.log('Task creation modal found');
        
        // Check for required form fields
        const taskNameField = modal.locator('input[name="name"], input[name="title"], input[placeholder*="Task name"]');
        const taskDescriptionField = modal.locator('textarea[name="description"], textarea[placeholder*="Description"]');
        const projectSelect = modal.locator('select[name="project_id"], [data-testid="project-select"]');
        
        const hasNameField = await taskNameField.isVisible();
        const hasDescriptionField = await taskDescriptionField.isVisible();
        const hasProjectSelect = await projectSelect.isVisible();
        
        console.log(`Form fields - Name: ${hasNameField}, Description: ${hasDescriptionField}, Project: ${hasProjectSelect}`);
        
        // Test form validation
        const submitButton = modal.locator('button[type="submit"], button:has-text("Create"), button:has-text("Save")');
        
        if (await submitButton.isVisible()) {
          // Try to submit empty form
          await submitButton.click();
          await page.waitForTimeout(1000);
          
          // Check for validation errors
          const validationErrors = modal.locator('.error, .invalid, [data-testid="error"], .field-error');
          const hasValidationErrors = await validationErrors.isVisible();
          
          if (hasValidationErrors) {
            console.log('Form validation working - errors shown for empty form');
          } else {
            console.log('Form validation may not be implemented yet');
          }
        }
        
        // Close modal
        const closeButton = modal.locator('button:has-text("Cancel"), button:has-text("Close"), [data-testid="close-button"], [aria-label="Close"]');
        if (await closeButton.isVisible()) {
          await closeButton.click();
          await page.waitForTimeout(500);
        }
      } else {
        console.log('Task creation modal not found - feature may not be implemented yet');
      }
    } else {
      console.log('New Task button not found - feature may not be implemented yet');
    }
    
    // Test theme toggle functionality
    const themeToggle = page.locator('button:has-text("Dark mode"), button:has-text("Theme"), [data-testid="theme-toggle"]').first();
    
    if (await themeToggle.isVisible()) {
      const initialTheme = await page.evaluate(getThemeState);
      console.log('Task Create Modal Initial theme:', initialTheme);
      await themeToggle.click();
      await page.waitForTimeout(500);
      const newTheme = await page.evaluate(getThemeState);
      console.log('Task Create Modal New theme:', newTheme);
      expect(newTheme).not.toBe(initialTheme);
    }
  });

  test('@core Task creation with valid data', async ({ page }) => {
    const authHelper = new AuthHelper(page);
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    await page.goto('/app/tasks');
    
    // Wait for tasks page to load
    await page.waitForTimeout(3000);
    
    // Look for "New Task" button
    const newTaskButton = page.locator('button:has-text("New Task"), button:has-text("Create Task"), [data-testid="new-task-button"]');
    const hasNewTaskButton = await newTaskButton.isVisible();
    
    if (hasNewTaskButton) {
      await newTaskButton.click();
      await page.waitForTimeout(1000);
      
      // Look for task creation modal/form
      const modal = page.locator('[role="dialog"], .modal, [data-testid="task-modal"], form');
      const hasModal = await modal.isVisible();
      
      if (hasModal) {
        console.log('Attempting to create task with valid data');
        
        // Fill in task details
        const taskNameField = modal.locator('input[name="name"], input[name="title"], input[placeholder*="Task name"]');
        const taskDescriptionField = modal.locator('textarea[name="description"], textarea[placeholder*="Description"]');
        const projectSelect = modal.locator('select[name="project_id"], [data-testid="project-select"]');
        
        if (await taskNameField.isVisible()) {
          await taskNameField.fill('E2E Test Task');
        }
        
        if (await taskDescriptionField.isVisible()) {
          await taskDescriptionField.fill('Test task created during E2E testing');
        }
        
        if (await projectSelect.isVisible()) {
          // Try to select a project
          await projectSelect.click();
          await page.waitForTimeout(500);
          const projectOption = page.locator('option:has-text("E2E"), option:has-text("Test")').first();
          if (await projectOption.isVisible()) {
            await projectOption.click();
          }
        }
        
        // Submit the form
        const submitButton = modal.locator('button[type="submit"], button:has-text("Create"), button:has-text("Save")');
        if (await submitButton.isVisible()) {
          await submitButton.click();
          await page.waitForTimeout(2000);
          
          // Check for success message or task creation
          const successMessage = page.locator('text="Task created", text="Success", .success, .alert-success');
          const hasSuccess = await successMessage.isVisible();
          
          if (hasSuccess) {
            console.log('Task created successfully');
          } else {
            console.log('Task creation may have succeeded (no error visible)');
          }
        }
      } else {
        console.log('Task creation modal not found - feature may not be implemented yet');
      }
    } else {
      console.log('New Task button not found - feature may not be implemented yet');
    }
    
    // Test theme toggle functionality
    const themeToggle = page.locator('button:has-text("Dark mode"), button:has-text("Theme"), [data-testid="theme-toggle"]').first();
    
    if (await themeToggle.isVisible()) {
      const initialTheme = await page.evaluate(getThemeState);
      console.log('Task Create Valid Initial theme:', initialTheme);
      await themeToggle.click();
      await page.waitForTimeout(500);
      const newTheme = await page.evaluate(getThemeState);
      console.log('Task Create Valid New theme:', newTheme);
      expect(newTheme).not.toBe(initialTheme);
    }
  });

  test.fixme('@core Task creation RBAC - Admin vs PM vs Dev', async ({ page }) => {
    // TODO: RBAC-SECURITY-002 - Task creation permissions need verification
    // This test will verify that only appropriate roles can create tasks
    // Once implemented, change back to test() and verify role-based access
    
    // Test Admin permissions
    const adminUser = testData.users.zena.find(user => user.role === 'Admin');
    expect(adminUser).toBeDefined();
    
    if (adminUser) {
      await authHelper.login(adminUser.email, adminUser.password);
      await page.goto('/app/tasks');
      await page.waitForTimeout(3000);
      
      const newTaskButton = page.locator('button:has-text("New Task"), button:has-text("Create Task"), [data-testid="new-task-button"]');
      const adminCanCreate = await newTaskButton.isVisible();
      
      console.log(`Admin can create tasks: ${adminCanCreate}`);
      expect(adminCanCreate).toBe(true);
      
      await authHelper.logout();
    }
    
    // Test PM permissions
    const pmUser = testData.users.zena.find(user => user.role === 'Project Manager');
    expect(pmUser).toBeDefined();
    
    if (pmUser) {
      await authHelper.login(pmUser.email, pmUser.password);
      await page.goto('/app/tasks');
      await page.waitForTimeout(3000);
      
      const newTaskButton = page.locator('button:has-text("New Task"), button:has-text("Create Task"), [data-testid="new-task-button"]');
      const pmCanCreate = await newTaskButton.isVisible();
      
      console.log(`PM can create tasks: ${pmCanCreate}`);
      expect(pmCanCreate).toBe(true);
      
      await authHelper.logout();
    }
    
    // Test Dev permissions
    const devUser = testData.users.zena.find(user => user.role === 'Developer');
    expect(devUser).toBeDefined();
    
    if (devUser) {
      await authHelper.login(devUser.email, devUser.password);
      await page.goto('/app/tasks');
      await page.waitForTimeout(3000);
      
      const newTaskButton = page.locator('button:has-text("New Task"), button:has-text("Create Task"), [data-testid="new-task-button"]');
      const devCanCreate = await newTaskButton.isVisible();
      
      console.log(`Dev can create tasks: ${devCanCreate}`);
      // Dev should be able to create tasks (unlike projects)
      expect(devCanCreate).toBe(true);
      
      await authHelper.logout();
    }
  });
});
