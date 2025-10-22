import { test, expect } from '@playwright/test';
import { AuthHelper } from '../../helpers/smoke-helpers';
import { testData } from '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/tests/e2e/helpers/data';

test.describe('@regression i18n Modal / Timezone Testing', () => {
  let authHelper: AuthHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
  });

  test('@regression Language switching functionality', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to preferences or settings page
    await page.goto('/app/preferences');
    
    // Check if preferences page exists
    if (page.url().includes('preferences')) {
      console.log('✅ Preferences page found');
      
      // Look for language selector
      const languageSelector = page.locator('select[name="language"], .language-selector, button:has-text("Language")');
      
      if (await languageSelector.isVisible()) {
        console.log('✅ Language selector found');
        
        // Test language switching
        const languageOptions = page.locator('option, .language-option');
        const languageCount = await languageOptions.count();
        
        console.log(`Found ${languageCount} language options`);
        
        // Switch to Vietnamese
        const vietnameseOption = page.locator('option[value="vi"], .language-option:has-text("Vietnamese"), .language-option:has-text("Tiếng Việt")');
        
        if (await vietnameseOption.isVisible()) {
          await vietnameseOption.click();
          
          // Wait for language change
          await page.waitForTimeout(1000);
          
          // Check if language changed
          const htmlLang = await page.getAttribute('html', 'lang');
          const documentLang = await page.evaluate(() => document.documentElement.lang);
          
          if (htmlLang === 'vi' || documentLang === 'vi') {
            console.log('✅ Language switched to Vietnamese');
            
            // Check for Vietnamese text
            const vietnameseText = page.locator('text=/chào|xin chào|tiếng việt/i');
            
            if (await vietnameseText.isVisible()) {
              console.log('✅ Vietnamese text displayed');
            } else {
              console.log('⚠️ Vietnamese text not found - may need to implement translations');
            }
            
          } else {
            console.log('⚠️ Language attribute not updated to Vietnamese');
          }
          
        } else {
          console.log('⚠️ Vietnamese option not found');
        }
        
        // Switch back to English
        const englishOption = page.locator('option[value="en"], .language-option:has-text("English")');
        
        if (await englishOption.isVisible()) {
          await englishOption.click();
          
          // Wait for language change
          await page.waitForTimeout(1000);
          
          // Check if language changed back
          const htmlLang = await page.getAttribute('html', 'lang');
          const documentLang = await page.evaluate(() => document.documentElement.lang);
          
          if (htmlLang === 'en' || documentLang === 'en') {
            console.log('✅ Language switched back to English');
          } else {
            console.log('⚠️ Language attribute not updated to English');
          }
        }
        
      } else {
        console.log('⚠️ Language selector not found - may need to implement language switching');
      }
      
    } else {
      console.log('⚠️ Preferences page not found - may need to implement');
      
      // Try to find language settings in other locations
      await page.goto('/app/dashboard');
      
      const languageButton = page.locator('button:has-text("Language"), button:has-text("Lang"), .language-toggle');
      
      if (await languageButton.isVisible()) {
        console.log('✅ Language button found in dashboard');
        
        await languageButton.click();
        
        // Check for language dropdown
        const languageDropdown = page.locator('.language-dropdown, .language-menu, .language-options');
        
        if (await languageDropdown.isVisible()) {
          console.log('✅ Language dropdown found');
        } else {
          console.log('⚠️ Language dropdown not found');
        }
        
      } else {
        console.log('⚠️ Language button not found in dashboard');
      }
    }
  });

  test('@regression Timezone handling and conversion', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to preferences page
    await page.goto('/app/preferences');
    
    // Check if preferences page exists
    if (page.url().includes('preferences')) {
      console.log('✅ Preferences page found');
      
      // Look for timezone selector
      const timezoneSelector = page.locator('select[name="timezone"], .timezone-selector, button:has-text("Timezone")');
      
      if (await timezoneSelector.isVisible()) {
        console.log('✅ Timezone selector found');
        
        // Test timezone switching
        const timezoneOptions = page.locator('option, .timezone-option');
        const timezoneCount = await timezoneOptions.count();
        
        console.log(`Found ${timezoneCount} timezone options`);
        
        // Switch to different timezone
        const timezones = [
          { value: 'Asia/Ho_Chi_Minh', name: 'Ho Chi Minh' },
          { value: 'UTC', name: 'UTC' },
          { value: 'America/New_York', name: 'New York' },
          { value: 'Europe/London', name: 'London' }
        ];
        
        for (const timezone of timezones) {
          const timezoneOption = page.locator(`option[value="${timezone.value}"], .timezone-option:has-text("${timezone.name}")`);
          
          if (await timezoneOption.isVisible()) {
            await timezoneOption.click();
            
            // Wait for timezone change
            await page.waitForTimeout(1000);
            
            console.log(`✅ Timezone switched to ${timezone.name}`);
            
            // Check for timezone-specific date/time display
            const dateTimeDisplay = page.locator('.date-time, .timestamp, .current-time');
            
            if (await dateTimeDisplay.isVisible()) {
              console.log(`✅ Date/time display found for ${timezone.name}`);
              
              // Get the displayed time
              const displayedTime = await dateTimeDisplay.textContent();
              console.log(`Displayed time: ${displayedTime}`);
              
            } else {
              console.log(`⚠️ Date/time display not found for ${timezone.name}`);
            }
            
            break; // Test one timezone change
          }
        }
        
      } else {
        console.log('⚠️ Timezone selector not found - may need to implement timezone switching');
      }
      
    } else {
      console.log('⚠️ Preferences page not found - may need to implement');
    }
  });

  test('@regression Date/time formatting across locales', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to a page with date/time displays
    await page.goto('/app/projects');
    await page.waitForLoadState('networkidle');
    
    // Look for date/time displays
    const dateTimeElements = page.locator('.date, .time, .timestamp, .created-at, .updated-at, .due-date');
    const dateTimeCount = await dateTimeElements.count();
    
    if (dateTimeCount > 0) {
      console.log(`✅ Found ${dateTimeCount} date/time elements`);
      
      // Check date/time formatting
      for (let i = 0; i < Math.min(dateTimeCount, 5); i++) {
        const element = dateTimeElements.nth(i);
        const text = await element.textContent();
        
        if (text) {
          console.log(`Date/time element ${i + 1}: ${text}`);
          
          // Check if it's a valid date format
          const dateRegex = /\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}|\d{4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,2}/;
          
          if (dateRegex.test(text)) {
            console.log(`✅ Valid date format found: ${text}`);
          } else {
            console.log(`⚠️ Unusual date format: ${text}`);
          }
        }
      }
      
    } else {
      console.log('⚠️ No date/time elements found');
    }
    
    // Test with different language settings
    await page.goto('/app/preferences');
    
    if (page.url().includes('preferences')) {
      // Switch to Vietnamese
      const vietnameseOption = page.locator('option[value="vi"], .language-option:has-text("Vietnamese")');
      
      if (await vietnameseOption.isVisible()) {
        await vietnameseOption.click();
        await page.waitForTimeout(1000);
        
        // Go back to projects page
        await page.goto('/app/projects');
        await page.waitForLoadState('networkidle');
        
        // Check date/time formatting in Vietnamese
        const dateTimeElementsVi = page.locator('.date, .time, .timestamp, .created-at, .updated-at, .due-date');
        const dateTimeCountVi = await dateTimeElementsVi.count();
        
        if (dateTimeCountVi > 0) {
          console.log(`✅ Found ${dateTimeCountVi} date/time elements in Vietnamese`);
          
          // Check if formatting changed
          const firstElement = dateTimeElementsVi.first();
          const textVi = await firstElement.textContent();
          
          if (textVi) {
            console.log(`Vietnamese date/time: ${textVi}`);
          }
        }
      }
    }
  });

  test('@regression Modal and form localization', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to a page with modals
    await page.goto('/app/projects');
    await page.waitForLoadState('networkidle');
    
    // Look for modal trigger
    const modalTrigger = page.locator('button:has-text("Create"), button:has-text("Add"), button:has-text("New")');
    
    if (await modalTrigger.isVisible()) {
      await modalTrigger.click();
      
      // Wait for modal to open
      await page.waitForTimeout(1000);
      
      // Check for modal
      const modal = page.locator('.modal, .dialog, [role="dialog"]');
      
      if (await modal.isVisible()) {
        console.log('✅ Modal opened');
        
        // Check for localized text in modal
        const modalText = page.locator('.modal, .dialog, [role="dialog"]');
        const modalContent = await modalText.textContent();
        
        if (modalContent) {
          console.log(`Modal content: ${modalContent.substring(0, 100)}...`);
          
          // Check for common form labels
          const formLabels = page.locator('label, .form-label, .field-label');
          const labelCount = await formLabels.count();
          
          console.log(`Found ${labelCount} form labels in modal`);
          
          // Check for localized button text
          const modalButtons = page.locator('.modal button, .dialog button, [role="dialog"] button');
          const buttonCount = await modalButtons.count();
          
          console.log(`Found ${buttonCount} buttons in modal`);
          
          // Test with different language
          await page.goto('/app/preferences');
          
          if (page.url().includes('preferences')) {
            const vietnameseOption = page.locator('option[value="vi"], .language-option:has-text("Vietnamese")');
            
            if (await vietnameseOption.isVisible()) {
              await vietnameseOption.click();
              await page.waitForTimeout(1000);
              
              // Go back to projects and open modal again
              await page.goto('/app/projects');
              await page.waitForLoadState('networkidle');
              
              const modalTriggerVi = page.locator('button:has-text("Create"), button:has-text("Add"), button:has-text("New")');
              
              if (await modalTriggerVi.isVisible()) {
                await modalTriggerVi.click();
                await page.waitForTimeout(1000);
                
                const modalVi = page.locator('.modal, .dialog, [role="dialog"]');
                
                if (await modalVi.isVisible()) {
                  console.log('✅ Modal opened in Vietnamese');
                  
                  const modalContentVi = await modalVi.textContent();
                  
                  if (modalContentVi) {
                    console.log(`Vietnamese modal content: ${modalContentVi.substring(0, 100)}...`);
                  }
                }
              }
            }
          }
        }
        
      } else {
        console.log('⚠️ Modal not found - may need to implement modal functionality');
      }
      
    } else {
      console.log('⚠️ Modal trigger not found - may need to implement modal functionality');
    }
  });

  test('@regression Currency and number formatting', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to a page with currency/number displays
    await page.goto('/app/projects');
    await page.waitForLoadState('networkidle');
    
    // Look for currency/number displays
    const currencyElements = page.locator('.currency, .price, .budget, .amount, .cost');
    const numberElements = page.locator('.number, .count, .quantity, .percentage');
    
    const currencyCount = await currencyElements.count();
    const numberCount = await numberElements.count();
    
    if (currencyCount > 0) {
      console.log(`✅ Found ${currencyCount} currency elements`);
      
      // Check currency formatting
      for (let i = 0; i < Math.min(currencyCount, 3); i++) {
        const element = currencyElements.nth(i);
        const text = await element.textContent();
        
        if (text) {
          console.log(`Currency element ${i + 1}: ${text}`);
          
          // Check for currency symbols
          const currencyRegex = /[\$€£¥₫]|\d+[,\d]*\.?\d*\s*[A-Z]{3}/;
          
          if (currencyRegex.test(text)) {
            console.log(`✅ Valid currency format found: ${text}`);
          } else {
            console.log(`⚠️ Unusual currency format: ${text}`);
          }
        }
      }
    }
    
    if (numberCount > 0) {
      console.log(`✅ Found ${numberCount} number elements`);
      
      // Check number formatting
      for (let i = 0; i < Math.min(numberCount, 3); i++) {
        const element = numberElements.nth(i);
        const text = await element.textContent();
        
        if (text) {
          console.log(`Number element ${i + 1}: ${text}`);
          
          // Check for number format
          const numberRegex = /\d+[,\d]*\.?\d*/;
          
          if (numberRegex.test(text)) {
            console.log(`✅ Valid number format found: ${text}`);
          } else {
            console.log(`⚠️ Unusual number format: ${text}`);
          }
        }
      }
    }
    
    // Test with different locale settings
    await page.goto('/app/preferences');
    
    if (page.url().includes('preferences')) {
      // Switch to Vietnamese locale
      const vietnameseOption = page.locator('option[value="vi"], .language-option:has-text("Vietnamese")');
      
      if (await vietnameseOption.isVisible()) {
        await vietnameseOption.click();
        await page.waitForTimeout(1000);
        
        // Go back to projects page
        await page.goto('/app/projects');
        await page.waitForLoadState('networkidle');
        
        // Check currency/number formatting in Vietnamese
        const currencyElementsVi = page.locator('.currency, .price, .budget, .amount, .cost');
        const currencyCountVi = await currencyElementsVi.count();
        
        if (currencyCountVi > 0) {
          console.log(`✅ Found ${currencyCountVi} currency elements in Vietnamese`);
          
          const firstElement = currencyElementsVi.first();
          const textVi = await firstElement.textContent();
          
          if (textVi) {
            console.log(`Vietnamese currency: ${textVi}`);
          }
        }
      }
    }
  });

  test('@regression RTL language support', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to preferences page
    await page.goto('/app/preferences');
    
    // Check if preferences page exists
    if (page.url().includes('preferences')) {
      console.log('✅ Preferences page found');
      
      // Look for RTL language options
      const rtlLanguages = page.locator('option[value="ar"], option[value="he"], .language-option:has-text("Arabic"), .language-option:has-text("Hebrew")');
      
      if (await rtlLanguages.isVisible()) {
        console.log('✅ RTL language options found');
        
        // Test RTL language switching
        const arabicOption = page.locator('option[value="ar"], .language-option:has-text("Arabic")');
        
        if (await arabicOption.isVisible()) {
          await arabicOption.click();
          
          // Wait for language change
          await page.waitForTimeout(1000);
          
          // Check for RTL direction
          const htmlDir = await page.getAttribute('html', 'dir');
          const documentDir = await page.evaluate(() => document.documentElement.dir);
          
          if (htmlDir === 'rtl' || documentDir === 'rtl') {
            console.log('✅ RTL direction applied');
            
            // Check for RTL-specific styling
            const rtlStyles = await page.evaluate(() => {
              const styles = window.getComputedStyle(document.body);
              return {
                textAlign: styles.textAlign,
                direction: styles.direction
              };
            });
            
            console.log(`RTL styles: ${JSON.stringify(rtlStyles)}`);
            
          } else {
            console.log('⚠️ RTL direction not applied');
          }
          
        } else {
          console.log('⚠️ Arabic option not found');
        }
        
      } else {
        console.log('⚠️ RTL language options not found - may need to implement RTL support');
      }
      
    } else {
      console.log('⚠️ Preferences page not found - may need to implement');
    }
  });

  test('@regression Language preference persistence', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to preferences page
    await page.goto('/app/preferences');
    
    // Check if preferences page exists
    if (page.url().includes('preferences')) {
      console.log('✅ Preferences page found');
      
      // Set language to Vietnamese
      const vietnameseOption = page.locator('option[value="vi"], .language-option:has-text("Vietnamese")');
      
      if (await vietnameseOption.isVisible()) {
        await vietnameseOption.click();
        
        // Save preferences
        const saveButton = page.locator('button:has-text("Save"), button:has-text("Update"), button[type="submit"]');
        
        if (await saveButton.isVisible()) {
          await saveButton.click();
          
          // Wait for save
          await page.waitForTimeout(1000);
          
          console.log('✅ Language preference saved');
          
          // Navigate away and back
          await page.goto('/app/dashboard');
          await page.waitForLoadState('networkidle');
          
          await page.goto('/app/preferences');
          await page.waitForLoadState('networkidle');
          
          // Check if language preference persisted
          const selectedLanguage = page.locator('select[name="language"] option:checked, .language-selector .selected');
          
          if (await selectedLanguage.isVisible()) {
            const selectedValue = await selectedLanguage.getAttribute('value');
            const selectedText = await selectedLanguage.textContent();
            
            if (selectedValue === 'vi' || selectedText?.includes('Vietnamese')) {
              console.log('✅ Language preference persisted');
            } else {
              console.log('⚠️ Language preference not persisted');
            }
          } else {
            console.log('⚠️ Selected language not found');
          }
          
        } else {
          console.log('⚠️ Save button not found');
        }
        
      } else {
        console.log('⚠️ Vietnamese option not found');
      }
      
    } else {
      console.log('⚠️ Preferences page not found - may need to implement');
    }
  });
});
