import type { FullConfig } from '@playwright/test';
import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';

const PROJECT_ROOT = path.resolve(__dirname, '../../../..');

async function globalSetup(config: FullConfig) {
  const baseURL = config.projects[0].use?.baseURL || 'http://127.0.0.1:8000';
  
  console.log('🧹 Preparing authentication test environment...');
  
  // Set test environment
  // Round 158: Default to MySQL to match webServer command and global-setup
  const dbMode = process.env.DB_MODE || process.env.DB_CONNECTION || 'mysql';
  const artisanEnv = {
    ...process.env,
    APP_ENV: 'testing',
    DB_CONNECTION: dbMode,
    DB_DATABASE: dbMode === 'mysql' 
      ? (process.env.DB_DATABASE || 'zenamanage_test')
      : path.join(PROJECT_ROOT, 'database/database.sqlite'),
    DB_HOST: dbMode === 'mysql' ? (process.env.DB_HOST || '127.0.0.1') : undefined,
    DB_PORT: dbMode === 'mysql' ? (process.env.DB_PORT || '3306') : undefined,
    DB_USERNAME: dbMode === 'mysql' ? (process.env.DB_USERNAME || 'root') : undefined,
    DB_PASSWORD: dbMode === 'mysql' ? (process.env.DB_PASSWORD || '') : undefined,
  };
  
  // Clear caches
  console.log('  → Clearing caches...');
  try {
    execSync('php artisan config:clear', { cwd: PROJECT_ROOT, stdio: 'inherit' });
    execSync('php artisan cache:clear', { cwd: PROJECT_ROOT, stdio: 'inherit' });
    execSync('php artisan view:clear', { cwd: PROJECT_ROOT, stdio: 'inherit' });
  } catch (error) {
    console.warn('  → Cache clear failed (may already be clear)');
  }
  
  // Reset and seed database
  console.log('  → Resetting database...');
  try {
    execSync(`php artisan migrate:fresh --env=testing`, { 
      cwd: PROJECT_ROOT, 
      env: artisanEnv,
      stdio: 'inherit',
    });
  } catch (error) {
    console.error('  ✗ Database reset failed');
    throw error;
  }
  
  console.log('  → Seeding test users...');
  try {
    // Round 161: Use E2EDatabaseSeeder which creates admin@zena.local / password
    execSync(`php artisan db:seed --class="Database\\Seeders\\E2EDatabaseSeeder" --env=testing`, { 
      cwd: PROJECT_ROOT, 
      env: artisanEnv,
      stdio: 'inherit',
    });
  } catch (error) {
    console.error('  ✗ Seeder failed:', (error as Error).message);
    throw error;
  }
  
  console.log('✅ Authentication test environment ready!');
}

export default globalSetup;

