import { rest } from 'msw';

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8003/api/v1';

export const handlers = [
  // Auth endpoints
  rest.post(`${API_BASE_URL}/auth/login`, (req, res, ctx) => {
    return res(
      ctx.status(200),
      ctx.json({
        status: 'success',
        data: {
          user: {
            id: 1,
            name: 'Test User',
            email: 'test@example.com',
          },
          token: 'mock-jwt-token',
        },
      })
    );
  }),

  rest.post(`${API_BASE_URL}/auth/register`, (req, res, ctx) => {
    return res(
      ctx.status(201),
      ctx.json({
        status: 'success',
        data: {
          user: {
            id: 2,
            name: 'New User',
            email: 'newuser@example.com',
          },
          token: 'mock-jwt-token',
        },
      })
    );
  }),

  rest.post(`${API_BASE_URL}/auth/logout`, (req, res, ctx) => {
    return res(
      ctx.status(200),
      ctx.json({
        status: 'success',
        data: { message: 'Logged out successfully' },
      })
    );
  }),

  rest.get(`${API_BASE_URL}/auth/me`, (req, res, ctx) => {
    const authHeader = req.headers.get('Authorization');
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return res(
        ctx.status(401),
        ctx.json({
          status: 'error',
          message: 'Unauthorized',
        })
      );
    }

    return res(
      ctx.status(200),
      ctx.json({
        status: 'success',
        data: {
          user: {
            id: 1,
            name: 'Test User',
            email: 'test@example.com',
          },
        },
      })
    );
  }),

  // Projects endpoints
  rest.get(`${API_BASE_URL}/projects`, (req, res, ctx) => {
    return res(
      ctx.status(200),
      ctx.json({
        status: 'success',
        data: {
          projects: [
            {
              id: 1,
              name: 'Sample Project',
              description: 'A sample project for testing',
              status: 'active',
              progress: 45,
            },
          ],
        },
      })
    );
  }),

  // Test endpoint
  rest.get(`${API_BASE_URL}/test`, (req, res, ctx) => {
    return res(
      ctx.status(200),
      ctx.json({
        status: 'success',
        data: { message: 'API is working!' },
      })
    );
  }),
];