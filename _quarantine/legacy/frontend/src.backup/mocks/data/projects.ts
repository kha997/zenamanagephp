export interface MockProject {
  id: number;
  tenant_id: number;
  name: string;
  description: string;
  start_date: string;
  end_date: string;
  status: 'planning' | 'in_progress' | 'on_hold' | 'completed' | 'cancelled';
  progress: number;
  actual_cost: number;
  created_at: string;
  updated_at: string;
}

export const mockProjects: MockProject[] = [
  {
    id: 1,
    tenant_id: 1,
    name: 'Dự án Xây dựng Nhà máy ABC',
    description: 'Xây dựng nhà máy sản xuất linh kiện điện tử với diện tích 5000m2',
    start_date: '2024-01-15',
    end_date: '2024-12-31',
    status: 'in_progress',
    progress: 35,
    actual_cost: 2500000000,
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-15T00:00:00Z'
  },
  {
    id: 2,
    tenant_id: 1,
    name: 'Dự án Cải tạo Văn phòng XYZ',
    description: 'Cải tạo và nâng cấp hệ thống văn phòng 20 tầng',
    start_date: '2024-02-01',
    end_date: '2024-08-30',
    status: 'planning',
    progress: 0,
    actual_cost: 0,
    created_at: '2024-01-20T00:00:00Z',
    updated_at: '2024-01-20T00:00:00Z'
  },
  {
    id: 3,
    tenant_id: 1,
    name: 'Dự án Khu dân cư Green Valley',
    description: 'Xây dựng khu dân cư cao cấp với 200 căn hộ',
    start_date: '2023-06-01',
    end_date: '2024-06-30',
    status: 'completed',
    progress: 100,
    actual_cost: 15000000000,
    created_at: '2023-05-01T00:00:00Z',
    updated_at: '2024-06-30T00:00:00Z'
  },
  {
    id: 4,
    tenant_id: 1,
    name: 'Dự án Cầu vượt Đại lộ Thăng Long',
    description: 'Xây dựng cầu vượt 4 làn xe tại nút giao Đại lộ Thăng Long',
    start_date: '2024-03-01',
    end_date: '2025-02-28',
    status: 'in_progress',
    progress: 15,
    actual_cost: 800000000,
    created_at: '2024-02-15T00:00:00Z',
    updated_at: '2024-03-15T00:00:00Z'
  },
  {
    id: 5,
    tenant_id: 2,
    name: 'Client Project Alpha',
    description: 'Sample project for client tenant',
    start_date: '2024-01-01',
    end_date: '2024-12-31',
    status: 'in_progress',
    progress: 25,
    actual_cost: 500000000,
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-15T00:00:00Z'
  }
];