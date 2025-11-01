import { FullConfig } from '@playwright/test';
import { execSync } from 'child_process';
import { existsSync, mkdirSync, writeFileSync } from 'fs';
import { join } from 'path';

/**
 * Phase 3 Global Setup
 * 
 * Prepares the environment for Phase 3 E2E tests:
 * - Seeds Phase 3 test data
 * - Sets up test files and directories
 * - Configures test environment
 */
async function globalSetup(config: FullConfig) {
  console.log('🚀 Starting Phase 3 E2E test setup...');

  try {
    // 1. Seed Phase 3 test data
    await seedPhase3TestData();

    // 2. Create test fixtures and directories
    await createTestDirectories();

    // 3. Create test files
    await createTestFiles();

    // 4. Verify test environment
    await verifyTestEnvironment();

    console.log('✅ Phase 3 E2E test setup completed successfully!');
  } catch (error) {
    console.error('❌ Phase 3 E2E test setup failed:', error);
    throw error;
  }
}

/**
 * Seed Phase 3 test data
 */
async function seedPhase3TestData(): Promise<void> {
  console.log('📊 Seeding Phase 3 test data...');

  try {
    // Run the Phase 3 test data seeder
    execSync('php artisan db:seed --class=Phase3TestDataSeeder', {
      stdio: 'inherit',
      cwd: process.cwd(),
    });

    console.log('✅ Phase 3 test data seeded successfully');
  } catch (error) {
    console.error('❌ Failed to seed Phase 3 test data:', error);
    throw error;
  }
}

/**
 * Create test directories
 */
async function createTestDirectories(): Promise<void> {
  console.log('📁 Creating test directories...');

  const directories = [
    'tests/fixtures',
    'tests/fixtures/attachments',
    'tests/fixtures/documents',
    'tests/fixtures/images',
    'test-results/phase3-artifacts',
    'test-results/phase3-artifacts/screenshots',
    'test-results/phase3-artifacts/videos',
    'test-results/phase3-artifacts/traces',
  ];

  directories.forEach(dir => {
    if (!existsSync(dir)) {
      mkdirSync(dir, { recursive: true });
      console.log(`✅ Created directory: ${dir}`);
    }
  });
}

/**
 * Create test files
 */
async function createTestFiles(): Promise<void> {
  console.log('📄 Creating test files...');

  // Create test document
  const testDocumentPath = join('tests', 'fixtures', 'test-document.txt');
  if (!existsSync(testDocumentPath)) {
    const testContent = `Phase 3 E2E Test Document

This is a test document created for Phase 3 E2E testing.

Features tested:
- File attachment upload
- File attachment download
- File attachment categorization
- File attachment versioning

Created: ${new Date().toISOString()}
Test Environment: Phase 3 E2E Tests
`;

    writeFileSync(testDocumentPath, testContent);
    console.log(`✅ Created test document: ${testDocumentPath}`);
  }

  // Create test image (simple SVG)
  const testImagePath = join('tests', 'fixtures', 'test-image.svg');
  if (!existsSync(testImagePath)) {
    const svgContent = `<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg">
  <rect width="100" height="100" fill="#4F46E5"/>
  <text x="50" y="50" text-anchor="middle" fill="white" font-family="Arial" font-size="12">Phase 3</text>
</svg>`;

    writeFileSync(testImagePath, svgContent);
    console.log(`✅ Created test image: ${testImagePath}`);
  }

  // Create test PDF (simple HTML that can be converted)
  const testPdfPath = join('tests', 'fixtures', 'test-report.html');
  if (!existsSync(testPdfPath)) {
    const htmlContent = `<!DOCTYPE html>
<html>
<head>
    <title>Phase 3 Test Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #4F46E5; }
        .test-info { background: #F3F4F6; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Phase 3 E2E Test Report</h1>
    <div class="test-info">
        <p><strong>Test Date:</strong> ${new Date().toISOString()}</p>
        <p><strong>Test Environment:</strong> Phase 3 E2E Tests</p>
        <p><strong>Features Tested:</strong></p>
        <ul>
            <li>Frontend comment UI integration</li>
            <li>Kanban React board with ULID schema</li>
            <li>File attachments system</li>
            <li>Real-time updates</li>
        </ul>
    </div>
    <p>This is a test document for Phase 3 E2E testing.</p>
</body>
</html>`;

    writeFileSync(testPdfPath, htmlContent);
    console.log(`✅ Created test report: ${testPdfPath}`);
  }
}

/**
 * Verify test environment
 */
async function verifyTestEnvironment(): Promise<void> {
  console.log('🔍 Verifying test environment...');

  try {
    // Check if Laravel application is running
    const response = await fetch('http://127.0.0.1:8000');
    if (!response.ok) {
      throw new Error(`Laravel application not responding: ${response.status}`);
    }
    console.log('✅ Laravel application is running');

    // Check if database is accessible
    execSync('php artisan migrate:status', {
      stdio: 'pipe',
      cwd: process.cwd(),
    });
    console.log('✅ Database is accessible');

    // Check if test data exists
    execSync('php artisan tinker --execute="echo App\\Models\\Task::count();"', {
      stdio: 'pipe',
      cwd: process.cwd(),
    });
    console.log('✅ Test data is available');

    // Check if Pusher configuration exists
    const envPath = join(process.cwd(), '.env');
    if (existsSync(envPath)) {
      const envContent = require('fs').readFileSync(envPath, 'utf8');
      if (envContent.includes('PUSHER_APP_KEY') && envContent.includes('PUSHER_APP_SECRET')) {
        console.log('✅ Pusher configuration found');
      } else {
        console.log('⚠️  Pusher configuration not found - real-time tests may not work');
      }
    }

  } catch (error) {
    console.error('❌ Test environment verification failed:', error);
    throw error;
  }
}

export default globalSetup;
