import { http, HttpResponse } from 'msw';
import { mockUsers } from '../data/users';
import { generateMockJWT, verifyMockJWT } from '../utils/jwt';

const API_BASE = '/api/v1';

export const authHandlers = [
  // POST /api/v1/auth/login
  http.post(`${API_BASE}/auth/login`, async ({ request }) => {
    const { email, password } = await request.json() as { email: string; password: string };
    
    // Tìm user trong mock data
    const user = mockUsers.find(u => u.email === email);
    
    if (!user || password !== 'password123') {
      return HttpResponse.json(
        {
          status: 'error',
          message: 'Email hoặc mật khẩu không đúng'
        },
        { status: 401 }
      );
    }

    // Tạo JWT token
    const token = generateMockJWT({
      user_id: user.id,
      tenant_id: user.tenant_id,
      roles: user.system_roles
    });

    return HttpResponse.json({
      status: 'success',
      data: {
        user: {
          id: user.id,
          name: user.name,
          email: user.email,
          tenant_id: user.tenant_id,
          system_roles: user.system_roles
        },
        token,
        expires_in: 3600
      }
    });
  }),

  // POST /api/v1/auth/logout
  http.post(`${API_BASE}/auth/logout`, () => {
    return HttpResponse.json({
      status: 'success',
      data: {
        message: 'Đăng xuất thành công'
      }
    });
  }),

  // GET /api/v1/auth/me
  http.get(`${API_BASE}/auth/me`, ({ request }) => {
    const authHeader = request.headers.get('Authorization');
    
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return HttpResponse.json(
        {
          status: 'error',
          message: 'Token không hợp lệ'
        },
        { status: 401 }
      );
    }

    const token = authHeader.substring(7);
    const payload = verifyMockJWT(token);
    
    if (!payload) {
      return HttpResponse.json(
        {
          status: 'error',
          message: 'Token đã hết hạn'
        },
        { status: 401 }
      );
    }

    const user = mockUsers.find(u => u.id === payload.user_id);
    
    if (!user) {
      return HttpResponse.json(
        {
          status: 'error',
          message: 'Người dùng không tồn tại'
        },
        { status: 404 }
      );
    }

    return HttpResponse.json({
      status: 'success',
      data: {
        id: user.id,
        name: user.name,
        email: user.email,
        tenant_id: user.tenant_id,
        system_roles: user.system_roles
      }
    });
  }),

  // POST /api/v1/auth/refresh
  http.post(`${API_BASE}/auth/refresh`, ({ request }) => {
    const authHeader = request.headers.get('Authorization');
    
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return HttpResponse.json(
        {
          status: 'error',
          message: 'Token không hợp lệ'
        },
        { status: 401 }
      );
    }

    const token = authHeader.substring(7);
    const payload = verifyMockJWT(token);
    
    if (!payload) {
      return HttpResponse.json(
        {
          status: 'error',
          message: 'Token đã hết hạn'
        },
        { status: 401 }
      );
    }

    // Tạo token mới
    const newToken = generateMockJWT({
      user_id: payload.user_id,
      tenant_id: payload.tenant_id,
      roles: payload.roles
    });

    return HttpResponse.json({
      status: 'success',
      data: {
        token: newToken,
        expires_in: 3600
      }
    });
  })
];