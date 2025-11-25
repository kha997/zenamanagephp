import { http, HttpResponse } from 'msw';
import { mockProjects } from '../data/projects';
import { verifyMockJWT } from '../utils/jwt';

const API_BASE = '/api/v1';

// Helper function để kiểm tra authentication
function checkAuth(request: Request) {
  const authHeader = request.headers.get('Authorization');
  
  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    return null;
  }

  const token = authHeader.substring(7);
  return verifyMockJWT(token);
}

export const projectHandlers = [
  // GET /api/v1/projects
  http.get(`${API_BASE}/projects`, ({ request }) => {
    const payload = checkAuth(request);
    
    if (!payload) {
      return HttpResponse.json(
        {
          status: 'error',
          message: 'Unauthorized'
        },
        { status: 401 }
      );
    }

    const url = new URL(request.url);
    const page = parseInt(url.searchParams.get('page') || '1');
    const limit = parseInt(url.searchParams.get('limit') || '10');
    const search = url.searchParams.get('search') || '';
    const status = url.searchParams.get('status');

    // Lọc projects theo tenant_id
    let filteredProjects = mockProjects.filter(p => p.tenant_id === payload.tenant_id);

    // Lọc theo search
    if (search) {
      filteredProjects = filteredProjects.filter(p => 
        p.name.toLowerCase().includes(search.toLowerCase()) ||
        p.description.toLowerCase().includes(search.toLowerCase())
      );
    }

    // Lọc theo status
    if (status) {
      filteredProjects = filteredProjects.filter(p => p.status === status);
    }

    // Phân trang
    const total = filteredProjects.length;
    const startIndex = (page - 1) * limit;
    const endIndex = startIndex + limit;
    const paginatedProjects = filteredProjects.slice(startIndex, endIndex);

    return HttpResponse.json({
      status: 'success',
      data: {
        projects: paginatedProjects,
        pagination: {
          current_page: page,
          per_page: limit,
          total,
          last_page: Math.ceil(total / limit),
          from: startIndex + 1,
          to: Math.min(endIndex, total)
        }
      }
    });
  }),

  // GET /api/v1/projects/:id
  http.get(`${API_BASE}/projects/:id`, ({ request, params }) => {
    const payload = checkAuth(request);
    
    if (!payload) {
      return HttpResponse.json(
        {
          status: 'error',
          message: 'Unauthorized'
        },
        { status: 401 }
      );
    }

    const projectId = parseInt(params.id as string);
    const project = mockProjects.find(p => 
      p.id === projectId && p.tenant_id === payload.tenant_id
    );

    if (!project) {
      return HttpResponse.json(
        {
          status: 'error',
          message: 'Dự án không tồn tại'
        },
        { status: 404 }
      );
    }

    return HttpResponse.json({
      status: 'success',
      data: project
    });
  }),

  // POST /api/v1/projects
  http.post(`${API_BASE}/projects`, async ({ request }) => {
    const payload = checkAuth(request);
    
    if (!payload) {
      return HttpResponse.json(
        {
          status: 'error',
          message: 'Unauthorized'
        },
        { status: 401 }
      );
    }

    const projectData = await request.json() as any;
    
    // Tạo project mới
    const newProject = {
      id: Math.max(...mockProjects.map(p => p.id)) + 1,
      tenant_id: payload.tenant_id,
      name: projectData.name,
      description: projectData.description || '',
      start_date: projectData.start_date,
      end_date: projectData.end_date,
      status: 'planning',
      progress: 0,
      actual_cost: 0,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    };

    mockProjects.push(newProject);

    return HttpResponse.json({
      status: 'success',
      data: newProject
    }, { status: 201 });
  }),

  // PUT /api/v1/projects/:id
  http.put(`${API_BASE}/projects/:id`, async ({ request, params }) => {
    const payload = checkAuth(request);
    
    if (!payload) {
      return HttpResponse.json(
        {
          status: 'error',
          message: 'Unauthorized'
        },
        { status: 401 }
      );
    }

    const projectId = parseInt(params.id as string);
    const projectIndex = mockProjects.findIndex(p => 
      p.id === projectId && p.tenant_id === payload.tenant_id
    );

    if (projectIndex === -1) {
      return HttpResponse.json(
        {
          status: 'error',
          message: 'Dự án không tồn tại'
        },
        { status: 404 }
      );
    }

    const updateData = await request.json() as any;
    
    // Cập nhật project
    mockProjects[projectIndex] = {
      ...mockProjects[projectIndex],
      ...updateData,
      updated_at: new Date().toISOString()
    };

    return HttpResponse.json({
      status: 'success',
      data: mockProjects[projectIndex]
    });
  }),

  // DELETE /api/v1/projects/:id
  http.delete(`${API_BASE}/projects/:id`, ({ request, params }) => {
    const payload = checkAuth(request);
    
    if (!payload) {
      return HttpResponse.json(
        {
          status: 'error',
          message: 'Unauthorized'
        },
        { status: 401 }
      );
    }

    const projectId = parseInt(params.id as string);
    const projectIndex = mockProjects.findIndex(p => 
      p.id === projectId && p.tenant_id === payload.tenant_id
    );

    if (projectIndex === -1) {
      return HttpResponse.json(
        {
          status: 'error',
          message: 'Dự án không tồn tại'
        },
        { status: 404 }
      );
    }

    // Xóa project
    mockProjects.splice(projectIndex, 1);

    return HttpResponse.json({
      status: 'success',
      data: {
        message: 'Xóa dự án thành công'
      }
    });
  })
];