import React, { useState, useEffect } from 'react';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import { Modal } from '../../components/ui/Modal';
import { Table } from '../../components/ui/Table';
import { Layout } from '../../components/layout/Layout';
import { useApi } from '../../hooks/useApi';
import { User, Role } from '../../lib/types';
import { formatDate } from '../../lib/utils';
import { 
  UserPlusIcon, 
  PencilIcon, 
  TrashIcon, 
  EyeIcon,
  MagnifyingGlassIcon,
  FunnelIcon,
  ArrowDownTrayIcon,
  KeyIcon,
  ShieldCheckIcon
} from '@heroicons/react/24/outline';

interface UserWithRoles extends User {
  roles: Role[];
  lastLoginAt?: string;
  status: 'active' | 'inactive' | 'suspended';
}

interface UserFormData {
  name: string;
  email: string;
  password?: string;
  roleIds: string[];
  status: 'active' | 'inactive' | 'suspended';
}

export const UsersManagementPage: React.FC = () => {
  const [users, setUsers] = useState<UserWithRoles[]>([]);
  const [roles, setRoles] = useState<Role[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [roleFilter, setRoleFilter] = useState<string>('all');
  const [selectedUser, setSelectedUser] = useState<UserWithRoles | null>(null);
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [isResetPasswordModalOpen, setIsResetPasswordModalOpen] = useState(false);
  const [isAssignRoleModalOpen, setIsAssignRoleModalOpen] = useState(false);
  const [formData, setFormData] = useState<UserFormData>({
    name: '',
    email: '',
    password: '',
    roleIds: [],
    status: 'active'
  });
  const [newPassword, setNewPassword] = useState('');
  const [selectedRoleIds, setSelectedRoleIds] = useState<string[]>([]);

  const { get, post, put, del } = useApi();

  // Load dữ liệu ban đầu
  useEffect(() => {
    loadUsers();
    loadRoles();
  }, []);

  const loadUsers = async () => {
    try {
      setLoading(true);
      const response = await get('/admin/users');
      setUsers(response.data);
    } catch (error) {
      console.error('Error loading users:', error);
    } finally {
      setLoading(false);
    }
  };

  const loadRoles = async () => {
    try {
      const response = await get('/admin/roles');
      setRoles(response.data);
    } catch (error) {
      console.error('Error loading roles:', error);
    }
  };

  // Lọc và tìm kiếm người dùng
  const filteredUsers = users.filter(user => {
    const matchesSearch = user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                         user.email.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesStatus = statusFilter === 'all' || user.status === statusFilter;
    const matchesRole = roleFilter === 'all' || 
                       user.roles.some(role => role.id === roleFilter);
    
    return matchesSearch && matchesStatus && matchesRole;
  });

  // Xử lý tạo người dùng mới
  const handleCreateUser = async () => {
    try {
      await post('/admin/users', formData);
      setIsCreateModalOpen(false);
      resetForm();
      loadUsers();
    } catch (error) {
      console.error('Error creating user:', error);
    }
  };

  // Xử lý cập nhật người dùng
  const handleUpdateUser = async () => {
    if (!selectedUser) return;
    
    try {
      await put(`/admin/users/${selectedUser.id}`, formData);
      setIsEditModalOpen(false);
      resetForm();
      loadUsers();
    } catch (error) {
      console.error('Error updating user:', error);
    }
  };

  // Xử lý xóa người dùng
  const handleDeleteUser = async () => {
    if (!selectedUser) return;
    
    try {
      await del(`/admin/users/${selectedUser.id}`);
      setIsDeleteModalOpen(false);
      setSelectedUser(null);
      loadUsers();
    } catch (error) {
      console.error('Error deleting user:', error);
    }
  };

  // Xử lý reset mật khẩu
  const handleResetPassword = async () => {
    if (!selectedUser) return;
    
    try {
      await post(`/admin/users/${selectedUser.id}/reset-password`, {
        password: newPassword
      });
      setIsResetPasswordModalOpen(false);
      setNewPassword('');
      setSelectedUser(null);
    } catch (error) {
      console.error('Error resetting password:', error);
    }
  };

  // Xử lý gán quyền
  const handleAssignRoles = async () => {
    if (!selectedUser) return;
    
    try {
      await post(`/admin/users/${selectedUser.id}/assign-roles`, {
        roleIds: selectedRoleIds
      });
      setIsAssignRoleModalOpen(false);
      setSelectedRoleIds([]);
      setSelectedUser(null);
      loadUsers();
    } catch (error) {
      console.error('Error assigning roles:', error);
    }
  };

  // Xử lý export dữ liệu
  const handleExportUsers = async () => {
    try {
      const response = await get('/admin/users/export');
      // Tạo file download
      const blob = new Blob([response.data], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `users_${new Date().toISOString().split('T')[0]}.csv`;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch (error) {
      console.error('Error exporting users:', error);
    }
  };

  const resetForm = () => {
    setFormData({
      name: '',
      email: '',
      password: '',
      roleIds: [],
      status: 'active'
    });
  };

  const openEditModal = (user: UserWithRoles) => {
    setSelectedUser(user);
    setFormData({
      name: user.name,
      email: user.email,
      roleIds: user.roles.map(role => role.id),
      status: user.status
    });
    setIsEditModalOpen(true);
  };

  const openAssignRoleModal = (user: UserWithRoles) => {
    setSelectedUser(user);
    setSelectedRoleIds(user.roles.map(role => role.id));
    setIsAssignRoleModalOpen(true);
  };

  const columns = [
    {
      key: 'name',
      label: 'Tên người dùng',
      render: (user: UserWithRoles) => (
        <div className="flex items-center space-x-3">
          <div className="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm font-medium">
            {user.name.charAt(0).toUpperCase()}
          </div>
          <div>
            <div className="font-medium text-gray-900">{user.name}</div>
            <div className="text-sm text-gray-500">{user.email}</div>
          </div>
        </div>
      )
    },
    {
      key: 'roles',
      label: 'Quyền',
      render: (user: UserWithRoles) => (
        <div className="flex flex-wrap gap-1">
          {user.roles.map(role => (
            <span
              key={role.id}
              className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
            >
              {role.name}
            </span>
          ))}
        </div>
      )
    },
    {
      key: 'status',
      label: 'Trạng thái',
      render: (user: UserWithRoles) => (
        <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
          user.status === 'active' ? 'bg-green-100 text-green-800' :
          user.status === 'inactive' ? 'bg-gray-100 text-gray-800' :
          'bg-red-100 text-red-800'
        }`}>
          {user.status === 'active' ? 'Hoạt động' :
           user.status === 'inactive' ? 'Không hoạt động' : 'Bị khóa'}
        </span>
      )
    },
    {
      key: 'lastLoginAt',
      label: 'Đăng nhập cuối',
      render: (user: UserWithRoles) => (
        <span className="text-sm text-gray-500">
          {user.lastLoginAt ? formatDate(user.lastLoginAt) : 'Chưa đăng nhập'}
        </span>
      )
    },
    {
      key: 'actions',
      label: 'Thao tác',
      render: (user: UserWithRoles) => (
        <div className="flex items-center space-x-2">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              setSelectedUser(user);
              setIsViewModalOpen(true);
            }}
          >
            <EyeIcon className="w-4 h-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => openEditModal(user)}
          >
            <PencilIcon className="w-4 h-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              setSelectedUser(user);
              setIsResetPasswordModalOpen(true);
            }}
          >
            <KeyIcon className="w-4 h-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => openAssignRoleModal(user)}
          >
            <ShieldCheckIcon className="w-4 h-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              setSelectedUser(user);
              setIsDeleteModalOpen(true);
            }}
            className="text-red-600 hover:text-red-700"
          >
            <TrashIcon className="w-4 h-4" />
          </Button>
        </div>
      )
    }
  ];

  return (
    <Layout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Quản lý người dùng</h1>
            <p className="text-gray-600">Quản lý tài khoản người dùng và phân quyền</p>
          </div>
          <div className="flex space-x-3">
            <Button
              variant="outline"
              onClick={handleExportUsers}
            >
              <ArrowDownTrayIcon className="w-4 h-4 mr-2" />
              Xuất dữ liệu
            </Button>
            <Button onClick={() => setIsCreateModalOpen(true)}>
              <UserPlusIcon className="w-4 h-4 mr-2" />
              Thêm người dùng
            </Button>
          </div>
        </div>

        {/* Filters */}
        <div className="bg-white p-4 rounded-lg border border-gray-200">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className="relative">
              <MagnifyingGlassIcon className="w-5 h-5 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
              <Input
                placeholder="Tìm kiếm theo tên hoặc email..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-10"
              />
            </div>
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="all">Tất cả trạng thái</option>
              <option value="active">Hoạt động</option>
              <option value="inactive">Không hoạt động</option>
              <option value="suspended">Bị khóa</option>
            </select>
            <select
              value={roleFilter}
              onChange={(e) => setRoleFilter(e.target.value)}
              className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="all">Tất cả quyền</option>
              {roles.map(role => (
                <option key={role.id} value={role.id}>{role.name}</option>
              ))}
            </select>
            <Button variant="outline">
              <FunnelIcon className="w-4 h-4 mr-2" />
              Lọc nâng cao
            </Button>
          </div>
        </div>

        {/* Statistics */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
          <div className="bg-white p-6 rounded-lg border border-gray-200">
            <div className="flex items-center">
              <div className="p-2 bg-blue-100 rounded-lg">
                <UserPlusIcon className="w-6 h-6 text-blue-600" />
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-gray-600">Tổng người dùng</p>
                <p className="text-2xl font-bold text-gray-900">{users.length}</p>
              </div>
            </div>
          </div>
          <div className="bg-white p-6 rounded-lg border border-gray-200">
            <div className="flex items-center">
              <div className="p-2 bg-green-100 rounded-lg">
                <ShieldCheckIcon className="w-6 h-6 text-green-600" />
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-gray-600">Đang hoạt động</p>
                <p className="text-2xl font-bold text-gray-900">
                  {users.filter(u => u.status === 'active').length}
                </p>
              </div>
            </div>
          </div>
          <div className="bg-white p-6 rounded-lg border border-gray-200">
            <div className="flex items-center">
              <div className="p-2 bg-yellow-100 rounded-lg">
                <KeyIcon className="w-6 h-6 text-yellow-600" />
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-gray-600">Không hoạt động</p>
                <p className="text-2xl font-bold text-gray-900">
                  {users.filter(u => u.status === 'inactive').length}
                </p>
              </div>
            </div>
          </div>
          <div className="bg-white p-6 rounded-lg border border-gray-200">
            <div className="flex items-center">
              <div className="p-2 bg-red-100 rounded-lg">
                <TrashIcon className="w-6 h-6 text-red-600" />
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-gray-600">Bị khóa</p>
                <p className="text-2xl font-bold text-gray-900">
                  {users.filter(u => u.status === 'suspended').length}
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* Table */}
        <div className="bg-white rounded-lg border border-gray-200">
          <Table
            data={filteredUsers}
            columns={columns}
            loading={loading}
          />
        </div>

        {/* Create User Modal */}
        <Modal
          isOpen={isCreateModalOpen}
          onClose={() => {
            setIsCreateModalOpen(false);
            resetForm();
          }}
          title="Thêm người dùng mới"
        >
          <div className="space-y-4">
            <Input
              label="Tên người dùng"
              value={formData.name}
              onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              placeholder="Nhập tên người dùng"
            />
            <Input
              label="Email"
              type="email"
              value={formData.email}
              onChange={(e) => setFormData({ ...formData, email: e.target.value })}
              placeholder="Nhập email"
            />
            <Input
              label="Mật khẩu"
              type="password"
              value={formData.password}
              onChange={(e) => setFormData({ ...formData, password: e.target.value })}
              placeholder="Nhập mật khẩu"
            />
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Quyền
              </label>
              <div className="space-y-2 max-h-40 overflow-y-auto">
                {roles.map(role => (
                  <label key={role.id} className="flex items-center">
                    <input
                      type="checkbox"
                      checked={formData.roleIds.includes(role.id)}
                      onChange={(e) => {
                        if (e.target.checked) {
                          setFormData({
                            ...formData,
                            roleIds: [...formData.roleIds, role.id]
                          });
                        } else {
                          setFormData({
                            ...formData,
                            roleIds: formData.roleIds.filter(id => id !== role.id)
                          });
                        }
                      }}
                      className="mr-2"
                    />
                    <span className="text-sm">{role.name}</span>
                  </label>
                ))}
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Trạng thái
              </label>
              <select
                value={formData.status}
                onChange={(e) => setFormData({ 
                  ...formData, 
                  status: e.target.value as 'active' | 'inactive' | 'suspended'
                })}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="active">Hoạt động</option>
                <option value="inactive">Không hoạt động</option>
                <option value="suspended">Bị khóa</option>
              </select>
            </div>
            <div className="flex justify-end space-x-3 pt-4">
              <Button
                variant="outline"
                onClick={() => {
                  setIsCreateModalOpen(false);
                  resetForm();
                }}
              >
                Hủy
              </Button>
              <Button onClick={handleCreateUser}>
                Tạo người dùng
              </Button>
            </div>
          </div>
        </Modal>

        {/* Edit User Modal */}
        <Modal
          isOpen={isEditModalOpen}
          onClose={() => {
            setIsEditModalOpen(false);
            resetForm();
          }}
          title="Chỉnh sửa người dùng"
        >
          <div className="space-y-4">
            <Input
              label="Tên người dùng"
              value={formData.name}
              onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              placeholder="Nhập tên người dùng"
            />
            <Input
              label="Email"
              type="email"
              value={formData.email}
              onChange={(e) => setFormData({ ...formData, email: e.target.value })}
              placeholder="Nhập email"
            />
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Trạng thái
              </label>
              <select
                value={formData.status}
                onChange={(e) => setFormData({ 
                  ...formData, 
                  status: e.target.value as 'active' | 'inactive' | 'suspended'
                })}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="active">Hoạt động</option>
                <option value="inactive">Không hoạt động</option>
                <option value="suspended">Bị khóa</option>
              </select>
            </div>
            <div className="flex justify-end space-x-3 pt-4">
              <Button
                variant="outline"
                onClick={() => {
                  setIsEditModalOpen(false);
                  resetForm();
                }}
              >
                Hủy
              </Button>
              <Button onClick={handleUpdateUser}>
                Cập nhật
              </Button>
            </div>
          </div>
        </Modal>

        {/* View User Modal */}
        <Modal
          isOpen={isViewModalOpen}
          onClose={() => {
            setIsViewModalOpen(false);
            setSelectedUser(null);
          }}
          title="Thông tin người dùng"
        >
          {selectedUser && (
            <div className="space-y-4">
              <div className="flex items-center space-x-4">
                <div className="w-16 h-16 bg-blue-500 rounded-full flex items-center justify-center text-white text-xl font-bold">
                  {selectedUser.name.charAt(0).toUpperCase()}
                </div>
                <div>
                  <h3 className="text-lg font-medium text-gray-900">{selectedUser.name}</h3>
                  <p className="text-gray-600">{selectedUser.email}</p>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700">Trạng thái</label>
                  <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                    selectedUser.status === 'active' ? 'bg-green-100 text-green-800' :
                    selectedUser.status === 'inactive' ? 'bg-gray-100 text-gray-800' :
                    'bg-red-100 text-red-800'
                  }`}>
                    {selectedUser.status === 'active' ? 'Hoạt động' :
                     selectedUser.status === 'inactive' ? 'Không hoạt động' : 'Bị khóa'}
                  </span>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700">Đăng nhập cuối</label>
                  <p className="text-sm text-gray-900">
                    {selectedUser.lastLoginAt ? formatDate(selectedUser.lastLoginAt) : 'Chưa đăng nhập'}
                  </p>
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Quyền</label>
                <div className="flex flex-wrap gap-2">
                  {selectedUser.roles.map(role => (
                    <span
                      key={role.id}
                      className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800"
                    >
                      {role.name}
                    </span>
                  ))}
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700">Ngày tạo</label>
                  <p className="text-sm text-gray-900">{formatDate(selectedUser.createdAt)}</p>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700">Cập nhật cuối</label>
                  <p className="text-sm text-gray-900">{formatDate(selectedUser.updatedAt)}</p>
                </div>
              </div>
            </div>
          )}
        </Modal>

        {/* Reset Password Modal */}
        <Modal
          isOpen={isResetPasswordModalOpen}
          onClose={() => {
            setIsResetPasswordModalOpen(false);
            setNewPassword('');
            setSelectedUser(null);
          }}
          title="Đặt lại mật khẩu"
        >
          <div className="space-y-4">
            <p className="text-sm text-gray-600">
              Đặt lại mật khẩu cho người dùng: <strong>{selectedUser?.name}</strong>
            </p>
            <Input
              label="Mật khẩu mới"
              type="password"
              value={newPassword}
              onChange={(e) => setNewPassword(e.target.value)}
              placeholder="Nhập mật khẩu mới"
            />
            <div className="flex justify-end space-x-3 pt-4">
              <Button
                variant="outline"
                onClick={() => {
                  setIsResetPasswordModalOpen(false);
                  setNewPassword('');
                  setSelectedUser(null);
                }}
              >
                Hủy
              </Button>
              <Button onClick={handleResetPassword}>
                Đặt lại mật khẩu
              </Button>
            </div>
          </div>
        </Modal>

        {/* Assign Role Modal */}
        <Modal
          isOpen={isAssignRoleModalOpen}
          onClose={() => {
            setIsAssignRoleModalOpen(false);
            setSelectedRoleIds([]);
            setSelectedUser(null);
          }}
          title="Phân quyền"
        >
          <div className="space-y-4">
            <p className="text-sm text-gray-600">
              Phân quyền cho người dùng: <strong>{selectedUser?.name}</strong>
            </p>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Chọn quyền
              </label>
              <div className="space-y-2 max-h-60 overflow-y-auto">
                {roles.map(role => (
                  <label key={role.id} className="flex items-center">
                    <input
                      type="checkbox"
                      checked={selectedRoleIds.includes(role.id)}
                      onChange={(e) => {
                        if (e.target.checked) {
                          setSelectedRoleIds([...selectedRoleIds, role.id]);
                        } else {
                          setSelectedRoleIds(selectedRoleIds.filter(id => id !== role.id));
                        }
                      }}
                      className="mr-3"
                    />
                    <div>
                      <span className="text-sm font-medium">{role.name}</span>
                      {role.description && (
                        <p className="text-xs text-gray-500">{role.description}</p>
                      )}
                    </div>
                  </label>
                ))}
              </div>
            </div>
            <div className="flex justify-end space-x-3 pt-4">
              <Button
                variant="outline"
                onClick={() => {
                  setIsAssignRoleModalOpen(false);
                  setSelectedRoleIds([]);
                  setSelectedUser(null);
                }}
              >
                Hủy
              </Button>
              <Button onClick={handleAssignRoles}>
                Phân quyền
              </Button>
            </div>
          </div>
        </Modal>

        {/* Delete User Modal */}
        <Modal
          isOpen={isDeleteModalOpen}
          onClose={() => {
            setIsDeleteModalOpen(false);
            setSelectedUser(null);
          }}
          title="Xác nhận xóa"
        >
          <div className="space-y-4">
            <p className="text-sm text-gray-600">
              Bạn có chắc chắn muốn xóa người dùng <strong>{selectedUser?.name}</strong>?
              Hành động này không thể hoàn tác.
            </p>
            <div className="flex justify-end space-x-3 pt-4">
              <Button
                variant="outline"
                onClick={() => {
                  setIsDeleteModalOpen(false);
                  setSelectedUser(null);
                }}
              >
                Hủy
              </Button>
              <Button
                variant="danger"
                onClick={handleDeleteUser}
              >
                Xóa người dùng
              </Button>
            </div>
          </div>
        </Modal>
      </div>
    </Layout>
  );
};