import { APIRequestContext, expect } from '@playwright/test';
import crypto from 'node:crypto';

export type AuthSession = {
  token: string;
  user: {
    id: string;
    email: string;
    name: string;
    tenant_id: string;
    role?: string;
  };
};

type LoginOptions = {
  remember?: boolean;
};

const jsonHeaders = {
  Accept: 'application/json',
  'Content-Type': 'application/json',
};

export async function login(
  request: APIRequestContext,
  email: string,
  password: string,
  options: LoginOptions = {}
): Promise<AuthSession> {
  const response = await request.post('/api/v1/auth/login', {
    headers: jsonHeaders,
    data: {
      email,
      password,
      remember: options.remember ?? false,
    },
  });

  const body = await parseJson(response);
  expect(response.status(), 'login should return 200').toBe(200);
  expect(body?.status ?? body?.success).toBeTruthy();
  expect(body?.data?.token, 'login response should include token').toBeTruthy();

  return {
    token: body.data.token as string,
    user: {
      id: body.data.user.id,
      email: body.data.user.email,
      name: body.data.user.name,
      tenant_id: body.data.user.tenant_id,
      role: body.data.user.role,
    },
  };
}

export function authHeaders(token: string): Record<string, string> {
  return {
    ...jsonHeaders,
    Authorization: `Bearer ${token}`,
    'X-Request-Id': crypto.randomUUID(),
  };
}

export async function parseJson(response: APIResponse): Promise<any> {
  const contentType = response.headers()['content-type'] ?? '';
  if (!contentType.includes('application/json')) {
    const text = await response.text();
    // If it's HTML, it might be a redirect or error page
    if (contentType.includes('text/html')) {
      throw new Error(`Expected JSON response, received HTML: ${text.substring(0, 200)}...`);
    }
    throw new Error(`Expected JSON response, received: ${text}`);
  }

  return response.json();
}

export async function expectSuccess(response: APIResponse, status = 200) {
  expect(response.status()).toBe(status);
  const body = await parseJson(response);
  expect(body?.success ?? body?.status === 'success').toBeTruthy();
  return body;
}

export async function expectError(response: APIResponse, status: number) {
  expect(response.status()).toBe(status);
  return parseJson(response);
}

export function uniqueName(prefix: string): string {
  return `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2, 8)}`;
}

type APIResponse = Awaited<ReturnType<APIRequestContext['get']>>;
