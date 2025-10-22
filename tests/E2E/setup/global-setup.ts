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
  const envFileVars = parseEnvFile(ENV_FILE_PATH);
  const overrides: EnvMap = {
    APP_ENV: envFileVars.APP_ENV ?? 'e2e',
    APP_DEBUG: envFileVars.APP_DEBUG ?? 'true',
    CACHE_DRIVER: envFileVars.CACHE_DRIVER ?? 'array',
    SESSION_DRIVER: envFileVars.SESSION_DRIVER ?? 'array',
    QUEUE_CONNECTION: envFileVars.QUEUE_CONNECTION ?? 'sync',
    MAIL_MAILER: envFileVars.MAIL_MAILER ?? 'log',
  };

  if (!envFileVars.DB_CONNECTION) {
    overrides.DB_CONNECTION = 'sqlite';
    overrides.DB_DATABASE = path.join(PROJECT_ROOT, 'database', 'database.sqlite');
  }

  return {
    ...process.env,
    ...envFileVars,
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
  runArtisan('config:clear', artisanEnv);
  runArtisan('cache:clear', artisanEnv);
  runArtisan('view:clear', artisanEnv);

  prepareStorage(artisanEnv);

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
