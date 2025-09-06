import React, { useState, useEffect } from 'react';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import { Modal } from '../../components/ui/Modal';
import { Table } from '../../components/ui/Table';
import { Layout } from '../../components/layout/Layout';
import { useApi } from '../../hooks/useApi';
import { Role, Permission } from '../../lib/types';
import { formatDate } from '../../lib/utils';
import { 
  ShieldCheckIcon, 
  PencilIcon, 
  TrashIcon, 
  EyeIcon,
  MagnifyingGlassIcon,
  FunnelIcon,
  ArrowDownTrayIcon,
  PlusIcon,
  KeyIcon,
  UserGroupIcon,
  LockClosedIcon
} from '@heroicons/react/24/outline';

interface RoleWithPermissions extends Role {
  permissions: Permission[];
  usersCount: number;
  scope: 'system' | 'custom' | 'project';
}

interface RoleFormData {
  name: string;
  description: string;
  scope: 'system' | 'custom' | 'project';
  permissionIds: string[];
}

interface PermissionGroup {
  module: string;
  permissions: Permission[];
}

export const RolesManagementPage: React.FC = () => {
  const [roles, setRoles] = useState<RoleWithPermissions[]>([]);
  const [permissions, setPermissions] = useState<Permission[]>([]);
  const [permissionGroups, setPermissionGroups] = useState<PermissionGroup[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [scopeFilter, setScopeFilter] = useState<string>('all');
  const [selectedRole, setSelectedRole] = useState<RoleWithPermissions | null>(null);
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [isPermissionsModalOpen, setIsPermissionsModalOpen] = useState(false);
  const [isDuplicateModalOpen, setIsDuplicateModalOpen] = useState(false);
  const [formData, setFormData] = useState<RoleFormData>({
    name: '',
    description: '',
    scope: 'custom',
    permissionIds: []
  });
  const [selectedPermissionIds, setSelectedPermissionIds] = useState<string[]>([]);
  const [expandedGroups, setExpandedGroups] = useState<Set<string>>(new Set());

  const { get, post, put, del } = useApi();

  // Load dữ liệu ban đầu
  useEffect(() => {
    loadRoles();
    loadPermissions();
  }, []);

  const loadRoles = async () => {
    try {
      setLoading(true);
      const response = await get('/admin/roles');
      setRoles(response.data);
    } catch (error) {
      console.error('Error loading roles:', error);
    } finally {
      setLoading(false);
    }
  };

  const loadPermissions = async () => {
    try {
      const response = await get('/admin/permissions');
      setPermissions(response.data);
      
      // Nhóm permissions theo module
      const grouped = response.data.reduce((acc: { [key: string]: Permission[] }, permission: Permission) => {
        const module = permission.module || 'Other';
        if (!acc[module]) {
          acc[module] = [];
        }
        acc[module].push(permission);
        return acc;
      }, {});
      
      const groups = Object.entries(grouped).map(([module, permissions]) => ({
        module,
        permissions: permissions.sort((a, b) => a.action.localeCompare(b.action))
      }));
      
      setPermissionGroups(groups.sort((a, b) => a.module.localeCompare(b.module)));
    } catch (error) {
      console.error('Error loading permissions:', error);
    }
  };

  // Lọc và tìm kiếm roles
  const filteredRoles = roles.filter(role => {
    const matchesSearch = role.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                         role.description?.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesScope = scopeFilter === 'all' || role.scope === scopeFilter;
    
    return matchesSearch && matchesScope;
  });

  // Xử lý tạo role mới
  const handleCreateRole = async () => {
    try {
      await post('/admin/roles', formData);
      setIsCreateModalOpen(false);
      resetForm();
      loadRoles();
    } catch (error) {
      console.error('Error creating role:', error);
    }
  };

  // Xử lý cập nhật role
  const handleUpdateRole = async () => {
    if (!selectedRole) return;
    
    try {
      await put(`/admin/roles/${selectedRole.id}`, formData);
      setIsEditModalOpen(false);
      resetForm();
      loadRoles();
    } catch (error) {
      console.error('Error updating role:', error);
    }
  };

  // Xử lý xóa role
  const handleDeleteRole = async () => {
    if (!selectedRole) return;
    
    try {
      await del(`/admin/roles/${selectedRole.id}`);
      setIsDeleteModalOpen(false);
      setSelectedRole(null);
      loadRoles();
    } catch (error) {
      console.error('Error deleting role:', error);
    }
  };

  // Xử lý gán permissions
  const handleAssignPermissions = async () => {
    if (!selectedRole) return;
    
    try {
      await post(`/admin/roles/${selectedRole.id}/permissions`, {
        permissionIds: selectedPermissionIds
      });
      setIsPermissionsModalOpen(false);
      setSelectedPermissionIds([]);
      setSelectedRole(null);
      loadRoles();
    } catch (error) {
      console.error('Error assigning permissions:', error);
    }
  };

  // Xử lý nhân bản role
  const handleDuplicateRole = async () => {
    if (!selectedRole) return;
    
    try {
      await post('/admin/roles/duplicate', {
        sourceRoleId: selectedRole.id,
        name: formData.name,
        description: formData.description,
        scope: formData.scope
      });
      setIsDuplicateModalOpen(false);
      resetForm();
      loadRoles();
    } catch (error) {
      console.error('Error duplicating role:', error);
    }
  };

  // Xử lý export dữ liệu
  const handleExportRoles = async () => {
    try {
      const response = await get('/admin/roles/export');
      // Tạo file download
      const blob = new Blob([response.data], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `roles_${new Date().toISOString().split('T')[0]}.csv`;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch (error) {
      console.error('Error exporting roles:', error);
    }
  };

  const resetForm = () => {
    setFormData({
      name: '',
      description: '',
      scope: 'custom',
      permissionIds: []
    });
  };

  const openEditModal = (role: RoleWithPermissions) => {
    setSelectedRole(role);
    setFormData({
      name: role.name,
      description: role.description || '',
      scope: role.scope,
      permissionIds: role.permissions.map(p => p.id)
    });
    setIsEditModalOpen(true);
  };

  const openPermissionsModal = (role: RoleWithPermissions) => {
    setSelectedRole(role);
    setSelectedPermissionIds(role.permissions.map(p => p.id));
    setIsPermissionsModalOpen(true);
  };

  const openDuplicateModal = (role: RoleWithPermissions) => {
    setSelectedRole(role);
    setFormData({
      name: `${role.name} (Copy)`,
      description: role.description || '',
      scope: 'custom',
      permissionIds: role.permissions.map(p => p.id)
    });
    setIsDuplicateModalOpen(true);
  };

  const toggleGroup = (module: string) => {
    const newExpanded = new Set(expandedGroups);
    if (newExpanded.has(module)) {
      newExpanded.delete(module);
    } else {
      newExpanded.add(module);
    }
    setExpandedGroups(newExpanded);
  };

  const toggleAllPermissionsInGroup = (groupPermissions: Permission[], checked: boolean) => {
    const groupIds = groupPermissions.map(p => p.id);
    if (checked) {
      setSelectedPermissionIds([...new Set([...selectedPermissionIds, ...groupIds])]);
    } else {
      setSelectedPermissionIds(selectedPermissionIds.filter(id => !groupIds.includes(id)));
    }
  };

  const getScopeColor = (scope: string) => {
    switch (scope) {
      case 'system': return 'bg-red-100 text-red-800';
      case 'custom': return 'bg-blue-100 text-blue-800';
      case 'project': return 'bg-green-100 text-green-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getScopeLabel = (scope: string) => {
    switch (scope) {
      case 'system': return 'Hệ thống';
      case 'custom': return 'Tùy chỉnh';
      case 'project': return 'Dự án';
      default: return scope;
    }
  };

  const columns = [
    {
      key: 'name',
      label: 'Tên quyền',
      render: (role: RoleWithPermissions) => (
        <div className="flex items-center space-x-3">
          <div className="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center text-white text-sm font-medium">
            <ShieldCheckIcon className="w-4 h-4" />
          </div>
          <div>
            <div className="font-medium text-gray-900">{role.name}</div>
            <div className="text-sm text-gray-500">{role.description}</div>
          </div>
        </div>
      )
    },
    {
      key: 'scope',
      label: 'Phạm vi',
      render: (role: RoleWithPermissions) => (
        <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
          getScopeColor(role.scope)
        }`}>
          {getScopeLabel(role.scope)}
        </span>
      )
    },
    {
      key: 'permissions',
      label: 'Số quyền',
      render: (role: RoleWithPermissions) => (
        <div className="flex items-center space-x-2">
          <KeyIcon className="w-4 h-4 text-gray-400" />
          <span className="text-sm font-medium">{role.permissions.length}</span>
        </div>
      )
    },
    {
      key: 'users',
      label: 'Số người dùng',
      render: (role: RoleWithPermissions) => (
        <div className="flex items-center space-x-2">
          <UserGroupIcon className="w-4 h-4 text-gray-400" />
          <span className="text-sm font-medium">{role.usersCount}</span>
        </div>
      )
    },
    {
      key: 'createdAt',
      label: 'Ngày tạo',
      render: (role: RoleWithPermissions) => (
        <span className="text-sm text-gray-500">
          {formatDate(role.createdAt)}
        </span>
      )
    },
    {
      key: 'actions',
      label: 'Thao tác',
      render: (role: RoleWithPermissions) => (
        <div className="flex items-center space-x-2">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              setSelectedRole(role);
              setIsViewModalOpen(true);
            }}
          >
            <EyeIcon className="w-4 h-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => openEditModal(role)}
            disabled={role.scope === 'system'}
          >
            <PencilIcon className="w-4 h-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => openPermissionsModal(role)}
          >
            <KeyIcon className="w-4 h-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => openDuplicateModal(role)}
          >
            <PlusIcon className="w-4 h-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              setSelectedRole(role);
              setIsDeleteModalOpen(true);
            }}
            disabled={role.scope === 'system' || role.usersCount > 0}
            className="text-red-600 hover:text-red-700 disabled:text-gray-400"
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
            <h1 className="text-2xl font-bold text-gray-900">Quản lý quyền</h1>
            <p className="text-gray-600">Quản lý vai trò và phân quyền trong hệ thống</p>
          </div>
          <div className="flex space-x-3">
            <Button
              variant="outline"
              onClick={handleExportRoles}
            >
              <ArrowDownTrayIcon className="w-4 h-4 mr-2" />
              Xuất dữ liệu
            </Button>
            <Button onClick={() => setIsCreateModalOpen(true)}>
              <PlusIcon className="w-4 h-4 mr-2" />
              Thêm quyền
            </Button>
          </div>
        </div>

        {/* Filters */}
        <div className="bg-white p-4 rounded-lg border border-gray-200">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="relative">
              <MagnifyingGlassIcon className="w-5 h-5 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
              <Input
                placeholder="Tìm kiếm theo tên hoặc mô tả..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-10"
              />
            </div>
            <select
              value={scopeFilter}
              onChange={(e) => setScopeFilter(e.target.value)}
              className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="all">Tất cả phạm vi</option>
              <option value="system">Hệ thống</option>
              <option value="custom">Tùy chỉnh</option>
              <option value="project">Dự án</option>
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
              <div className="p-2 bg-purple-100 rounded-lg">
                <ShieldCheckIcon className="w-6 h-6 text-purple-600" />
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-gray-600">Tổng quyền</p>
                <p className="text-2xl font-bold text-gray-900">{roles.length}</p>
              </div>
            </div>
          </div>
          <div className="bg-white p-6 rounded-lg border border-gray-200">
            <div className="flex items-center">
              <div className="p-2 bg-red-100 rounded-lg">
                <LockClosedIcon className="w-6 h-6 text-red-600" />
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-gray-600">Quyền hệ thống</p>
                <p className="text-2xl font-bold text-gray-900">
                  {roles.filter(r => r.scope === 'system').length}
                </p>
              </div>
            </div>
          </div>
          <div className="bg-white p-6 rounded-lg border border-gray-200">
            <div className="flex items-center">
              <div className="p-2 bg-blue-100 rounded-lg">
                <KeyIcon className="w-6 h-6 text-blue-600" />
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-gray-600">Quyền tùy chỉnh</p>
                <p className="text-2xl font-bold text-gray-900">
                  {roles.filter(r => r.scope === 'custom').length}
                </p>
              </div>
            </div>
          </div>
          <div className="bg-white p-6 rounded-lg border border-gray-200">
            <div className="flex items-center">
              <div className="p-2 bg-green-100 rounded-lg">
                <UserGroupIcon className="w-6 h-6 text-green-600" />
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-gray-600">Quyền dự án</p>
                <p className="text-2xl font-bold text-gray-900">
                  {roles.filter(r => r.scope === 'project').length}
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* Table */}
        <div className="bg-white rounded-lg border border-gray-200">
          <Table
            data={filteredRoles}
            columns={columns}
            loading={loading}
          />
        </div>

        {/* Create Role Modal */}
        <Modal
          isOpen={isCreateModalOpen}
          onClose={() => {
            setIsCreateModalOpen(false);
            resetForm();
          }}
          title="Thêm quyền mới"
          size="lg"
        >
          <div className="space-y-4">
            <Input
              label="Tên quyền"
              value={formData.name}
              onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              placeholder="Nhập tên quyền"
            />
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Mô tả
              </label>
              <textarea
                value={formData.description}
                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                placeholder="Nhập mô tả quyền"
                rows={3}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Phạm vi
              </label>
              <select
                value={formData.scope}
                onChange={(e) => setFormData({ 
                  ...formData, 
                  scope: e.target.value as 'system' | 'custom' | 'project'
                })}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="custom">Tùy chỉnh</option>
                <option value="project">Dự án</option>
                <option value="system">Hệ thống</option>
              </select>
            </div>
            
            {/* Permissions Selection */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Chọn quyền ({formData.permissionIds.length} đã chọn)
              </label>
              <div className="border border-gray-300 rounded-md max-h-80 overflow-y-auto">
                {permissionGroups.map(group => {
                  const groupPermissionIds = group.permissions.map(p => p.id);
                  const selectedInGroup = groupPermissionIds.filter(id => formData.permissionIds.includes(id));
                  const isGroupExpanded = expandedGroups.has(group.module);
                  
                  return (
                    <div key={group.module} className="border-b border-gray-200 last:border-b-0">
                      <div className="p-3 bg-gray-50 flex items-center justify-between">
                        <div className="flex items-center space-x-2">
                          <input
                            type="checkbox"
                            checked={selectedInGroup.length === group.permissions.length}
                            onChange={(e) => {
                              if (e.target.checked) {
                                setFormData({
                                  ...formData,
                                  permissionIds: [...new Set([...formData.permissionIds, ...groupPermissionIds])]
                                });
                              } else {
                                setFormData({
                                  ...formData,
                                  permissionIds: formData.permissionIds.filter(id => !groupPermissionIds.includes(id))
                                });
                              }
                            }}
                            className="mr-2"
                          />
                          <span className="font-medium text-gray-900">
                            {group.module} ({selectedInGroup.length}/{group.permissions.length})
                          </span>
                        </div>
                        <button
                          type="button"
                          onClick={() => toggleGroup(group.module)}
                          className="text-gray-400 hover:text-gray-600"
                        >
                          {isGroupExpanded ? '−' : '+'}
                        </button>
                      </div>
                      {isGroupExpanded && (
                        <div className="p-3 space-y-2">
                          {group.permissions.map(permission => (
                            <label key={permission.id} className="flex items-center">
                              <input
                                type="checkbox"
                                checked={formData.permissionIds.includes(permission.id)}
                                onChange={(e) => {
                                  if (e.target.checked) {
                                    setFormData({
                                      ...formData,
                                      permissionIds: [...formData.permissionIds, permission.id]
                                    });
                                  } else {
                                    setFormData({
                                      ...formData,
                                      permissionIds: formData.permissionIds.filter(id => id !== permission.id)
                                    });
                                  }
                                }}
                                className="mr-3"
                              />
                              <div>
                                <span className="text-sm font-medium">{permission.code}</span>
                                {permission.description && (
                                  <p className="text-xs text-gray-500">{permission.description}</p>
                                )}
                              </div>
                            </label>
                          ))}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
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
              <Button onClick={handleCreateRole}>
                Tạo quyền
              </Button>
            </div>
          </div>
        </Modal>

        {/* Edit Role Modal */}
        <Modal
          isOpen={isEditModalOpen}
          onClose={() => {
            setIsEditModalOpen(false);
            resetForm();
          }}
          title="Chỉnh sửa quyền"
          size="lg"
        >
          <div className="space-y-4">
            <Input
              label="Tên quyền"
              value={formData.name}
              onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              placeholder="Nhập tên quyền"
            />
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Mô tả
              </label>
              <textarea
                value={formData.description}
                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                placeholder="Nhập mô tả quyền"
                rows={3}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Phạm vi
              </label>
              <select
                value={formData.scope}
                onChange={(e) => setFormData({ 
                  ...formData, 
                  scope: e.target.value as 'system' | 'custom' | 'project'
                })}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                disabled={selectedRole?.scope === 'system'}
              >
                <option value="custom">Tùy chỉnh</option>
                <option value="project">Dự án</option>
                <option value="system">Hệ thống</option>
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
              <Button onClick={handleUpdateRole}>
                Cập nhật
              </Button>
            </div>
          </div>
        </Modal>

        {/* View Role Modal */}
        <Modal
          isOpen={isViewModalOpen}
          onClose={() => {
            setIsViewModalOpen(false);
            setSelectedRole(null);
          }}
          title="Thông tin quyền"
          size="lg"
        >
          {selectedRole && (
            <div className="space-y-4">
              <div className="flex items-center space-x-4">
                <div className="w-16 h-16 bg-purple-500 rounded-full flex items-center justify-center text-white text-xl font-bold">
                  <ShieldCheckIcon className="w-8 h-8" />
                </div>
                <div>
                  <h3 className="text-lg font-medium text-gray-900">{selectedRole.name}</h3>
                  <p className="text-gray-600">{selectedRole.description}</p>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700">Phạm vi</label>
                  <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                    getScopeColor(selectedRole.scope)
                  }`}>
                    {getScopeLabel(selectedRole.scope)}
                  </span>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700">Số người dùng</label>
                  <p className="text-sm text-gray-900">{selectedRole.usersCount}</p>
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Quyền ({selectedRole.permissions.length})
                </label>
                <div className="border border-gray-200 rounded-md max-h-60 overflow-y-auto">
                  {permissionGroups.map(group => {
                    const groupPermissions = selectedRole.permissions.filter(p => p.module === group.module);
                    if (groupPermissions.length === 0) return null;
                    
                    return (
                      <div key={group.module} className="border-b border-gray-200 last:border-b-0">
                        <div className="p-3 bg-gray-50">
                          <span className="font-medium text-gray-900">
                            {group.module} ({groupPermissions.length})
                          </span>
                        </div>
                        <div className="p-3 space-y-1">
                          {groupPermissions.map(permission => (
                            <div key={permission.id} className="flex items-center justify-between">
                              <span className="text-sm font-medium">{permission.code}</span>
                              <span className="text-xs text-gray-500">{permission.action}</span>
                            </div>
                          ))}
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700">Ngày tạo</label>
                  <p className="text-sm text-gray-900">{formatDate(selectedRole.createdAt)}</p>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700">Cập nhật cuối</label>
                  <p className="text-sm text-gray-900">{formatDate(selectedRole.updatedAt)}</p>
                </div>
              </div>
            </div>
          )}
        </Modal>

        {/* Assign Permissions Modal */}
        <Modal
          isOpen={isPermissionsModalOpen}
          onClose={() => {
            setIsPermissionsModalOpen(false);
            setSelectedPermissionIds([]);
            setSelectedRole(null);
          }}
          title="Phân quyền"
          size="lg"
        >
          <div className="space-y-4">
            <p className="text-sm text-gray-600">
              Phân quyền cho: <strong>{selectedRole?.name}</strong>
            </p>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Chọn quyền ({selectedPermissionIds.length} đã chọn)
              </label>
              <div className="border border-gray-300 rounded-md max-h-80 overflow-y-auto">
                {permissionGroups.map(group => {
                  const groupPermissionIds = group.permissions.map(p => p.id);
                  const selectedInGroup = groupPermissionIds.filter(id => selectedPermissionIds.includes(id));
                  const isGroupExpanded = expandedGroups.has(group.module);
                  
                  return (
                    <div key={group.module} className="border-b border-gray-200 last:border-b-0">
                      <div className="p-3 bg-gray-50 flex items-center justify-between">
                        <div className="flex items-center space-x-2">
                          <input
                            type="checkbox"
                            checked={selectedInGroup.length === group.permissions.length}
                            onChange={(e) => toggleAllPermissionsInGroup(group.permissions, e.target.checked)}
                            className="mr-2"
                          />
                          <span className="font-medium text-gray-900">
                            {group.module} ({selectedInGroup.length}/{group.permissions.length})
                          </span>
                        </div>
                        <button
                          type="button"
                          onClick={() => toggleGroup(group.module)}
                          className="text-gray-400 hover:text-gray-600"
                        >
                          {isGroupExpanded ? '−' : '+'}
                        </button>
                      </div>
                      {isGroupExpanded && (
                        <div className="p-3 space-y-2">
                          {group.permissions.map(permission => (
                            <label key={permission.id} className="flex items-center">
                              <input
                                type="checkbox"
                                checked={selectedPermissionIds.includes(permission.id)}
                                onChange={(e) => {
                                  if (e.target.checked) {
                                    setSelectedPermissionIds([...selectedPermissionIds, permission.id]);
                                  } else {
                                    setSelectedPermissionIds(selectedPermissionIds.filter(id => id !== permission.id));
                                  }
                                }}
                                className="mr-3"
                              />
                              <div>
                                <span className="text-sm font-medium">{permission.code}</span>
                                {permission.description && (
                                  <p className="text-xs text-gray-500">{permission.description}</p>
                                )}
                              </div>
                            </label>
                          ))}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
            <div className="flex justify-end space-x-3 pt-4">
              <Button
                variant="outline"
                onClick={() => {
                  setIsPermissionsModalOpen(false);
                  setSelectedPermissionIds([]);
                  setSelectedRole(null);
                }}
              >
                Hủy
              </Button>
              <Button onClick={handleAssignPermissions}>
                Phân quyền
              </Button>
            </div>
          </div>
        </Modal>

        {/* Duplicate Role Modal */}
        <Modal
          isOpen={isDuplicateModalOpen}
          onClose={() => {
            setIsDuplicateModalOpen(false);
            resetForm();
          }}
          title="Nhân bản quyền"
        >
          <div className="space-y-4">
            <p className="text-sm text-gray-600">
              Nhân bản từ quyền: <strong>{selectedRole?.name}</strong>
            </p>
            <Input
              label="Tên quyền mới"
              value={formData.name}
              onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              placeholder="Nhập tên quyền mới"
            />
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Mô tả
              </label>
              <textarea
                value={formData.description}
                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                placeholder="Nhập mô tả quyền"
                rows={3}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Phạm vi
              </label>
              <select
                value={formData.scope}
                onChange={(e) => setFormData({ 
                  ...formData, 
                  scope: e.target.value as 'system' | 'custom' | 'project'
                })}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="custom">Tùy chỉnh</option>
                <option value="project">Dự án</option>
                <option value="system">Hệ thống</option>
              </select>
            </div>
            <div className="flex justify-end space-x-3 pt-4">
              <Button
                variant="outline"
                onClick={() => {
                  setIsDuplicateModalOpen(false);
                  resetForm();
                }}
              >
                Hủy
              </Button>
              <Button onClick={handleDuplicateRole}>
                Nhân bản
              </Button>
            </div>
          </div>
        </Modal>

        {/* Delete Role Modal */}
        <Modal
          isOpen={isDeleteModalOpen}
          onClose={() => {
            setIsDeleteModalOpen(false);
            setSelectedRole(null);
          }}
          title="Xác nhận xóa"
        >
          <div className="space-y-4">
            <p className="text-sm text-gray-600">
              Bạn có chắc chắn muốn xóa quyền <strong>{selectedRole?.name}</strong>?
              {selectedRole?.usersCount && selectedRole.usersCount > 0 && (
                <span className="text-red-600">
                  <br />Quyền này đang được sử dụng bởi {selectedRole.usersCount} người dùng.
                </span>
              )}
            </p>
            <div className="flex justify-end space-x-3 pt-4">
              <Button
                variant="outline"
                onClick={() => {
                  setIsDeleteModalOpen(false);
                  setSelectedRole(null);
                }}
              >
                Hủy
              </Button>
              <Button
                variant="danger"
                onClick={handleDeleteRole}
                disabled={selectedRole?.usersCount && selectedRole.usersCount > 0}
              >
                Xóa quyền
              </Button>
            </div>
          </div>
        </Modal>
      </div>
    </Layout>
  );
};