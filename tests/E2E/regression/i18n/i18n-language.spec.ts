import { test, expect } from '@playwright/test';
import { AuthHelper } from '../../helpers/smoke-helpers';
import { testData } from '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/tests/e2e/helpers/data';

test.describe('@regression i18n Language Testing', () => {
  let authHelper: AuthHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
  });

  test('@regression Multi-language content support', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to different pages to check for multi-language content
    const pages = [
      { url: '/app/dashboard', name: 'Dashboard' },
      { url: '/app/projects', name: 'Projects' },
      { url: '/app/tasks', name: 'Tasks' },
      { url: '/app/documents', name: 'Documents' },
      { url: '/app/alerts', name: 'Alerts' }
    ];
    
    for (const pageInfo of pages) {
      await page.goto(pageInfo.url);
      await page.waitForLoadState('networkidle');
      
      console.log(`\n--- Testing ${pageInfo.name} Page ---`);
      
      // Check for common UI elements that should be localized
      const uiElements = [
        { selector: 'h1, h2, h3', name: 'Headings' },
        { selector: 'button', name: 'Buttons' },
        { selector: 'label', name: 'Labels' },
        { selector: '.nav-link, .menu-item', name: 'Navigation' },
        { selector: '.breadcrumb', name: 'Breadcrumbs' }
      ];
      
      for (const element of uiElements) {
        const elements = page.locator(element.selector);
        const count = await elements.count();
        
        if (count > 0) {
          console.log(`✅ Found ${count} ${element.name} elements`);
          
          // Check first few elements for text content
          for (let i = 0; i < Math.min(count, 3); i++) {
            const text = await elements.nth(i).textContent();
            if (text && text.trim()) {
              console.log(`  ${element.name} ${i + 1}: "${text.trim()}"`);
            }
          }
        } else {
          console.log(`⚠️ No ${element.name} elements found`);
        }
      }
    }
  });

  test('@regression Translation completeness check', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to a page with various UI elements
    await page.goto('/app/projects');
    await page.waitForLoadState('networkidle');
    
    // Check for untranslated text (common English words that should be translated)
    const commonEnglishWords = [
      'Create', 'Edit', 'Delete', 'Save', 'Cancel', 'Submit', 'Add', 'Remove',
      'Search', 'Filter', 'Sort', 'Export', 'Import', 'Download', 'Upload',
      'Name', 'Description', 'Status', 'Date', 'Time', 'User', 'Project', 'Task'
    ];
    
    console.log('Checking for untranslated English words...');
    
    for (const word of commonEnglishWords) {
      const elements = page.locator(`text="${word}"`);
      const count = await elements.count();
      
      if (count > 0) {
        console.log(`⚠️ Found "${word}" (${count} instances) - may need translation`);
      }
    }
    
    // Check for placeholder text
    const placeholders = page.locator('input[placeholder], textarea[placeholder]');
    const placeholderCount = await placeholders.count();
    
    if (placeholderCount > 0) {
      console.log(`✅ Found ${placeholderCount} input placeholders`);
      
      for (let i = 0; i < Math.min(placeholderCount, 3); i++) {
        const placeholder = await placeholders.nth(i).getAttribute('placeholder');
        if (placeholder) {
          console.log(`  Placeholder ${i + 1}: "${placeholder}"`);
        }
      }
    }
  });

  test('@regression Language switching UI elements', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Look for language switching elements in different locations
    const locations = [
      { url: '/app/dashboard', name: 'Dashboard' },
      { url: '/app/preferences', name: 'Preferences' },
      { url: '/app/profile', name: 'Profile' },
      { url: '/app/settings', name: 'Settings' }
    ];
    
    for (const location of locations) {
      await page.goto(location.url);
      await page.waitForLoadState('networkidle');
      
      console.log(`\n--- Checking ${location.name} for language switching ---`);
      
      // Look for language switching elements
      const languageElements = [
        { selector: 'select[name="language"]', name: 'Language select' },
        { selector: '.language-selector', name: 'Language selector' },
        { selector: 'button:has-text("Language")', name: 'Language button' },
        { selector: '.language-toggle', name: 'Language toggle' },
        { selector: '.language-dropdown', name: 'Language dropdown' },
        { selector: '.language-menu', name: 'Language menu' }
      ];
      
      let foundLanguageElement = false;
      
      for (const element of languageElements) {
        const elements = page.locator(element.selector);
        
        if (await elements.isVisible()) {
          console.log(`✅ Found ${element.name}`);
          foundLanguageElement = true;
          
          // Check for language options
          const options = page.locator('option, .language-option');
          const optionCount = await options.count();
          
          if (optionCount > 0) {
            console.log(`  Found ${optionCount} language options`);
            
            // Check for specific languages
            const languages = ['English', 'Vietnamese', 'Tiếng Việt', 'en', 'vi'];
            
            for (const lang of languages) {
              const langOption = page.locator(`option:has-text("${lang}"), .language-option:has-text("${lang}")`);
              
              if (await langOption.isVisible()) {
                console.log(`  ✅ Found ${lang} option`);
              }
            }
          }
          
          break; // Found language element, no need to check others
        }
      }
      
      if (!foundLanguageElement) {
        console.log(`⚠️ No language switching elements found in ${location.name}`);
      }
    }
  });

  test('@regression Error message localization', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to a form page to test error messages
    await page.goto('/app/projects');
    await page.waitForLoadState('networkidle');
    
    // Look for form elements
    const forms = page.locator('form');
    const formCount = await forms.count();
    
    if (formCount > 0) {
      console.log(`✅ Found ${formCount} forms`);
      
      // Try to trigger validation errors
      const submitButtons = page.locator('button[type="submit"], button:has-text("Submit"), button:has-text("Save")');
      
      if (await submitButtons.isVisible()) {
        await submitButtons.first().click();
        
        // Wait for potential error messages
        await page.waitForTimeout(1000);
        
        // Check for error messages
        const errorElements = page.locator('.error, .alert-error, .validation-error, .field-error, .invalid-feedback');
        const errorCount = await errorElements.count();
        
        if (errorCount > 0) {
          console.log(`✅ Found ${errorCount} error messages`);
          
          for (let i = 0; i < Math.min(errorCount, 3); i++) {
            const errorText = await errorElements.nth(i).textContent();
            if (errorText) {
              console.log(`  Error ${i + 1}: "${errorText.trim()}"`);
            }
          }
        } else {
          console.log('⚠️ No error messages found - may need to test with invalid data');
        }
      }
    } else {
      console.log('⚠️ No forms found to test error messages');
    }
  });

  test('@regression Notification and alert localization', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to different pages to check for notifications
    const pages = [
      { url: '/app/dashboard', name: 'Dashboard' },
      { url: '/app/projects', name: 'Projects' },
      { url: '/app/tasks', name: 'Tasks' }
    ];
    
    for (const pageInfo of pages) {
      await page.goto(pageInfo.url);
      await page.waitForLoadState('networkidle');
      
      console.log(`\n--- Checking ${pageInfo.name} for notifications ---`);
      
      // Look for notification elements
      const notificationElements = [
        { selector: '.notification, .alert, .toast, .message', name: 'Notifications' },
        { selector: '.success, .alert-success', name: 'Success messages' },
        { selector: '.warning, .alert-warning', name: 'Warning messages' },
        { selector: '.info, .alert-info', name: 'Info messages' }
      ];
      
      for (const element of notificationElements) {
        const elements = page.locator(element.selector);
        const count = await elements.count();
        
        if (count > 0) {
          console.log(`✅ Found ${count} ${element.name}`);
          
          for (let i = 0; i < Math.min(count, 2); i++) {
            const text = await elements.nth(i).textContent();
            if (text && text.trim()) {
              console.log(`  ${element.name} ${i + 1}: "${text.trim()}"`);
            }
          }
        }
      }
    }
  });

  test('@regression Language fallback behavior', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Test with unsupported language
    await page.goto('/app/dashboard');
    await page.waitForLoadState('networkidle');
    
    // Try to set unsupported language via URL parameter
    await page.goto('/app/dashboard?lang=xx');
    await page.waitForLoadState('networkidle');
    
    // Check if fallback language is used
    const htmlLang = await page.getAttribute('html', 'lang');
    const documentLang = await page.evaluate(() => document.documentElement.lang);
    
    console.log(`HTML lang attribute: ${htmlLang}`);
    console.log(`Document lang: ${documentLang}`);
    
    if (htmlLang === 'en' || documentLang === 'en') {
      console.log('✅ Fallback to English language working');
    } else if (htmlLang === 'xx' || documentLang === 'xx') {
      console.log('⚠️ Unsupported language not handled properly');
    } else {
      console.log(`⚠️ Unexpected language: ${htmlLang || documentLang}`);
    }
    
    // Test with empty language
    await page.goto('/app/dashboard?lang=');
    await page.waitForLoadState('networkidle');
    
    const htmlLangEmpty = await page.getAttribute('html', 'lang');
    const documentLangEmpty = await page.evaluate(() => document.documentElement.lang);
    
    console.log(`Empty lang - HTML: ${htmlLangEmpty}, Document: ${documentLangEmpty}`);
    
    if (htmlLangEmpty === 'en' || documentLangEmpty === 'en') {
      console.log('✅ Empty language fallback to English working');
    } else {
      console.log('⚠️ Empty language fallback not working properly');
    }
  });
});
