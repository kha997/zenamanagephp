import type { FullConfig } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

type EnvMap = Record<string, string>;

const PROJECT_ROOT = path.resolve(__dirname, '../../..');
const ENV_FILE_PATH = path.join(PROJECT_ROOT, '.env.e2e');

function parseEnvFile(filePath: string): EnvMap {
  if (!fs.existsSync(filePath)) {
    return {};
  }

  const content = fs.readFileSync(filePath, 'utf8');
  const lines = content.split(/\r?\n/);
  const envVars: EnvMap = {};

  for (const rawLine of lines) {
    const line = rawLine.trim();
    if (!line || line.startsWith('#')) {
      continue;
    }

    const eqIndex = line.indexOf('=');
    if (eqIndex === -1) {
      continue;
    }

    const key = line.slice(0, eqIndex).trim();
    let value = line.slice(eqIndex + 1).trim();

    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1);
    }

    envVars[key] = value;
  }

  const expandValue = (value: string, visited: Set<string>): string => {
    return value.replace(/\${([^}]+)}/g, (_, group: string) => {
      if (visited.has(group)) {
        return '';
      }
      visited.add(group);
      const replacement =
        envVars[group] ??
        process.env[group] ??
        '';
      return expandValue(replacement, visited);
    });
  };

  for (const [key, value] of Object.entries(envVars)) {
    envVars[key] = expandValue(value, new Set([key]));
  }

  return envVars;
}

function runArtisan(command: string, env: NodeJS.ProcessEnv) {
  execSync(`php artisan ${command} --no-interaction`, {
    cwd: PROJECT_ROOT,
    env,
    stdio: 'inherit',
  });
}

function ensureDirectory(directory: string, reset = false) {
  const absolutePath = path.join(PROJECT_ROOT, directory);

  if (reset) {
    fs.rmSync(absolutePath, { recursive: true, force: true });
  }

  fs.mkdirSync(absolutePath, { recursive: true });
}

function prepareStorage(env: NodeJS.ProcessEnv) {
  const directoriesToReset = [
    'storage/app/documents',
    'storage/app/public/documents',
    'storage/app/public/e2e',
    'storage/app/uploads/e2e',
  ];

  const directoriesToEnsure = [
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
  ];

  directoriesToReset.forEach((dir) => ensureDirectory(dir, true));
  directoriesToEnsure.forEach((dir) => ensureDirectory(dir));

  const logFile = path.join(PROJECT_ROOT, 'storage/logs/laravel.log');
  if (!fs.existsSync(logFile)) {
    fs.closeSync(fs.openSync(logFile, 'w'));
  }

  const publicStorage = path.join(PROJECT_ROOT, 'public', 'storage');
  if (!fs.existsSync(publicStorage)) {
    try {
      runArtisan('storage:link', env);
    } catch (error) {
      console.warn('storage:link failed (possibly already exists):', (error as Error).message);
    }
  }
}

function buildArtisanEnv(): NodeJS.ProcessEnv {
  // Also check .env file (created by workflow)
  const envFilePath = path.join(PROJECT_ROOT, '.env');
  const envE2EFileVars = parseEnvFile(ENV_FILE_PATH);
  const envFileVars = parseEnvFile(envFilePath);
  
  // Merge: .env.e2e takes precedence, but check .env and process.env too
  const mergedEnvVars: EnvMap = {
    ...envFileVars, // From .env (workflow created)
    ...envE2EFileVars, // From .env.e2e (overrides)
  };
  
  const overrides: EnvMap = {
    APP_ENV: mergedEnvVars.APP_ENV ?? process.env.APP_ENV ?? 'e2e',
    APP_DEBUG: mergedEnvVars.APP_DEBUG ?? process.env.APP_DEBUG ?? 'true',
    CACHE_DRIVER: mergedEnvVars.CACHE_DRIVER ?? process.env.CACHE_DRIVER ?? 'array',
    SESSION_DRIVER: mergedEnvVars.SESSION_DRIVER ?? process.env.SESSION_DRIVER ?? 'array',
    QUEUE_CONNECTION: mergedEnvVars.QUEUE_CONNECTION ?? process.env.QUEUE_CONNECTION ?? 'sync',
    MAIL_MAILER: mergedEnvVars.MAIL_MAILER ?? process.env.MAIL_MAILER ?? 'log',
  };

  // Only override DB_CONNECTION if not already set in .env, .env.e2e, or process.env
  // This allows workflow to configure MySQL while local tests can use SQLite
  if (!mergedEnvVars.DB_CONNECTION && !process.env.DB_CONNECTION) {
    console.log('⚠️  No DB_CONNECTION found, defaulting to SQLite for local E2E tests');
    overrides.DB_CONNECTION = 'sqlite';
    overrides.DB_DATABASE = path.join(PROJECT_ROOT, 'database', 'database.sqlite');
  } else {
    // Use the DB config from .env or process.env (set by workflow)
    if (mergedEnvVars.DB_CONNECTION) {
      overrides.DB_CONNECTION = mergedEnvVars.DB_CONNECTION;
      if (mergedEnvVars.DB_DATABASE) {
        overrides.DB_DATABASE = mergedEnvVars.DB_DATABASE;
      }
      if (mergedEnvVars.DB_HOST) {
        overrides.DB_HOST = mergedEnvVars.DB_HOST;
      }
      if (mergedEnvVars.DB_PORT) {
        overrides.DB_PORT = mergedEnvVars.DB_PORT;
      }
      if (mergedEnvVars.DB_USERNAME) {
        overrides.DB_USERNAME = mergedEnvVars.DB_USERNAME;
      }
      if (mergedEnvVars.DB_PASSWORD) {
        overrides.DB_PASSWORD = mergedEnvVars.DB_PASSWORD;
      }
    } else if (process.env.DB_CONNECTION) {
      // Use from process.env (CI/workflow)
      overrides.DB_CONNECTION = process.env.DB_CONNECTION;
      if (process.env.DB_DATABASE) overrides.DB_DATABASE = process.env.DB_DATABASE;
      if (process.env.DB_HOST) overrides.DB_HOST = process.env.DB_HOST;
      if (process.env.DB_PORT) overrides.DB_PORT = process.env.DB_PORT;
      if (process.env.DB_USERNAME) overrides.DB_USERNAME = process.env.DB_USERNAME;
      if (process.env.DB_PASSWORD) overrides.DB_PASSWORD = process.env.DB_PASSWORD;
    }
  }

  return {
    ...process.env,
    ...mergedEnvVars,
    ...overrides,
  };
}

function applyCryptoPolyfill() {
  const hasCrypto = typeof globalThis.crypto !== 'undefined';
  if (!hasCrypto) {
    (globalThis as any).crypto = {};
  }

  if (!(globalThis.crypto as any).randomUUID) {
    (globalThis.crypto as any).randomUUID = function randomUUID() {
      return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
      });
    };
  }

  if (!(globalThis.crypto as any).getRandomValues) {
    (globalThis.crypto as any).getRandomValues = function getRandomValues(array: any) {
      for (let i = 0; i < array.length; i++) {
        array[i] = Math.floor(Math.random() * 256);
      }
      return array;
    };
  }
}

export default async function globalSetup(config: FullConfig) {
  const artisanEnv = buildArtisanEnv();

  console.log('🧹 Clearing cached configuration before E2E run...');
  console.log(`   📊 DB Connection: ${artisanEnv.DB_CONNECTION || 'not set'}`);
  if (artisanEnv.DB_CONNECTION === 'mysql') {
    console.log(`   🗄️  MySQL Host: ${artisanEnv.DB_HOST}:${artisanEnv.DB_PORT}`);
    console.log(`   📂 Database: ${artisanEnv.DB_DATABASE}`);
  } else if (artisanEnv.DB_CONNECTION === 'sqlite') {
    console.log(`   📂 SQLite DB: ${artisanEnv.DB_DATABASE}`);
  }
  
  runArtisan('config:clear', artisanEnv);
  runArtisan('cache:clear', artisanEnv);
  runArtisan('view:clear', artisanEnv);

  prepareStorage(artisanEnv);

  // Only run migrations if DB_CONNECTION is set (allow workflow to handle migrations)
  // In CI/workflow, migrations are run by workflow before this setup
  // In local tests, we need to run migrations here
  const shouldRunMigrations = !process.env.CI || process.env.E2E_RUN_MIGRATIONS === 'true';
  
  if (shouldRunMigrations) {
    console.log('🔄 Resetting database for E2E suite...');
    try {
      runArtisan('migrate:fresh', artisanEnv);
    } catch (error) {
      console.error('Failed to run migrate:fresh for E2E setup.');
      throw error;
    }

    console.log('🌱 Seeding dedicated E2E dataset...');
    try {
      runArtisan('db:seed --class="Database\\Seeders\\E2EDatabaseSeeder"', artisanEnv);
    } catch (error) {
      console.error('Failed to seed E2E database.');
      throw error;
    }
  } else {
    console.log('⏭️  Skipping migrations (already run by workflow)');
  }

  console.log('✉️ Ensuring mailer is configured for test logging.');
  artisanEnv.MAIL_MAILER = 'log';
  
  console.log('🔧 Applying crypto polyfill for Node.js compatibility...');
  applyCryptoPolyfill();
  
  console.log('✅ E2E environment setup completed successfully!');
  console.log('📊 Test data available:');
  console.log('   - 2 Tenants: ZENA Company, TTF Company');
  console.log('   - 10 Users: 5 ZENA + 5 TTF users with different roles');
  console.log('   - 5 Roles: Owner, Admin, PM, Dev, Guest');
  console.log('   - 2 Projects: E2E-001, E2E-002');
  console.log('   - All users password: "password"');
}
