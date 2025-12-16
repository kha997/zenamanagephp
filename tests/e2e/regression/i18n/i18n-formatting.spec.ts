import { test, expect } from '@playwright/test';
import { AuthHelper } from '../../helpers/smoke-helpers';
import { testData } from '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/tests/e2e/helpers/data';

test.describe('@regression i18n Formatting Testing', () => {
  let authHelper: AuthHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
  });

  test('@regression Date formatting across locales', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to a page with date displays
    await page.goto('/app/projects');
    await page.waitForLoadState('networkidle');
    
    // Look for date elements
    const dateElements = page.locator('.date, .created-at, .updated-at, .due-date, .start-date, .end-date');
    const dateCount = await dateElements.count();
    
    if (dateCount > 0) {
      console.log(`✅ Found ${dateCount} date elements`);
      
      // Check date formatting
      for (let i = 0; i < Math.min(dateCount, 5); i++) {
        const element = dateElements.nth(i);
        const text = await element.textContent();
        
        if (text && text.trim()) {
          console.log(`Date element ${i + 1}: "${text.trim()}"`);
          
          // Check for common date formats
          const dateFormats = [
            { pattern: /\d{1,2}\/\d{1,2}\/\d{4}/, name: 'MM/DD/YYYY' },
            { pattern: /\d{4}-\d{1,2}-\d{1,2}/, name: 'YYYY-MM-DD' },
            { pattern: /\d{1,2}\.\d{1,2}\.\d{4}/, name: 'DD.MM.YYYY' },
            { pattern: /\d{1,2}\s+\w+\s+\d{4}/, name: 'DD Month YYYY' }
          ];
          
          let formatFound = false;
          for (const format of dateFormats) {
            if (format.pattern.test(text)) {
              console.log(`  ✅ ${format.name} format detected`);
              formatFound = true;
              break;
            }
          }
          
          if (!formatFound) {
            console.log(`  ⚠️ Unusual date format: "${text.trim()}"`);
          }
        }
      }
    } else {
      console.log('⚠️ No date elements found');
    }
  });

  test('@regression Time formatting across locales', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to a page with time displays
    await page.goto('/app/projects');
    await page.waitForLoadState('networkidle');
    
    // Look for time elements
    const timeElements = page.locator('.time, .timestamp, .created-time, .updated-time');
    const timeCount = await timeElements.count();
    
    if (timeCount > 0) {
      console.log(`✅ Found ${timeCount} time elements`);
      
      // Check time formatting
      for (let i = 0; i < Math.min(timeCount, 5); i++) {
        const element = timeElements.nth(i);
        const text = await element.textContent();
        
        if (text && text.trim()) {
          console.log(`Time element ${i + 1}: "${text.trim()}"`);
          
          // Check for common time formats
          const timeFormats = [
            { pattern: /\d{1,2}:\d{2}:\d{2}/, name: 'HH:MM:SS' },
            { pattern: /\d{1,2}:\d{2}/, name: 'HH:MM' },
            { pattern: /\d{1,2}:\d{2}\s*[AP]M/i, name: 'HH:MM AM/PM' },
            { pattern: /\d{1,2}h\d{2}/, name: 'HHhMM' }
          ];
          
          let formatFound = false;
          for (const format of timeFormats) {
            if (format.pattern.test(text)) {
              console.log(`  ✅ ${format.name} format detected`);
              formatFound = true;
              break;
            }
          }
          
          if (!formatFound) {
            console.log(`  ⚠️ Unusual time format: "${text.trim()}"`);
          }
        }
      }
    } else {
      console.log('⚠️ No time elements found');
    }
  });

  test('@regression Number formatting across locales', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to a page with number displays
    await page.goto('/app/projects');
    await page.waitForLoadState('networkidle');
    
    // Look for number elements
    const numberElements = page.locator('.number, .count, .quantity, .percentage, .progress');
    const numberCount = await numberElements.count();
    
    if (numberCount > 0) {
      console.log(`✅ Found ${numberCount} number elements`);
      
      // Check number formatting
      for (let i = 0; i < Math.min(numberCount, 5); i++) {
        const element = numberElements.nth(i);
        const text = await element.textContent();
        
        if (text && text.trim()) {
          console.log(`Number element ${i + 1}: "${text.trim()}"`);
          
          // Check for common number formats
          const numberFormats = [
            { pattern: /\d{1,3}(,\d{3})*/, name: 'Comma-separated thousands' },
            { pattern: /\d{1,3}(\.\d{3})*/, name: 'Dot-separated thousands' },
            { pattern: /\d+\.\d+%/, name: 'Percentage' },
            { pattern: /\d+%/, name: 'Simple percentage' }
          ];
          
          let formatFound = false;
          for (const format of numberFormats) {
            if (format.pattern.test(text)) {
              console.log(`  ✅ ${format.name} format detected`);
              formatFound = true;
              break;
            }
          }
          
          if (!formatFound) {
            console.log(`  ⚠️ Unusual number format: "${text.trim()}"`);
          }
        }
      }
    } else {
      console.log('⚠️ No number elements found');
    }
  });

  test('@regression Currency formatting across locales', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to a page with currency displays
    await page.goto('/app/projects');
    await page.waitForLoadState('networkidle');
    
    // Look for currency elements
    const currencyElements = page.locator('.currency, .price, .budget, .amount, .cost, .total');
    const currencyCount = await currencyElements.count();
    
    if (currencyCount > 0) {
      console.log(`✅ Found ${currencyCount} currency elements`);
      
      // Check currency formatting
      for (let i = 0; i < Math.min(currencyCount, 5); i++) {
        const element = currencyElements.nth(i);
        const text = await element.textContent();
        
        if (text && text.trim()) {
          console.log(`Currency element ${i + 1}: "${text.trim()}"`);
          
          // Check for common currency formats
          const currencyFormats = [
            { pattern: /\$\d+/, name: 'Dollar prefix' },
            { pattern: /\d+\$/, name: 'Dollar suffix' },
            { pattern: /€\d+/, name: 'Euro prefix' },
            { pattern: /\d+€/, name: 'Euro suffix' },
            { pattern: /₫\d+/, name: 'Vietnamese Dong prefix' },
            { pattern: /\d+₫/, name: 'Vietnamese Dong suffix' },
            { pattern: /\d+\s*[A-Z]{3}/, name: 'Currency code' }
          ];
          
          let formatFound = false;
          for (const format of currencyFormats) {
            if (format.pattern.test(text)) {
              console.log(`  ✅ ${format.name} format detected`);
              formatFound = true;
              break;
            }
          }
          
          if (!formatFound) {
            console.log(`  ⚠️ Unusual currency format: "${text.trim()}"`);
          }
        }
      }
    } else {
      console.log('⚠️ No currency elements found');
    }
  });

  test('@regression Locale-specific formatting changes', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Test formatting with different locale settings
    const locales = [
      { code: 'en-US', name: 'English (US)' },
      { code: 'vi-VN', name: 'Vietnamese (Vietnam)' },
      { code: 'en-GB', name: 'English (UK)' }
    ];
    
    for (const locale of locales) {
      console.log(`\n--- Testing ${locale.name} formatting ---`);
      
      // Set locale via URL parameter
      await page.goto(`/app/projects?locale=${locale.code}`);
      await page.waitForLoadState('networkidle');
      
      // Check if locale is applied
      const htmlLang = await page.getAttribute('html', 'lang');
      console.log(`HTML lang attribute: ${htmlLang}`);
      
      // Look for formatted elements
      const formattedElements = page.locator('.date, .time, .currency, .number');
      const elementCount = await formattedElements.count();
      
      if (elementCount > 0) {
        console.log(`Found ${elementCount} formatted elements for ${locale.name}`);
        
        // Check first few elements
        for (let i = 0; i < Math.min(elementCount, 3); i++) {
          const text = await formattedElements.nth(i).textContent();
          if (text && text.trim()) {
            console.log(`  Element ${i + 1}: "${text.trim()}"`);
          }
        }
      }
    }
  });

  test('@regression Input field formatting', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to a form page
    await page.goto('/app/projects');
    await page.waitForLoadState('networkidle');
    
    // Look for input fields
    const inputFields = page.locator('input[type="text"], input[type="number"], input[type="date"], input[type="time"]');
    const inputCount = await inputFields.count();
    
    if (inputCount > 0) {
      console.log(`✅ Found ${inputCount} input fields`);
      
      // Check input field formatting
      for (let i = 0; i < Math.min(inputCount, 5); i++) {
        const input = inputFields.nth(i);
        const type = await input.getAttribute('type');
        const placeholder = await input.getAttribute('placeholder');
        const value = await input.getAttribute('value');
        
        console.log(`Input ${i + 1}: type="${type}", placeholder="${placeholder}", value="${value}"`);
        
        // Test input formatting for different types
        if (type === 'number') {
          // Test number input formatting
          await input.fill('1234.56');
          const filledValue = await input.inputValue();
          console.log(`  Number input value: "${filledValue}"`);
        } else if (type === 'date') {
          // Test date input formatting
          await input.fill('2024-01-15');
          const filledValue = await input.inputValue();
          console.log(`  Date input value: "${filledValue}"`);
        }
      }
    } else {
      console.log('⚠️ No input fields found');
    }
  });

  test('@regression Table formatting across locales', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to a page with tables
    await page.goto('/app/projects');
    await page.waitForLoadState('networkidle');
    
    // Look for tables
    const tables = page.locator('table, .table');
    const tableCount = await tables.count();
    
    if (tableCount > 0) {
      console.log(`✅ Found ${tableCount} tables`);
      
      // Check table formatting
      for (let i = 0; i < Math.min(tableCount, 2); i++) {
        const table = tables.nth(i);
        
        // Check table headers
        const headers = table.locator('th, .table-header');
        const headerCount = await headers.count();
        
        if (headerCount > 0) {
          console.log(`  Table ${i + 1}: Found ${headerCount} headers`);
          
          for (let j = 0; j < Math.min(headerCount, 5); j++) {
            const headerText = await headers.nth(j).textContent();
            if (headerText && headerText.trim()) {
              console.log(`    Header ${j + 1}: "${headerText.trim()}"`);
            }
          }
        }
        
        // Check table data
        const cells = table.locator('td, .table-cell');
        const cellCount = await cells.count();
        
        if (cellCount > 0) {
          console.log(`  Table ${i + 1}: Found ${cellCount} cells`);
          
          // Check first few cells for formatting
          for (let j = 0; j < Math.min(cellCount, 5); j++) {
            const cellText = await cells.nth(j).textContent();
            if (cellText && cellText.trim()) {
              console.log(`    Cell ${j + 1}: "${cellText.trim()}"`);
            }
          }
        }
      }
    } else {
      console.log('⚠️ No tables found');
    }
  });
});
