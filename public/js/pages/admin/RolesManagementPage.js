import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { useState, useEffect } from 'react';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import { Modal } from '../../components/ui/Modal';
import { Table } from '../../components/ui/Table';
import { Layout } from '../../components/layout/Layout';
import { useApi } from '../../hooks/useApi';
import { formatDate } from '../../lib/utils';
import { ShieldCheckIcon, PencilIcon, TrashIcon, EyeIcon, MagnifyingGlassIcon, FunnelIcon, ArrowDownTrayIcon, PlusIcon, KeyIcon, UserGroupIcon, LockClosedIcon } from '@heroicons/react/24/outline';
export const RolesManagementPage = () => {
    const [roles, setRoles] = useState([]);
    const [permissions, setPermissions] = useState([]);
    const [permissionGroups, setPermissionGroups] = useState([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    const [scopeFilter, setScopeFilter] = useState('all');
    const [selectedRole, setSelectedRole] = useState(null);
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [isPermissionsModalOpen, setIsPermissionsModalOpen] = useState(false);
    const [isDuplicateModalOpen, setIsDuplicateModalOpen] = useState(false);
    const [formData, setFormData] = useState({
        name: '',
        description: '',
        scope: 'custom',
        permissionIds: []
    });
    const [selectedPermissionIds, setSelectedPermissionIds] = useState([]);
    const [expandedGroups, setExpandedGroups] = useState(new Set());
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
        }
        catch (error) {
            console.error('Error loading roles:', error);
        }
        finally {
            setLoading(false);
        }
    };
    const loadPermissions = async () => {
        try {
            const response = await get('/admin/permissions');
            setPermissions(response.data);
            // Nhóm permissions theo module
            const grouped = response.data.reduce((acc, permission) => {
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
        }
        catch (error) {
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
        }
        catch (error) {
            console.error('Error creating role:', error);
        }
    };
    // Xử lý cập nhật role
    const handleUpdateRole = async () => {
        if (!selectedRole)
            return;
        try {
            await put(`/admin/roles/${selectedRole.id}`, formData);
            setIsEditModalOpen(false);
            resetForm();
            loadRoles();
        }
        catch (error) {
            console.error('Error updating role:', error);
        }
    };
    // Xử lý xóa role
    const handleDeleteRole = async () => {
        if (!selectedRole)
            return;
        try {
            await del(`/admin/roles/${selectedRole.id}`);
            setIsDeleteModalOpen(false);
            setSelectedRole(null);
            loadRoles();
        }
        catch (error) {
            console.error('Error deleting role:', error);
        }
    };
    // Xử lý gán permissions
    const handleAssignPermissions = async () => {
        if (!selectedRole)
            return;
        try {
            await post(`/admin/roles/${selectedRole.id}/permissions`, {
                permissionIds: selectedPermissionIds
            });
            setIsPermissionsModalOpen(false);
            setSelectedPermissionIds([]);
            setSelectedRole(null);
            loadRoles();
        }
        catch (error) {
            console.error('Error assigning permissions:', error);
        }
    };
    // Xử lý nhân bản role
    const handleDuplicateRole = async () => {
        if (!selectedRole)
            return;
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
        }
        catch (error) {
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
        }
        catch (error) {
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
    const openEditModal = (role) => {
        setSelectedRole(role);
        setFormData({
            name: role.name,
            description: role.description || '',
            scope: role.scope,
            permissionIds: role.permissions.map(p => p.id)
        });
        setIsEditModalOpen(true);
    };
    const openPermissionsModal = (role) => {
        setSelectedRole(role);
        setSelectedPermissionIds(role.permissions.map(p => p.id));
        setIsPermissionsModalOpen(true);
    };
    const openDuplicateModal = (role) => {
        setSelectedRole(role);
        setFormData({
            name: `${role.name} (Copy)`,
            description: role.description || '',
            scope: 'custom',
            permissionIds: role.permissions.map(p => p.id)
        });
        setIsDuplicateModalOpen(true);
    };
    const toggleGroup = (module) => {
        const newExpanded = new Set(expandedGroups);
        if (newExpanded.has(module)) {
            newExpanded.delete(module);
        }
        else {
            newExpanded.add(module);
        }
        setExpandedGroups(newExpanded);
    };
    const toggleAllPermissionsInGroup = (groupPermissions, checked) => {
        const groupIds = groupPermissions.map(p => p.id);
        if (checked) {
            setSelectedPermissionIds([...new Set([...selectedPermissionIds, ...groupIds])]);
        }
        else {
            setSelectedPermissionIds(selectedPermissionIds.filter(id => !groupIds.includes(id)));
        }
    };
    const getScopeColor = (scope) => {
        switch (scope) {
            case 'system': return 'bg-red-100 text-red-800';
            case 'custom': return 'bg-blue-100 text-blue-800';
            case 'project': return 'bg-green-100 text-green-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };
    const getScopeLabel = (scope) => {
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
            render: (role) => (_jsxs("div", { className: "flex items-center space-x-3", children: [_jsx("div", { className: "w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center text-white text-sm font-medium", children: _jsx(ShieldCheckIcon, { className: "w-4 h-4" }) }), _jsxs("div", { children: [_jsx("div", { className: "font-medium text-gray-900", children: role.name }), _jsx("div", { className: "text-sm text-gray-500", children: role.description })] })] }))
        },
        {
            key: 'scope',
            label: 'Phạm vi',
            render: (role) => (_jsx("span", { className: `inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getScopeColor(role.scope)}`, children: getScopeLabel(role.scope) }))
        },
        {
            key: 'permissions',
            label: 'Số quyền',
            render: (role) => (_jsxs("div", { className: "flex items-center space-x-2", children: [_jsx(KeyIcon, { className: "w-4 h-4 text-gray-400" }), _jsx("span", { className: "text-sm font-medium", children: role.permissions.length })] }))
        },
        {
            key: 'users',
            label: 'Số người dùng',
            render: (role) => (_jsxs("div", { className: "flex items-center space-x-2", children: [_jsx(UserGroupIcon, { className: "w-4 h-4 text-gray-400" }), _jsx("span", { className: "text-sm font-medium", children: role.usersCount })] }))
        },
        {
            key: 'createdAt',
            label: 'Ngày tạo',
            render: (role) => (_jsx("span", { className: "text-sm text-gray-500", children: formatDate(role.createdAt) }))
        },
        {
            key: 'actions',
            label: 'Thao tác',
            render: (role) => (_jsxs("div", { className: "flex items-center space-x-2", children: [_jsx(Button, { variant: "ghost", size: "sm", onClick: () => {
                            setSelectedRole(role);
                            setIsViewModalOpen(true);
                        }, children: _jsx(EyeIcon, { className: "w-4 h-4" }) }), _jsx(Button, { variant: "ghost", size: "sm", onClick: () => openEditModal(role), disabled: role.scope === 'system', children: _jsx(PencilIcon, { className: "w-4 h-4" }) }), _jsx(Button, { variant: "ghost", size: "sm", onClick: () => openPermissionsModal(role), children: _jsx(KeyIcon, { className: "w-4 h-4" }) }), _jsx(Button, { variant: "ghost", size: "sm", onClick: () => openDuplicateModal(role), children: _jsx(PlusIcon, { className: "w-4 h-4" }) }), _jsx(Button, { variant: "ghost", size: "sm", onClick: () => {
                            setSelectedRole(role);
                            setIsDeleteModalOpen(true);
                        }, disabled: role.scope === 'system' || role.usersCount > 0, className: "text-red-600 hover:text-red-700 disabled:text-gray-400", children: _jsx(TrashIcon, { className: "w-4 h-4" }) })] }))
        }
    ];
    return (_jsx(Layout, { children: _jsxs("div", { className: "space-y-6", children: [_jsxs("div", { className: "flex justify-between items-center", children: [_jsxs("div", { children: [_jsx("h1", { className: "text-2xl font-bold text-gray-900", children: "Qu\u1EA3n l\u00FD quy\u1EC1n" }), _jsx("p", { className: "text-gray-600", children: "Qu\u1EA3n l\u00FD vai tr\u00F2 v\u00E0 ph\u00E2n quy\u1EC1n trong h\u1EC7 th\u1ED1ng" })] }), _jsxs("div", { className: "flex space-x-3", children: [_jsxs(Button, { variant: "outline", onClick: handleExportRoles, children: [_jsx(ArrowDownTrayIcon, { className: "w-4 h-4 mr-2" }), "Xu\u1EA5t d\u1EEF li\u1EC7u"] }), _jsxs(Button, { onClick: () => setIsCreateModalOpen(true), children: [_jsx(PlusIcon, { className: "w-4 h-4 mr-2" }), "Th\u00EAm quy\u1EC1n"] })] })] }), _jsx("div", { className: "bg-white p-4 rounded-lg border border-gray-200", children: _jsxs("div", { className: "grid grid-cols-1 md:grid-cols-3 gap-4", children: [_jsxs("div", { className: "relative", children: [_jsx(MagnifyingGlassIcon, { className: "w-5 h-5 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" }), _jsx(Input, { placeholder: "T\u00ECm ki\u1EBFm theo t\u00EAn ho\u1EB7c m\u00F4 t\u1EA3...", value: searchTerm, onChange: (e) => setSearchTerm(e.target.value), className: "pl-10" })] }), _jsxs("select", { value: scopeFilter, onChange: (e) => setScopeFilter(e.target.value), className: "px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500", children: [_jsx("option", { value: "all", children: "T\u1EA5t c\u1EA3 ph\u1EA1m vi" }), _jsx("option", { value: "system", children: "H\u1EC7 th\u1ED1ng" }), _jsx("option", { value: "custom", children: "T\u00F9y ch\u1EC9nh" }), _jsx("option", { value: "project", children: "D\u1EF1 \u00E1n" })] }), _jsxs(Button, { variant: "outline", children: [_jsx(FunnelIcon, { className: "w-4 h-4 mr-2" }), "L\u1ECDc n\u00E2ng cao"] })] }) }), _jsxs("div", { className: "grid grid-cols-1 md:grid-cols-4 gap-6", children: [_jsx("div", { className: "bg-white p-6 rounded-lg border border-gray-200", children: _jsxs("div", { className: "flex items-center", children: [_jsx("div", { className: "p-2 bg-purple-100 rounded-lg", children: _jsx(ShieldCheckIcon, { className: "w-6 h-6 text-purple-600" }) }), _jsxs("div", { className: "ml-4", children: [_jsx("p", { className: "text-sm font-medium text-gray-600", children: "T\u1ED5ng quy\u1EC1n" }), _jsx("p", { className: "text-2xl font-bold text-gray-900", children: roles.length })] })] }) }), _jsx("div", { className: "bg-white p-6 rounded-lg border border-gray-200", children: _jsxs("div", { className: "flex items-center", children: [_jsx("div", { className: "p-2 bg-red-100 rounded-lg", children: _jsx(LockClosedIcon, { className: "w-6 h-6 text-red-600" }) }), _jsxs("div", { className: "ml-4", children: [_jsx("p", { className: "text-sm font-medium text-gray-600", children: "Quy\u1EC1n h\u1EC7 th\u1ED1ng" }), _jsx("p", { className: "text-2xl font-bold text-gray-900", children: roles.filter(r => r.scope === 'system').length })] })] }) }), _jsx("div", { className: "bg-white p-6 rounded-lg border border-gray-200", children: _jsxs("div", { className: "flex items-center", children: [_jsx("div", { className: "p-2 bg-blue-100 rounded-lg", children: _jsx(KeyIcon, { className: "w-6 h-6 text-blue-600" }) }), _jsxs("div", { className: "ml-4", children: [_jsx("p", { className: "text-sm font-medium text-gray-600", children: "Quy\u1EC1n t\u00F9y ch\u1EC9nh" }), _jsx("p", { className: "text-2xl font-bold text-gray-900", children: roles.filter(r => r.scope === 'custom').length })] })] }) }), _jsx("div", { className: "bg-white p-6 rounded-lg border border-gray-200", children: _jsxs("div", { className: "flex items-center", children: [_jsx("div", { className: "p-2 bg-green-100 rounded-lg", children: _jsx(UserGroupIcon, { className: "w-6 h-6 text-green-600" }) }), _jsxs("div", { className: "ml-4", children: [_jsx("p", { className: "text-sm font-medium text-gray-600", children: "Quy\u1EC1n d\u1EF1 \u00E1n" }), _jsx("p", { className: "text-2xl font-bold text-gray-900", children: roles.filter(r => r.scope === 'project').length })] })] }) })] }), _jsx("div", { className: "bg-white rounded-lg border border-gray-200", children: _jsx(Table, { data: filteredRoles, columns: columns, loading: loading }) }), _jsx(Modal, { isOpen: isCreateModalOpen, onClose: () => {
                        setIsCreateModalOpen(false);
                        resetForm();
                    }, title: "Th\u00EAm quy\u1EC1n m\u1EDBi", size: "lg", children: _jsxs("div", { className: "space-y-4", children: [_jsx(Input, { label: "T\u00EAn quy\u1EC1n", value: formData.name, onChange: (e) => setFormData({ ...formData, name: e.target.value }), placeholder: "Nh\u1EADp t\u00EAn quy\u1EC1n" }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: "M\u00F4 t\u1EA3" }), _jsx("textarea", { value: formData.description, onChange: (e) => setFormData({ ...formData, description: e.target.value }), placeholder: "Nh\u1EADp m\u00F4 t\u1EA3 quy\u1EC1n", rows: 3, className: "w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" })] }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: "Ph\u1EA1m vi" }), _jsxs("select", { value: formData.scope, onChange: (e) => setFormData({
                                            ...formData,
                                            scope: e.target.value
                                        }), className: "w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500", children: [_jsx("option", { value: "custom", children: "T\u00F9y ch\u1EC9nh" }), _jsx("option", { value: "project", children: "D\u1EF1 \u00E1n" }), _jsx("option", { value: "system", children: "H\u1EC7 th\u1ED1ng" })] })] }), _jsxs("div", { children: [_jsxs("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: ["Ch\u1ECDn quy\u1EC1n (", formData.permissionIds.length, " \u0111\u00E3 ch\u1ECDn)"] }), _jsx("div", { className: "border border-gray-300 rounded-md max-h-80 overflow-y-auto", children: permissionGroups.map(group => {
                                            const groupPermissionIds = group.permissions.map(p => p.id);
                                            const selectedInGroup = groupPermissionIds.filter(id => formData.permissionIds.includes(id));
                                            const isGroupExpanded = expandedGroups.has(group.module);
                                            return (_jsxs("div", { className: "border-b border-gray-200 last:border-b-0", children: [_jsxs("div", { className: "p-3 bg-gray-50 flex items-center justify-between", children: [_jsxs("div", { className: "flex items-center space-x-2", children: [_jsx("input", { type: "checkbox", checked: selectedInGroup.length === group.permissions.length, onChange: (e) => {
                                                                            if (e.target.checked) {
                                                                                setFormData({
                                                                                    ...formData,
                                                                                    permissionIds: [...new Set([...formData.permissionIds, ...groupPermissionIds])]
                                                                                });
                                                                            }
                                                                            else {
                                                                                setFormData({
                                                                                    ...formData,
                                                                                    permissionIds: formData.permissionIds.filter(id => !groupPermissionIds.includes(id))
                                                                                });
                                                                            }
                                                                        }, className: "mr-2" }), _jsxs("span", { className: "font-medium text-gray-900", children: [group.module, " (", selectedInGroup.length, "/", group.permissions.length, ")"] })] }), _jsx("button", { type: "button", onClick: () => toggleGroup(group.module), className: "text-gray-400 hover:text-gray-600", children: isGroupExpanded ? '−' : '+' })] }), isGroupExpanded && (_jsx("div", { className: "p-3 space-y-2", children: group.permissions.map(permission => (_jsxs("label", { className: "flex items-center", children: [_jsx("input", { type: "checkbox", checked: formData.permissionIds.includes(permission.id), onChange: (e) => {
                                                                        if (e.target.checked) {
                                                                            setFormData({
                                                                                ...formData,
                                                                                permissionIds: [...formData.permissionIds, permission.id]
                                                                            });
                                                                        }
                                                                        else {
                                                                            setFormData({
                                                                                ...formData,
                                                                                permissionIds: formData.permissionIds.filter(id => id !== permission.id)
                                                                            });
                                                                        }
                                                                    }, className: "mr-3" }), _jsxs("div", { children: [_jsx("span", { className: "text-sm font-medium", children: permission.code }), permission.description && (_jsx("p", { className: "text-xs text-gray-500", children: permission.description }))] })] }, permission.id))) }))] }, group.module));
                                        }) })] }), _jsxs("div", { className: "flex justify-end space-x-3 pt-4", children: [_jsx(Button, { variant: "outline", onClick: () => {
                                            setIsCreateModalOpen(false);
                                            resetForm();
                                        }, children: "H\u1EE7y" }), _jsx(Button, { onClick: handleCreateRole, children: "T\u1EA1o quy\u1EC1n" })] })] }) }), _jsx(Modal, { isOpen: isEditModalOpen, onClose: () => {
                        setIsEditModalOpen(false);
                        resetForm();
                    }, title: "Ch\u1EC9nh s\u1EEDa quy\u1EC1n", size: "lg", children: _jsxs("div", { className: "space-y-4", children: [_jsx(Input, { label: "T\u00EAn quy\u1EC1n", value: formData.name, onChange: (e) => setFormData({ ...formData, name: e.target.value }), placeholder: "Nh\u1EADp t\u00EAn quy\u1EC1n" }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: "M\u00F4 t\u1EA3" }), _jsx("textarea", { value: formData.description, onChange: (e) => setFormData({ ...formData, description: e.target.value }), placeholder: "Nh\u1EADp m\u00F4 t\u1EA3 quy\u1EC1n", rows: 3, className: "w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" })] }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: "Ph\u1EA1m vi" }), _jsxs("select", { value: formData.scope, onChange: (e) => setFormData({
                                            ...formData,
                                            scope: e.target.value
                                        }), className: "w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500", disabled: selectedRole?.scope === 'system', children: [_jsx("option", { value: "custom", children: "T\u00F9y ch\u1EC9nh" }), _jsx("option", { value: "project", children: "D\u1EF1 \u00E1n" }), _jsx("option", { value: "system", children: "H\u1EC7 th\u1ED1ng" })] })] }), _jsxs("div", { className: "flex justify-end space-x-3 pt-4", children: [_jsx(Button, { variant: "outline", onClick: () => {
                                            setIsEditModalOpen(false);
                                            resetForm();
                                        }, children: "H\u1EE7y" }), _jsx(Button, { onClick: handleUpdateRole, children: "C\u1EADp nh\u1EADt" })] })] }) }), _jsx(Modal, { isOpen: isViewModalOpen, onClose: () => {
                        setIsViewModalOpen(false);
                        setSelectedRole(null);
                    }, title: "Th\u00F4ng tin quy\u1EC1n", size: "lg", children: selectedRole && (_jsxs("div", { className: "space-y-4", children: [_jsxs("div", { className: "flex items-center space-x-4", children: [_jsx("div", { className: "w-16 h-16 bg-purple-500 rounded-full flex items-center justify-center text-white text-xl font-bold", children: _jsx(ShieldCheckIcon, { className: "w-8 h-8" }) }), _jsxs("div", { children: [_jsx("h3", { className: "text-lg font-medium text-gray-900", children: selectedRole.name }), _jsx("p", { className: "text-gray-600", children: selectedRole.description })] })] }), _jsxs("div", { className: "grid grid-cols-2 gap-4", children: [_jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700", children: "Ph\u1EA1m vi" }), _jsx("span", { className: `inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getScopeColor(selectedRole.scope)}`, children: getScopeLabel(selectedRole.scope) })] }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700", children: "S\u1ED1 ng\u01B0\u1EDDi d\u00F9ng" }), _jsx("p", { className: "text-sm text-gray-900", children: selectedRole.usersCount })] })] }), _jsxs("div", { children: [_jsxs("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: ["Quy\u1EC1n (", selectedRole.permissions.length, ")"] }), _jsx("div", { className: "border border-gray-200 rounded-md max-h-60 overflow-y-auto", children: permissionGroups.map(group => {
                                            const groupPermissions = selectedRole.permissions.filter(p => p.module === group.module);
                                            if (groupPermissions.length === 0)
                                                return null;
                                            return (_jsxs("div", { className: "border-b border-gray-200 last:border-b-0", children: [_jsx("div", { className: "p-3 bg-gray-50", children: _jsxs("span", { className: "font-medium text-gray-900", children: [group.module, " (", groupPermissions.length, ")"] }) }), _jsx("div", { className: "p-3 space-y-1", children: groupPermissions.map(permission => (_jsxs("div", { className: "flex items-center justify-between", children: [_jsx("span", { className: "text-sm font-medium", children: permission.code }), _jsx("span", { className: "text-xs text-gray-500", children: permission.action })] }, permission.id))) })] }, group.module));
                                        }) })] }), _jsxs("div", { className: "grid grid-cols-2 gap-4", children: [_jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700", children: "Ng\u00E0y t\u1EA1o" }), _jsx("p", { className: "text-sm text-gray-900", children: formatDate(selectedRole.createdAt) })] }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700", children: "C\u1EADp nh\u1EADt cu\u1ED1i" }), _jsx("p", { className: "text-sm text-gray-900", children: formatDate(selectedRole.updatedAt) })] })] })] })) }), _jsx(Modal, { isOpen: isPermissionsModalOpen, onClose: () => {
                        setIsPermissionsModalOpen(false);
                        setSelectedPermissionIds([]);
                        setSelectedRole(null);
                    }, title: "Ph\u00E2n quy\u1EC1n", size: "lg", children: _jsxs("div", { className: "space-y-4", children: [_jsxs("p", { className: "text-sm text-gray-600", children: ["Ph\u00E2n quy\u1EC1n cho: ", _jsx("strong", { children: selectedRole?.name })] }), _jsxs("div", { children: [_jsxs("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: ["Ch\u1ECDn quy\u1EC1n (", selectedPermissionIds.length, " \u0111\u00E3 ch\u1ECDn)"] }), _jsx("div", { className: "border border-gray-300 rounded-md max-h-80 overflow-y-auto", children: permissionGroups.map(group => {
                                            const groupPermissionIds = group.permissions.map(p => p.id);
                                            const selectedInGroup = groupPermissionIds.filter(id => selectedPermissionIds.includes(id));
                                            const isGroupExpanded = expandedGroups.has(group.module);
                                            return (_jsxs("div", { className: "border-b border-gray-200 last:border-b-0", children: [_jsxs("div", { className: "p-3 bg-gray-50 flex items-center justify-between", children: [_jsxs("div", { className: "flex items-center space-x-2", children: [_jsx("input", { type: "checkbox", checked: selectedInGroup.length === group.permissions.length, onChange: (e) => toggleAllPermissionsInGroup(group.permissions, e.target.checked), className: "mr-2" }), _jsxs("span", { className: "font-medium text-gray-900", children: [group.module, " (", selectedInGroup.length, "/", group.permissions.length, ")"] })] }), _jsx("button", { type: "button", onClick: () => toggleGroup(group.module), className: "text-gray-400 hover:text-gray-600", children: isGroupExpanded ? '−' : '+' })] }), isGroupExpanded && (_jsx("div", { className: "p-3 space-y-2", children: group.permissions.map(permission => (_jsxs("label", { className: "flex items-center", children: [_jsx("input", { type: "checkbox", checked: selectedPermissionIds.includes(permission.id), onChange: (e) => {
                                                                        if (e.target.checked) {
                                                                            setSelectedPermissionIds([...selectedPermissionIds, permission.id]);
                                                                        }
                                                                        else {
                                                                            setSelectedPermissionIds(selectedPermissionIds.filter(id => id !== permission.id));
                                                                        }
                                                                    }, className: "mr-3" }), _jsxs("div", { children: [_jsx("span", { className: "text-sm font-medium", children: permission.code }), permission.description && (_jsx("p", { className: "text-xs text-gray-500", children: permission.description }))] })] }, permission.id))) }))] }, group.module));
                                        }) })] }), _jsxs("div", { className: "flex justify-end space-x-3 pt-4", children: [_jsx(Button, { variant: "outline", onClick: () => {
                                            setIsPermissionsModalOpen(false);
                                            setSelectedPermissionIds([]);
                                            setSelectedRole(null);
                                        }, children: "H\u1EE7y" }), _jsx(Button, { onClick: handleAssignPermissions, children: "Ph\u00E2n quy\u1EC1n" })] })] }) }), _jsx(Modal, { isOpen: isDuplicateModalOpen, onClose: () => {
                        setIsDuplicateModalOpen(false);
                        resetForm();
                    }, title: "Nh\u00E2n b\u1EA3n quy\u1EC1n", children: _jsxs("div", { className: "space-y-4", children: [_jsxs("p", { className: "text-sm text-gray-600", children: ["Nh\u00E2n b\u1EA3n t\u1EEB quy\u1EC1n: ", _jsx("strong", { children: selectedRole?.name })] }), _jsx(Input, { label: "T\u00EAn quy\u1EC1n m\u1EDBi", value: formData.name, onChange: (e) => setFormData({ ...formData, name: e.target.value }), placeholder: "Nh\u1EADp t\u00EAn quy\u1EC1n m\u1EDBi" }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: "M\u00F4 t\u1EA3" }), _jsx("textarea", { value: formData.description, onChange: (e) => setFormData({ ...formData, description: e.target.value }), placeholder: "Nh\u1EADp m\u00F4 t\u1EA3 quy\u1EC1n", rows: 3, className: "w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" })] }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: "Ph\u1EA1m vi" }), _jsxs("select", { value: formData.scope, onChange: (e) => setFormData({
                                            ...formData,
                                            scope: e.target.value
                                        }), className: "w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500", children: [_jsx("option", { value: "custom", children: "T\u00F9y ch\u1EC9nh" }), _jsx("option", { value: "project", children: "D\u1EF1 \u00E1n" }), _jsx("option", { value: "system", children: "H\u1EC7 th\u1ED1ng" })] })] }), _jsxs("div", { className: "flex justify-end space-x-3 pt-4", children: [_jsx(Button, { variant: "outline", onClick: () => {
                                            setIsDuplicateModalOpen(false);
                                            resetForm();
                                        }, children: "H\u1EE7y" }), _jsx(Button, { onClick: handleDuplicateRole, children: "Nh\u00E2n b\u1EA3n" })] })] }) }), _jsx(Modal, { isOpen: isDeleteModalOpen, onClose: () => {
                        setIsDeleteModalOpen(false);
                        setSelectedRole(null);
                    }, title: "X\u00E1c nh\u1EADn x\u00F3a", children: _jsxs("div", { className: "space-y-4", children: [_jsxs("p", { className: "text-sm text-gray-600", children: ["B\u1EA1n c\u00F3 ch\u1EAFc ch\u1EAFn mu\u1ED1n x\u00F3a quy\u1EC1n ", _jsx("strong", { children: selectedRole?.name }), "?", selectedRole?.usersCount && selectedRole.usersCount > 0 && (_jsxs("span", { className: "text-red-600", children: [_jsx("br", {}), "Quy\u1EC1n n\u00E0y \u0111ang \u0111\u01B0\u1EE3c s\u1EED d\u1EE5ng b\u1EDFi ", selectedRole.usersCount, " ng\u01B0\u1EDDi d\u00F9ng."] }))] }), _jsxs("div", { className: "flex justify-end space-x-3 pt-4", children: [_jsx(Button, { variant: "outline", onClick: () => {
                                            setIsDeleteModalOpen(false);
                                            setSelectedRole(null);
                                        }, children: "H\u1EE7y" }), _jsx(Button, { variant: "danger", onClick: handleDeleteRole, disabled: selectedRole?.usersCount && selectedRole.usersCount > 0, children: "X\u00F3a quy\u1EC1n" })] })] }) })] }) }));
};
