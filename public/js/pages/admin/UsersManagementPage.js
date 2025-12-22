import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { useState, useEffect } from 'react';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import { Modal } from '../../components/ui/Modal';
import { Table } from '../../components/ui/Table';
import { Layout } from '../../components/layout/Layout';
import { useApi } from '../../hooks/useApi';
import { formatDate } from '../../lib/utils';
import { UserPlusIcon, PencilIcon, TrashIcon, EyeIcon, MagnifyingGlassIcon, FunnelIcon, ArrowDownTrayIcon, KeyIcon, ShieldCheckIcon } from '@heroicons/react/24/outline';
export const UsersManagementPage = () => {
    const [users, setUsers] = useState([]);
    const [roles, setRoles] = useState([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [roleFilter, setRoleFilter] = useState('all');
    const [selectedUser, setSelectedUser] = useState(null);
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [isResetPasswordModalOpen, setIsResetPasswordModalOpen] = useState(false);
    const [isAssignRoleModalOpen, setIsAssignRoleModalOpen] = useState(false);
    const [formData, setFormData] = useState({
        name: '',
        email: '',
        password: '',
        roleIds: [],
        status: 'active'
    });
    const [newPassword, setNewPassword] = useState('');
    const [selectedRoleIds, setSelectedRoleIds] = useState([]);
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
        }
        catch (error) {
            console.error('Error loading users:', error);
        }
        finally {
            setLoading(false);
        }
    };
    const loadRoles = async () => {
        try {
            const response = await get('/admin/roles');
            setRoles(response.data);
        }
        catch (error) {
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
        }
        catch (error) {
            console.error('Error creating user:', error);
        }
    };
    // Xử lý cập nhật người dùng
    const handleUpdateUser = async () => {
        if (!selectedUser)
            return;
        try {
            await put(`/admin/users/${selectedUser.id}`, formData);
            setIsEditModalOpen(false);
            resetForm();
            loadUsers();
        }
        catch (error) {
            console.error('Error updating user:', error);
        }
    };
    // Xử lý xóa người dùng
    const handleDeleteUser = async () => {
        if (!selectedUser)
            return;
        try {
            await del(`/admin/users/${selectedUser.id}`);
            setIsDeleteModalOpen(false);
            setSelectedUser(null);
            loadUsers();
        }
        catch (error) {
            console.error('Error deleting user:', error);
        }
    };
    // Xử lý reset mật khẩu
    const handleResetPassword = async () => {
        if (!selectedUser)
            return;
        try {
            await post(`/admin/users/${selectedUser.id}/reset-password`, {
                password: newPassword
            });
            setIsResetPasswordModalOpen(false);
            setNewPassword('');
            setSelectedUser(null);
        }
        catch (error) {
            console.error('Error resetting password:', error);
        }
    };
    // Xử lý gán quyền
    const handleAssignRoles = async () => {
        if (!selectedUser)
            return;
        try {
            await post(`/admin/users/${selectedUser.id}/assign-roles`, {
                roleIds: selectedRoleIds
            });
            setIsAssignRoleModalOpen(false);
            setSelectedRoleIds([]);
            setSelectedUser(null);
            loadUsers();
        }
        catch (error) {
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
        }
        catch (error) {
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
    const openEditModal = (user) => {
        setSelectedUser(user);
        setFormData({
            name: user.name,
            email: user.email,
            roleIds: user.roles.map(role => role.id),
            status: user.status
        });
        setIsEditModalOpen(true);
    };
    const openAssignRoleModal = (user) => {
        setSelectedUser(user);
        setSelectedRoleIds(user.roles.map(role => role.id));
        setIsAssignRoleModalOpen(true);
    };
    const columns = [
        {
            key: 'name',
            label: 'Tên người dùng',
            render: (user) => (_jsxs("div", { className: "flex items-center space-x-3", children: [_jsx("div", { className: "w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm font-medium", children: user.name.charAt(0).toUpperCase() }), _jsxs("div", { children: [_jsx("div", { className: "font-medium text-gray-900", children: user.name }), _jsx("div", { className: "text-sm text-gray-500", children: user.email })] })] }))
        },
        {
            key: 'roles',
            label: 'Quyền',
            render: (user) => (_jsx("div", { className: "flex flex-wrap gap-1", children: user.roles.map(role => (_jsx("span", { className: "inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800", children: role.name }, role.id))) }))
        },
        {
            key: 'status',
            label: 'Trạng thái',
            render: (user) => (_jsx("span", { className: `inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${user.status === 'active' ? 'bg-green-100 text-green-800' :
                    user.status === 'inactive' ? 'bg-gray-100 text-gray-800' :
                        'bg-red-100 text-red-800'}`, children: user.status === 'active' ? 'Hoạt động' :
                    user.status === 'inactive' ? 'Không hoạt động' : 'Bị khóa' }))
        },
        {
            key: 'lastLoginAt',
            label: 'Đăng nhập cuối',
            render: (user) => (_jsx("span", { className: "text-sm text-gray-500", children: user.lastLoginAt ? formatDate(user.lastLoginAt) : 'Chưa đăng nhập' }))
        },
        {
            key: 'actions',
            label: 'Thao tác',
            render: (user) => (_jsxs("div", { className: "flex items-center space-x-2", children: [_jsx(Button, { variant: "ghost", size: "sm", onClick: () => {
                            setSelectedUser(user);
                            setIsViewModalOpen(true);
                        }, children: _jsx(EyeIcon, { className: "w-4 h-4" }) }), _jsx(Button, { variant: "ghost", size: "sm", onClick: () => openEditModal(user), children: _jsx(PencilIcon, { className: "w-4 h-4" }) }), _jsx(Button, { variant: "ghost", size: "sm", onClick: () => {
                            setSelectedUser(user);
                            setIsResetPasswordModalOpen(true);
                        }, children: _jsx(KeyIcon, { className: "w-4 h-4" }) }), _jsx(Button, { variant: "ghost", size: "sm", onClick: () => openAssignRoleModal(user), children: _jsx(ShieldCheckIcon, { className: "w-4 h-4" }) }), _jsx(Button, { variant: "ghost", size: "sm", onClick: () => {
                            setSelectedUser(user);
                            setIsDeleteModalOpen(true);
                        }, className: "text-red-600 hover:text-red-700", children: _jsx(TrashIcon, { className: "w-4 h-4" }) })] }))
        }
    ];
    return (_jsx(Layout, { children: _jsxs("div", { className: "space-y-6", children: [_jsxs("div", { className: "flex justify-between items-center", children: [_jsxs("div", { children: [_jsx("h1", { className: "text-2xl font-bold text-gray-900", children: "Qu\u1EA3n l\u00FD ng\u01B0\u1EDDi d\u00F9ng" }), _jsx("p", { className: "text-gray-600", children: "Qu\u1EA3n l\u00FD t\u00E0i kho\u1EA3n ng\u01B0\u1EDDi d\u00F9ng v\u00E0 ph\u00E2n quy\u1EC1n" })] }), _jsxs("div", { className: "flex space-x-3", children: [_jsxs(Button, { variant: "outline", onClick: handleExportUsers, children: [_jsx(ArrowDownTrayIcon, { className: "w-4 h-4 mr-2" }), "Xu\u1EA5t d\u1EEF li\u1EC7u"] }), _jsxs(Button, { onClick: () => setIsCreateModalOpen(true), children: [_jsx(UserPlusIcon, { className: "w-4 h-4 mr-2" }), "Th\u00EAm ng\u01B0\u1EDDi d\u00F9ng"] })] })] }), _jsx("div", { className: "bg-white p-4 rounded-lg border border-gray-200", children: _jsxs("div", { className: "grid grid-cols-1 md:grid-cols-4 gap-4", children: [_jsxs("div", { className: "relative", children: [_jsx(MagnifyingGlassIcon, { className: "w-5 h-5 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" }), _jsx(Input, { placeholder: "T\u00ECm ki\u1EBFm theo t\u00EAn ho\u1EB7c email...", value: searchTerm, onChange: (e) => setSearchTerm(e.target.value), className: "pl-10" })] }), _jsxs("select", { value: statusFilter, onChange: (e) => setStatusFilter(e.target.value), className: "px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500", children: [_jsx("option", { value: "all", children: "T\u1EA5t c\u1EA3 tr\u1EA1ng th\u00E1i" }), _jsx("option", { value: "active", children: "Ho\u1EA1t \u0111\u1ED9ng" }), _jsx("option", { value: "inactive", children: "Kh\u00F4ng ho\u1EA1t \u0111\u1ED9ng" }), _jsx("option", { value: "suspended", children: "B\u1ECB kh\u00F3a" })] }), _jsxs("select", { value: roleFilter, onChange: (e) => setRoleFilter(e.target.value), className: "px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500", children: [_jsx("option", { value: "all", children: "T\u1EA5t c\u1EA3 quy\u1EC1n" }), roles.map(role => (_jsx("option", { value: role.id, children: role.name }, role.id)))] }), _jsxs(Button, { variant: "outline", children: [_jsx(FunnelIcon, { className: "w-4 h-4 mr-2" }), "L\u1ECDc n\u00E2ng cao"] })] }) }), _jsxs("div", { className: "grid grid-cols-1 md:grid-cols-4 gap-6", children: [_jsx("div", { className: "bg-white p-6 rounded-lg border border-gray-200", children: _jsxs("div", { className: "flex items-center", children: [_jsx("div", { className: "p-2 bg-blue-100 rounded-lg", children: _jsx(UserPlusIcon, { className: "w-6 h-6 text-blue-600" }) }), _jsxs("div", { className: "ml-4", children: [_jsx("p", { className: "text-sm font-medium text-gray-600", children: "T\u1ED5ng ng\u01B0\u1EDDi d\u00F9ng" }), _jsx("p", { className: "text-2xl font-bold text-gray-900", children: users.length })] })] }) }), _jsx("div", { className: "bg-white p-6 rounded-lg border border-gray-200", children: _jsxs("div", { className: "flex items-center", children: [_jsx("div", { className: "p-2 bg-green-100 rounded-lg", children: _jsx(ShieldCheckIcon, { className: "w-6 h-6 text-green-600" }) }), _jsxs("div", { className: "ml-4", children: [_jsx("p", { className: "text-sm font-medium text-gray-600", children: "\u0110ang ho\u1EA1t \u0111\u1ED9ng" }), _jsx("p", { className: "text-2xl font-bold text-gray-900", children: users.filter(u => u.status === 'active').length })] })] }) }), _jsx("div", { className: "bg-white p-6 rounded-lg border border-gray-200", children: _jsxs("div", { className: "flex items-center", children: [_jsx("div", { className: "p-2 bg-yellow-100 rounded-lg", children: _jsx(KeyIcon, { className: "w-6 h-6 text-yellow-600" }) }), _jsxs("div", { className: "ml-4", children: [_jsx("p", { className: "text-sm font-medium text-gray-600", children: "Kh\u00F4ng ho\u1EA1t \u0111\u1ED9ng" }), _jsx("p", { className: "text-2xl font-bold text-gray-900", children: users.filter(u => u.status === 'inactive').length })] })] }) }), _jsx("div", { className: "bg-white p-6 rounded-lg border border-gray-200", children: _jsxs("div", { className: "flex items-center", children: [_jsx("div", { className: "p-2 bg-red-100 rounded-lg", children: _jsx(TrashIcon, { className: "w-6 h-6 text-red-600" }) }), _jsxs("div", { className: "ml-4", children: [_jsx("p", { className: "text-sm font-medium text-gray-600", children: "B\u1ECB kh\u00F3a" }), _jsx("p", { className: "text-2xl font-bold text-gray-900", children: users.filter(u => u.status === 'suspended').length })] })] }) })] }), _jsx("div", { className: "bg-white rounded-lg border border-gray-200", children: _jsx(Table, { data: filteredUsers, columns: columns, loading: loading }) }), _jsx(Modal, { isOpen: isCreateModalOpen, onClose: () => {
                        setIsCreateModalOpen(false);
                        resetForm();
                    }, title: "Th\u00EAm ng\u01B0\u1EDDi d\u00F9ng m\u1EDBi", children: _jsxs("div", { className: "space-y-4", children: [_jsx(Input, { label: "T\u00EAn ng\u01B0\u1EDDi d\u00F9ng", value: formData.name, onChange: (e) => setFormData({ ...formData, name: e.target.value }), placeholder: "Nh\u1EADp t\u00EAn ng\u01B0\u1EDDi d\u00F9ng" }), _jsx(Input, { label: "Email", type: "email", value: formData.email, onChange: (e) => setFormData({ ...formData, email: e.target.value }), placeholder: "Nh\u1EADp email" }), _jsx(Input, { label: "M\u1EADt kh\u1EA9u", type: "password", value: formData.password, onChange: (e) => setFormData({ ...formData, password: e.target.value }), placeholder: "Nh\u1EADp m\u1EADt kh\u1EA9u" }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: "Quy\u1EC1n" }), _jsx("div", { className: "space-y-2 max-h-40 overflow-y-auto", children: roles.map(role => (_jsxs("label", { className: "flex items-center", children: [_jsx("input", { type: "checkbox", checked: formData.roleIds.includes(role.id), onChange: (e) => {
                                                        if (e.target.checked) {
                                                            setFormData({
                                                                ...formData,
                                                                roleIds: [...formData.roleIds, role.id]
                                                            });
                                                        }
                                                        else {
                                                            setFormData({
                                                                ...formData,
                                                                roleIds: formData.roleIds.filter(id => id !== role.id)
                                                            });
                                                        }
                                                    }, className: "mr-2" }), _jsx("span", { className: "text-sm", children: role.name })] }, role.id))) })] }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: "Tr\u1EA1ng th\u00E1i" }), _jsxs("select", { value: formData.status, onChange: (e) => setFormData({
                                            ...formData,
                                            status: e.target.value
                                        }), className: "w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500", children: [_jsx("option", { value: "active", children: "Ho\u1EA1t \u0111\u1ED9ng" }), _jsx("option", { value: "inactive", children: "Kh\u00F4ng ho\u1EA1t \u0111\u1ED9ng" }), _jsx("option", { value: "suspended", children: "B\u1ECB kh\u00F3a" })] })] }), _jsxs("div", { className: "flex justify-end space-x-3 pt-4", children: [_jsx(Button, { variant: "outline", onClick: () => {
                                            setIsCreateModalOpen(false);
                                            resetForm();
                                        }, children: "H\u1EE7y" }), _jsx(Button, { onClick: handleCreateUser, children: "T\u1EA1o ng\u01B0\u1EDDi d\u00F9ng" })] })] }) }), _jsx(Modal, { isOpen: isEditModalOpen, onClose: () => {
                        setIsEditModalOpen(false);
                        resetForm();
                    }, title: "Ch\u1EC9nh s\u1EEDa ng\u01B0\u1EDDi d\u00F9ng", children: _jsxs("div", { className: "space-y-4", children: [_jsx(Input, { label: "T\u00EAn ng\u01B0\u1EDDi d\u00F9ng", value: formData.name, onChange: (e) => setFormData({ ...formData, name: e.target.value }), placeholder: "Nh\u1EADp t\u00EAn ng\u01B0\u1EDDi d\u00F9ng" }), _jsx(Input, { label: "Email", type: "email", value: formData.email, onChange: (e) => setFormData({ ...formData, email: e.target.value }), placeholder: "Nh\u1EADp email" }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: "Tr\u1EA1ng th\u00E1i" }), _jsxs("select", { value: formData.status, onChange: (e) => setFormData({
                                            ...formData,
                                            status: e.target.value
                                        }), className: "w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500", children: [_jsx("option", { value: "active", children: "Ho\u1EA1t \u0111\u1ED9ng" }), _jsx("option", { value: "inactive", children: "Kh\u00F4ng ho\u1EA1t \u0111\u1ED9ng" }), _jsx("option", { value: "suspended", children: "B\u1ECB kh\u00F3a" })] })] }), _jsxs("div", { className: "flex justify-end space-x-3 pt-4", children: [_jsx(Button, { variant: "outline", onClick: () => {
                                            setIsEditModalOpen(false);
                                            resetForm();
                                        }, children: "H\u1EE7y" }), _jsx(Button, { onClick: handleUpdateUser, children: "C\u1EADp nh\u1EADt" })] })] }) }), _jsx(Modal, { isOpen: isViewModalOpen, onClose: () => {
                        setIsViewModalOpen(false);
                        setSelectedUser(null);
                    }, title: "Th\u00F4ng tin ng\u01B0\u1EDDi d\u00F9ng", children: selectedUser && (_jsxs("div", { className: "space-y-4", children: [_jsxs("div", { className: "flex items-center space-x-4", children: [_jsx("div", { className: "w-16 h-16 bg-blue-500 rounded-full flex items-center justify-center text-white text-xl font-bold", children: selectedUser.name.charAt(0).toUpperCase() }), _jsxs("div", { children: [_jsx("h3", { className: "text-lg font-medium text-gray-900", children: selectedUser.name }), _jsx("p", { className: "text-gray-600", children: selectedUser.email })] })] }), _jsxs("div", { className: "grid grid-cols-2 gap-4", children: [_jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700", children: "Tr\u1EA1ng th\u00E1i" }), _jsx("span", { className: `inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${selectedUser.status === 'active' ? 'bg-green-100 text-green-800' :
                                                    selectedUser.status === 'inactive' ? 'bg-gray-100 text-gray-800' :
                                                        'bg-red-100 text-red-800'}`, children: selectedUser.status === 'active' ? 'Hoạt động' :
                                                    selectedUser.status === 'inactive' ? 'Không hoạt động' : 'Bị khóa' })] }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700", children: "\u0110\u0103ng nh\u1EADp cu\u1ED1i" }), _jsx("p", { className: "text-sm text-gray-900", children: selectedUser.lastLoginAt ? formatDate(selectedUser.lastLoginAt) : 'Chưa đăng nhập' })] })] }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: "Quy\u1EC1n" }), _jsx("div", { className: "flex flex-wrap gap-2", children: selectedUser.roles.map(role => (_jsx("span", { className: "inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800", children: role.name }, role.id))) })] }), _jsxs("div", { className: "grid grid-cols-2 gap-4", children: [_jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700", children: "Ng\u00E0y t\u1EA1o" }), _jsx("p", { className: "text-sm text-gray-900", children: formatDate(selectedUser.createdAt) })] }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700", children: "C\u1EADp nh\u1EADt cu\u1ED1i" }), _jsx("p", { className: "text-sm text-gray-900", children: formatDate(selectedUser.updatedAt) })] })] })] })) }), _jsx(Modal, { isOpen: isResetPasswordModalOpen, onClose: () => {
                        setIsResetPasswordModalOpen(false);
                        setNewPassword('');
                        setSelectedUser(null);
                    }, title: "\u0110\u1EB7t l\u1EA1i m\u1EADt kh\u1EA9u", children: _jsxs("div", { className: "space-y-4", children: [_jsxs("p", { className: "text-sm text-gray-600", children: ["\u0110\u1EB7t l\u1EA1i m\u1EADt kh\u1EA9u cho ng\u01B0\u1EDDi d\u00F9ng: ", _jsx("strong", { children: selectedUser?.name })] }), _jsx(Input, { label: "M\u1EADt kh\u1EA9u m\u1EDBi", type: "password", value: newPassword, onChange: (e) => setNewPassword(e.target.value), placeholder: "Nh\u1EADp m\u1EADt kh\u1EA9u m\u1EDBi" }), _jsxs("div", { className: "flex justify-end space-x-3 pt-4", children: [_jsx(Button, { variant: "outline", onClick: () => {
                                            setIsResetPasswordModalOpen(false);
                                            setNewPassword('');
                                            setSelectedUser(null);
                                        }, children: "H\u1EE7y" }), _jsx(Button, { onClick: handleResetPassword, children: "\u0110\u1EB7t l\u1EA1i m\u1EADt kh\u1EA9u" })] })] }) }), _jsx(Modal, { isOpen: isAssignRoleModalOpen, onClose: () => {
                        setIsAssignRoleModalOpen(false);
                        setSelectedRoleIds([]);
                        setSelectedUser(null);
                    }, title: "Ph\u00E2n quy\u1EC1n", children: _jsxs("div", { className: "space-y-4", children: [_jsxs("p", { className: "text-sm text-gray-600", children: ["Ph\u00E2n quy\u1EC1n cho ng\u01B0\u1EDDi d\u00F9ng: ", _jsx("strong", { children: selectedUser?.name })] }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: "Ch\u1ECDn quy\u1EC1n" }), _jsx("div", { className: "space-y-2 max-h-60 overflow-y-auto", children: roles.map(role => (_jsxs("label", { className: "flex items-center", children: [_jsx("input", { type: "checkbox", checked: selectedRoleIds.includes(role.id), onChange: (e) => {
                                                        if (e.target.checked) {
                                                            setSelectedRoleIds([...selectedRoleIds, role.id]);
                                                        }
                                                        else {
                                                            setSelectedRoleIds(selectedRoleIds.filter(id => id !== role.id));
                                                        }
                                                    }, className: "mr-3" }), _jsxs("div", { children: [_jsx("span", { className: "text-sm font-medium", children: role.name }), role.description && (_jsx("p", { className: "text-xs text-gray-500", children: role.description }))] })] }, role.id))) })] }), _jsxs("div", { className: "flex justify-end space-x-3 pt-4", children: [_jsx(Button, { variant: "outline", onClick: () => {
                                            setIsAssignRoleModalOpen(false);
                                            setSelectedRoleIds([]);
                                            setSelectedUser(null);
                                        }, children: "H\u1EE7y" }), _jsx(Button, { onClick: handleAssignRoles, children: "Ph\u00E2n quy\u1EC1n" })] })] }) }), _jsx(Modal, { isOpen: isDeleteModalOpen, onClose: () => {
                        setIsDeleteModalOpen(false);
                        setSelectedUser(null);
                    }, title: "X\u00E1c nh\u1EADn x\u00F3a", children: _jsxs("div", { className: "space-y-4", children: [_jsxs("p", { className: "text-sm text-gray-600", children: ["B\u1EA1n c\u00F3 ch\u1EAFc ch\u1EAFn mu\u1ED1n x\u00F3a ng\u01B0\u1EDDi d\u00F9ng ", _jsx("strong", { children: selectedUser?.name }), "? H\u00E0nh \u0111\u1ED9ng n\u00E0y kh\u00F4ng th\u1EC3 ho\u00E0n t\u00E1c."] }), _jsxs("div", { className: "flex justify-end space-x-3 pt-4", children: [_jsx(Button, { variant: "outline", onClick: () => {
                                            setIsDeleteModalOpen(false);
                                            setSelectedUser(null);
                                        }, children: "H\u1EE7y" }), _jsx(Button, { variant: "danger", onClick: handleDeleteUser, children: "X\u00F3a ng\u01B0\u1EDDi d\u00F9ng" })] })] }) })] }) }));
};
