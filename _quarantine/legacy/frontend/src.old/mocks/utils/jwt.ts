// Mock JWT utilities cho development
// Trong production, JWT sẽ được xử lý bởi backend

export interface JWTPayload {
  user_id: number;
  tenant_id: number;
  roles: string[];
  iat?: number;
  exp?: number;
}

/**
 * Tạo mock JWT token
 * Chỉ dùng cho development, không bảo mật
 */
export function generateMockJWT(payload: Omit<JWTPayload, 'iat' | 'exp'>): string {
  const now = Math.floor(Date.now() / 1000);
  const fullPayload: JWTPayload = {
    ...payload,
    iat: now,
    exp: now + 3600 // 1 hour
  };

  // Encode payload as base64 (không bảo mật, chỉ cho mock)
  const encodedPayload = btoa(JSON.stringify(fullPayload));
  
  // Mock JWT format: header.payload.signature
  return `mock.${encodedPayload}.signature`;
}

/**
 * Giải mã và verify mock JWT token
 * Chỉ dùng cho development
 */
export function verifyMockJWT(token: string): JWTPayload | null {
  try {
    const parts = token.split('.');
    
    if (parts.length !== 3 || parts[0] !== 'mock') {
      return null;
    }

    const payload = JSON.parse(atob(parts[1])) as JWTPayload;
    
    // Kiểm tra expiration
    const now = Math.floor(Date.now() / 1000);
    if (payload.exp && payload.exp < now) {
      return null;
    }

    return payload;
  } catch (error) {
    return null;
  }
}

/**
 * Lấy payload từ token mà không verify
 * Dùng để debug
 */
export function decodeMockJWT(token: string): JWTPayload | null {
  try {
    const parts = token.split('.');
    
    if (parts.length !== 3) {
      return null;
    }

    return JSON.parse(atob(parts[1])) as JWTPayload;
  } catch (error) {
    return null;
  }
}