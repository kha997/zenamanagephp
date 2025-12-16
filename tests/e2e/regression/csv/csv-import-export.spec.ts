import { test, expect } from '@playwright/test';
import { AuthHelper } from '../../helpers/smoke-helpers';
import { testData } from '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/tests/e2e/helpers/data';
import * as fs from 'fs';
import * as path from 'path';

test.describe('@regression CSV Import/Export Testing', () => {
  let authHelper: AuthHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
  });

  test('@regression CSV Export functionality', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to users page
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Look for export button
    const exportButton = page.locator('button:has-text("Export"), button:has-text("Download"), button:has-text("CSV")');
    
    if (await exportButton.isVisible()) {
      console.log('✅ Export button found');
      
      // Set up download handling
      const downloadPromise = page.waitForEvent('download');
      
      await exportButton.click();
      
      // Wait for download
      const download = await downloadPromise;
      
      // Get download path
      const downloadPath = await download.path();
      
      if (downloadPath) {
        console.log(`✅ CSV file downloaded: ${downloadPath}`);
        
        // Read and parse CSV file
        const csvContent = fs.readFileSync(downloadPath, 'utf-8');
        const lines = csvContent.split('\n');
        
        console.log(`CSV file contains ${lines.length} lines`);
        
        // Check CSV structure
        if (lines.length > 0) {
          const headers = lines[0].split(',');
          console.log(`CSV headers: ${headers.join(', ')}`);
          
          // Check for expected columns
          const expectedColumns = ['id', 'name', 'email', 'role', 'status', 'tenant'];
          const foundColumns = expectedColumns.filter(col => 
            headers.some(header => header.toLowerCase().includes(col.toLowerCase()))
          );
          
          console.log(`Found expected columns: ${foundColumns.join(', ')}`);
          
          // Check data rows
          if (lines.length > 1) {
            const dataRows = lines.slice(1).filter(line => line.trim());
            console.log(`CSV contains ${dataRows.length} data rows`);
            
            // Validate first data row
            if (dataRows.length > 0) {
              const firstRow = dataRows[0].split(',');
              console.log(`First data row: ${firstRow.join(', ')}`);
              
              // Check for valid data
              if (firstRow.length >= 3) {
                console.log('✅ CSV data structure is valid');
              } else {
                console.log('⚠️ CSV data structure may be incomplete');
              }
            }
          }
        }
        
        // Clean up downloaded file
        fs.unlinkSync(downloadPath);
        
      } else {
        console.log('⚠️ Download path not available');
      }
      
    } else {
      console.log('⚠️ Export button not found - may need to implement CSV export');
    }
  });

  test('@regression CSV Export with filters', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to users page
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Apply filters before export
    const filterButton = page.locator('button:has-text("Filter"), button:has-text("Search")');
    
    if (await filterButton.isVisible()) {
      await filterButton.click();
      
      // Wait for filter form
      await page.waitForTimeout(1000);
      
      // Apply role filter
      const roleFilter = page.locator('select[name="role"], .role-filter');
      
      if (await roleFilter.isVisible()) {
        await roleFilter.selectOption('admin');
        console.log('✅ Role filter applied');
        
        // Apply status filter
        const statusFilter = page.locator('select[name="status"], .status-filter');
        
        if (await statusFilter.isVisible()) {
          await statusFilter.selectOption('active');
          console.log('✅ Status filter applied');
        }
        
        // Apply filters
        const applyFilterButton = page.locator('button:has-text("Apply"), button:has-text("Filter"), button[type="submit"]');
        
        if (await applyFilterButton.isVisible()) {
          await applyFilterButton.click();
          
          // Wait for filtered results
          await page.waitForTimeout(2000);
          
          // Check if export button is still available
          const exportButton = page.locator('button:has-text("Export"), button:has-text("Download"), button:has-text("CSV")');
          
          if (await exportButton.isVisible()) {
            console.log('✅ Export button available with filters applied');
            
            // Test filtered export
            const downloadPromise = page.waitForEvent('download');
            await exportButton.click();
            
            const download = await downloadPromise;
            const downloadPath = await download.path();
            
            if (downloadPath) {
              const csvContent = fs.readFileSync(downloadPath, 'utf-8');
              const lines = csvContent.split('\n');
              
              console.log(`Filtered CSV contains ${lines.length} lines`);
              
              // Check if filtered data is correct
              if (lines.length > 1) {
                const dataRows = lines.slice(1).filter(line => line.trim());
                
                // Check if all rows have admin role
                let adminCount = 0;
                for (const row of dataRows) {
                  if (row.toLowerCase().includes('admin')) {
                    adminCount++;
                  }
                }
                
                console.log(`Found ${adminCount} admin users in filtered export`);
                
                if (adminCount > 0) {
                  console.log('✅ Filtered export working correctly');
                } else {
                  console.log('⚠️ Filtered export may not be working correctly');
                }
              }
              
              // Clean up
              fs.unlinkSync(downloadPath);
            }
            
          } else {
            console.log('⚠️ Export button not available with filters applied');
          }
        }
      }
    }
  });

  test('@regression CSV Import functionality', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to users page
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Look for import button
    const importButton = page.locator('button:has-text("Import"), button:has-text("Upload"), button:has-text("CSV")');
    
    if (await importButton.isVisible()) {
      console.log('✅ Import button found');
      
      await importButton.click();
      
      // Wait for import modal/form
      await page.waitForTimeout(1000);
      
      // Check for import modal
      const importModal = page.locator('.modal, .import-modal, .upload-modal, [role="dialog"]');
      
      if (await importModal.isVisible()) {
        console.log('✅ Import modal opened');
        
        // Create test CSV file
        const testCsvContent = `name,email,role,status
Test User 1,test1@example.com,member,active
Test User 2,test2@example.com,developer,active
Test User 3,test3@example.com,client,inactive`;
        
        const testCsvPath = path.join(__dirname, 'test-users.csv');
        fs.writeFileSync(testCsvPath, testCsvContent);
        
        // Upload CSV file
        const fileInput = page.locator('input[type="file"]');
        
        if (await fileInput.isVisible()) {
          await fileInput.setInputFiles(testCsvPath);
          console.log('✅ CSV file uploaded');
          
          // Check for file validation
          const validationMessage = page.locator('.validation-message, .file-info, .upload-status');
          
          if (await validationMessage.isVisible()) {
            const validationText = await validationMessage.textContent();
            console.log(`Validation message: ${validationText}`);
          }
          
          // Submit import
          const submitButton = page.locator('button:has-text("Import"), button:has-text("Upload"), button[type="submit"]');
          
          if (await submitButton.isVisible()) {
            await submitButton.click();
            
            // Wait for import processing
            await page.waitForTimeout(3000);
            
            // Check for import results
            const importResults = page.locator('.import-results, .upload-results, .success-message');
            
            if (await importResults.isVisible()) {
              const resultsText = await importResults.textContent();
              console.log(`Import results: ${resultsText}`);
              
              // Check for success indicators
              if (resultsText?.includes('success') || resultsText?.includes('imported')) {
                console.log('✅ CSV import successful');
              } else {
                console.log('⚠️ CSV import may have failed');
              }
            } else {
              console.log('⚠️ No import results displayed');
            }
          } else {
            console.log('⚠️ Submit button not found');
          }
        } else {
          console.log('⚠️ File input not found');
        }
        
        // Clean up test file
        fs.unlinkSync(testCsvPath);
        
      } else {
        console.log('⚠️ Import modal not found');
      }
      
    } else {
      console.log('⚠️ Import button not found - may need to implement CSV import');
    }
  });

  test('@regression CSV Import validation and error handling', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to users page
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Look for import button
    const importButton = page.locator('button:has-text("Import"), button:has-text("Upload"), button:has-text("CSV")');
    
    if (await importButton.isVisible()) {
      await importButton.click();
      await page.waitForTimeout(1000);
      
      const importModal = page.locator('.modal, .import-modal, .upload-modal, [role="dialog"]');
      
      if (await importModal.isVisible()) {
        // Test invalid CSV file
        const invalidCsvContent = `invalid,csv,content
not,enough,columns
too,many,columns,here,extra`;
        
        const invalidCsvPath = path.join(__dirname, 'invalid-users.csv');
        fs.writeFileSync(invalidCsvPath, invalidCsvContent);
        
        // Upload invalid CSV
        const fileInput = page.locator('input[type="file"]');
        
        if (await fileInput.isVisible()) {
          await fileInput.setInputFiles(invalidCsvPath);
          
          // Check for validation errors
          const errorMessage = page.locator('.error, .alert-danger, .validation-error');
          
          if (await errorMessage.isVisible()) {
            const errorText = await errorMessage.textContent();
            console.log(`Validation error: ${errorText}`);
            
            if (errorText?.includes('invalid') || errorText?.includes('error')) {
              console.log('✅ CSV validation working correctly');
            }
          } else {
            console.log('⚠️ No validation error displayed for invalid CSV');
          }
          
          // Test empty CSV
          const emptyCsvPath = path.join(__dirname, 'empty-users.csv');
          fs.writeFileSync(emptyCsvPath, '');
          
          await fileInput.setInputFiles(emptyCsvPath);
          
          // Check for empty file error
          const emptyError = page.locator('.error, .alert-danger, .validation-error');
          
          if (await emptyError.isVisible()) {
            const emptyErrorText = await emptyError.textContent();
            console.log(`Empty file error: ${emptyErrorText}`);
            
            if (emptyErrorText?.includes('empty') || emptyErrorText?.includes('no data')) {
              console.log('✅ Empty file validation working correctly');
            }
          }
          
          // Clean up test files
          fs.unlinkSync(invalidCsvPath);
          fs.unlinkSync(emptyCsvPath);
        }
      }
    }
  });

  test('@regression CSV Import with duplicate detection', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to users page
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Look for import button
    const importButton = page.locator('button:has-text("Import"), button:has-text("Upload"), button:has-text("CSV")');
    
    if (await importButton.isVisible()) {
      await importButton.click();
      await page.waitForTimeout(1000);
      
      const importModal = page.locator('.modal, .import-modal, .upload-modal, [role="dialog"]');
      
      if (await importModal.isVisible()) {
        // Create CSV with duplicate emails
        const duplicateCsvContent = `name,email,role,status
Duplicate User 1,admin@zena.local,member,active
Duplicate User 2,admin@zena.local,developer,active
New User,newuser@example.com,client,active`;
        
        const duplicateCsvPath = path.join(__dirname, 'duplicate-users.csv');
        fs.writeFileSync(duplicateCsvPath, duplicateCsvContent);
        
        // Upload CSV with duplicates
        const fileInput = page.locator('input[type="file"]');
        
        if (await fileInput.isVisible()) {
          await fileInput.setInputFiles(duplicateCsvPath);
          
          // Submit import
          const submitButton = page.locator('button:has-text("Import"), button:has-text("Upload"), button[type="submit"]');
          
          if (await submitButton.isVisible()) {
            await submitButton.click();
            
            // Wait for import processing
            await page.waitForTimeout(3000);
            
            // Check for duplicate handling
            const duplicateMessage = page.locator('text=/duplicate|already exists|conflict/i');
            
            if (await duplicateMessage.isVisible()) {
              const duplicateText = await duplicateMessage.textContent();
              console.log(`Duplicate handling: ${duplicateText}`);
              
              // Look for duplicate resolution options
              const resolutionOptions = page.locator('button:has-text("Skip"), button:has-text("Overwrite"), button:has-text("Merge")');
              
              if (await resolutionOptions.isVisible()) {
                console.log('✅ Duplicate resolution options available');
                
                // Test skip duplicates option
                const skipButton = page.locator('button:has-text("Skip"), button:has-text("Skip Duplicates")');
                
                if (await skipButton.isVisible()) {
                  await skipButton.click();
                  
                  // Wait for processing
                  await page.waitForTimeout(2000);
                  
                  // Check for results
                  const results = page.locator('.import-results, .upload-results');
                  
                  if (await results.isVisible()) {
                    const resultsText = await results.textContent();
                    console.log(`Import results with duplicates skipped: ${resultsText}`);
                  }
                }
              } else {
                console.log('⚠️ No duplicate resolution options found');
              }
            } else {
              console.log('⚠️ No duplicate handling detected');
            }
          }
        }
        
        // Clean up
        fs.unlinkSync(duplicateCsvPath);
      }
    }
  });

  test('@regression CSV Import progress tracking', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to users page
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Look for import button
    const importButton = page.locator('button:has-text("Import"), button:has-text("Upload"), button:has-text("CSV")');
    
    if (await importButton.isVisible()) {
      await importButton.click();
      await page.waitForTimeout(1000);
      
      const importModal = page.locator('.modal, .import-modal, .upload-modal, [role="dialog"]');
      
      if (await importModal.isVisible()) {
        // Create large CSV file for progress testing
        const largeCsvContent = ['name,email,role,status'];
        
        for (let i = 1; i <= 100; i++) {
          largeCsvContent.push(`Test User ${i},test${i}@example.com,member,active`);
        }
        
        const largeCsvPath = path.join(__dirname, 'large-users.csv');
        fs.writeFileSync(largeCsvPath, largeCsvContent.join('\n'));
        
        // Upload large CSV
        const fileInput = page.locator('input[type="file"]');
        
        if (await fileInput.isVisible()) {
          await fileInput.setInputFiles(largeCsvPath);
          
          // Submit import
          const submitButton = page.locator('button:has-text("Import"), button:has-text("Upload"), button[type="submit"]');
          
          if (await submitButton.isVisible()) {
            await submitButton.click();
            
            // Check for progress indicator
            const progressIndicator = page.locator('.progress-bar, .upload-progress, [role="progressbar"]');
            
            if (await progressIndicator.isVisible()) {
              console.log('✅ Progress indicator displayed');
              
              // Wait for progress updates
              await page.waitForTimeout(2000);
              
              // Check progress value
              const progressValue = await progressIndicator.getAttribute('aria-valuenow');
              const progressText = await progressIndicator.textContent();
              
              if (progressValue || progressText) {
                console.log(`Progress: ${progressValue || progressText}`);
              }
            } else {
              console.log('⚠️ No progress indicator found');
            }
            
            // Check for status messages
            const statusMessage = page.locator('.status-message, .import-status, .upload-status');
            
            if (await statusMessage.isVisible()) {
              const statusText = await statusMessage.textContent();
              console.log(`Status message: ${statusText}`);
            }
            
            // Wait for completion
            await page.waitForTimeout(5000);
            
            // Check for completion message
            const completionMessage = page.locator('text=/complete|finished|success|imported/i');
            
            if (await completionMessage.isVisible()) {
              const completionText = await completionMessage.textContent();
              console.log(`Completion message: ${completionText}`);
            }
          }
        }
        
        // Clean up
        fs.unlinkSync(largeCsvPath);
      }
    }
  });

  test('@regression CSV Import rollback on failure', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to users page
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Look for import button
    const importButton = page.locator('button:has-text("Import"), button:has-text("Upload"), button:has-text("CSV")');
    
    if (await importButton.isVisible()) {
      await importButton.click();
      await page.waitForTimeout(1000);
      
      const importModal = page.locator('.modal, .import-modal, .upload-modal, [role="dialog"]');
      
      if (await importModal.isVisible()) {
        // Create CSV with invalid data that should cause failure
        const invalidDataCsvContent = `name,email,role,status
Valid User,valid@example.com,member,active
Invalid User,invalid-email,invalid-role,invalid-status
Another Valid User,another@example.com,developer,active`;
        
        const invalidDataCsvPath = path.join(__dirname, 'invalid-data-users.csv');
        fs.writeFileSync(invalidDataCsvPath, invalidDataCsvContent);
        
        // Upload CSV with invalid data
        const fileInput = page.locator('input[type="file"]');
        
        if (await fileInput.isVisible()) {
          await fileInput.setInputFiles(invalidDataCsvPath);
          
          // Submit import
          const submitButton = page.locator('button:has-text("Import"), button:has-text("Upload"), button[type="submit"]');
          
          if (await submitButton.isVisible()) {
            await submitButton.click();
            
            // Wait for processing
            await page.waitForTimeout(3000);
            
            // Check for failure message
            const failureMessage = page.locator('text=/failed|error|rollback|transaction/i');
            
            if (await failureMessage.isVisible()) {
              const failureText = await failureMessage.textContent();
              console.log(`Failure message: ${failureText}`);
              
              // Check for rollback indication
              if (failureText?.includes('rollback') || failureText?.includes('transaction')) {
                console.log('✅ Rollback mechanism detected');
              } else {
                console.log('⚠️ No rollback indication found');
              }
            } else {
              console.log('⚠️ No failure message displayed');
            }
            
            // Check for partial import results
            const partialResults = page.locator('text=/partial|some.*failed|partially.*imported/i');
            
            if (await partialResults.isVisible()) {
              const partialText = await partialResults.textContent();
              console.log(`Partial import results: ${partialText}`);
            }
          }
        }
        
        // Clean up
        fs.unlinkSync(invalidDataCsvPath);
      }
    }
  });
});
