import type { FullConfig } from '@playwright/test';
import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';

const PROJECT_ROOT = path.resolve(__dirname, '../../../..');

async function globalSetup(config: FullConfig) {
  const baseURL = config.projects[0].use?.baseURL || 'http://127.0.0.1:8000';
  
  console.log('🧹 Preparing authentication test environment...');
  
  // Set test environment
  const artisanEnv = {
    ...process.env,
    APP_ENV: 'testing',
    DB_CONNECTION: process.env.DB_MODE || 'sqlite',
    DB_DATABASE: process.env.DB_MODE === 'mysql' 
      ? process.env.DB_DATABASE 
      : path.join(PROJECT_ROOT, 'database/database.sqlite'),
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
    execSync(`php artisan db:seed --class=AuthE2ESeeder --env=testing`, { 
      cwd: PROJECT_ROOT, 
      env: artisanEnv,
      stdio: 'inherit',
    });
  } catch (error) {
    console.warn('  → Seeder failed, test will seed on the fly');
  }
  
  console.log('✅ Authentication test environment ready!');
}

export default globalSetup;

