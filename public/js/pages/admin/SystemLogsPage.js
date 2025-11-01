import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { useState, useEffect } from 'react';
import { Search, Filter, Download, RefreshCw, AlertTriangle, Info, CheckCircle, XCircle, Eye, Clock, User, Activity } from 'lucide-react';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import { Modal } from '../../components/ui/Modal';
import { Table } from '../../components/ui/Table';
const SystemLogsPage = () => {
    // State management
    const [logs, setLogs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedLog, setSelectedLog] = useState(null);
    const [showLogDetail, setShowLogDetail] = useState(false);
    const [showFilters, setShowFilters] = useState(false);
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [stats, setStats] = useState({
        total: 0,
        emergency: 0,
        alert: 0,
        critical: 0,
        error: 0,
        warning: 0,
        notice: 0,
        info: 0,
        debug: 0,
        todayCount: 0,
        weekCount: 0
    });
    // Filters state
    const [filters, setFilters] = useState({
        level: '',
        channel: '',
        dateFrom: '',
        dateTo: '',
        userId: '',
        ipAddress: ''
    });
    // Mock data cho demo
    const mockLogs = [
        {
            id: '1',
            level: 'error',
            message: 'Database connection failed',
            context: { database: 'mysql', host: 'localhost', port: 3306 },
            channel: 'database',
            datetime: '2024-01-15 10:30:25',
            extra: { memory_usage: '128MB', execution_time: '2.5s' },
            user_id: 'user_1',
            user_name: 'Nguyễn Văn A',
            ip_address: '192.168.1.100',
            user_agent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            request_id: 'req_123456',
            session_id: 'sess_789012'
        },
        {
            id: '2',
            level: 'warning',
            message: 'High memory usage detected',
            context: { memory_usage: '85%', threshold: '80%' },
            channel: 'system',
            datetime: '2024-01-15 10:25:15',
            extra: { cpu_usage: '45%', disk_usage: '60%' },
            ip_address: '192.168.1.101',
            user_agent: 'Chrome/120.0.0.0',
            request_id: 'req_123457'
        },
        {
            id: '3',
            level: 'info',
            message: 'User login successful',
            context: { user_id: 'user_2', login_method: 'email' },
            channel: 'auth',
            datetime: '2024-01-15 10:20:10',
            extra: { login_attempts: 1, last_login: '2024-01-14 15:30:00' },
            user_id: 'user_2',
            user_name: 'Trần Thị B',
            ip_address: '192.168.1.102',
            user_agent: 'Safari/17.0',
            request_id: 'req_123458',
            session_id: 'sess_789013'
        },
        {
            id: '4',
            level: 'critical',
            message: 'Disk space critically low',
            context: { available_space: '2GB', total_space: '100GB', usage: '98%' },
            channel: 'system',
            datetime: '2024-01-15 10:15:05',
            extra: { partition: '/var/log', alert_threshold: '95%' },
            request_id: 'req_123459'
        },
        {
            id: '5',
            level: 'debug',
            message: 'API request processed',
            context: { endpoint: '/api/v1/projects', method: 'GET', response_time: '150ms' },
            channel: 'api',
            datetime: '2024-01-15 10:10:00',
            extra: { request_size: '2KB', response_size: '15KB' },
            user_id: 'user_3',
            user_name: 'Lê Văn C',
            ip_address: '192.168.1.103',
            user_agent: 'Postman/10.0.0',
            request_id: 'req_123460',
            session_id: 'sess_789014'
        }
    ];
    const mockStats = {
        total: 1250,
        emergency: 2,
        alert: 5,
        critical: 8,
        error: 45,
        warning: 120,
        notice: 200,
        info: 650,
        debug: 220,
        todayCount: 85,
        weekCount: 420
    };
    // Load data
    useEffect(() => {
        loadLogs();
        loadStats();
    }, [currentPage, filters, searchTerm]);
    const loadLogs = async () => {
        setLoading(true);
        try {
            // Simulate API call
            await new Promise(resolve => setTimeout(resolve, 1000));
            let filteredLogs = [...mockLogs];
            // Apply search filter
            if (searchTerm) {
                filteredLogs = filteredLogs.filter(log => log.message.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    log.channel.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    log.user_name?.toLowerCase().includes(searchTerm.toLowerCase()));
            }
            // Apply level filter
            if (filters.level) {
                filteredLogs = filteredLogs.filter(log => log.level === filters.level);
            }
            // Apply channel filter
            if (filters.channel) {
                filteredLogs = filteredLogs.filter(log => log.channel === filters.channel);
            }
            // Apply user filter
            if (filters.userId) {
                filteredLogs = filteredLogs.filter(log => log.user_id === filters.userId);
            }
            // Apply IP filter
            if (filters.ipAddress) {
                filteredLogs = filteredLogs.filter(log => log.ip_address?.includes(filters.ipAddress));
            }
            setLogs(filteredLogs);
            setTotalPages(Math.ceil(filteredLogs.length / 20));
        }
        catch (error) {
            console.error('Error loading logs:', error);
        }
        finally {
            setLoading(false);
        }
    };
    const loadStats = async () => {
        try {
            // Simulate API call
            await new Promise(resolve => setTimeout(resolve, 500));
            setStats(mockStats);
        }
        catch (error) {
            console.error('Error loading stats:', error);
        }
    };
    // Utility functions
    const getLevelIcon = (level) => {
        switch (level) {
            case 'emergency':
            case 'alert':
            case 'critical':
                return _jsx(XCircle, { className: "w-4 h-4 text-red-500" });
            case 'error':
                return _jsx(AlertTriangle, { className: "w-4 h-4 text-red-400" });
            case 'warning':
                return _jsx(AlertTriangle, { className: "w-4 h-4 text-yellow-500" });
            case 'notice':
            case 'info':
                return _jsx(Info, { className: "w-4 h-4 text-blue-500" });
            case 'debug':
                return _jsx(CheckCircle, { className: "w-4 h-4 text-gray-500" });
            default:
                return _jsx(Info, { className: "w-4 h-4 text-gray-500" });
        }
    };
    const getLevelColor = (level) => {
        switch (level) {
            case 'emergency':
            case 'alert':
            case 'critical':
                return 'bg-red-100 text-red-800';
            case 'error':
                return 'bg-red-50 text-red-700';
            case 'warning':
                return 'bg-yellow-100 text-yellow-800';
            case 'notice':
            case 'info':
                return 'bg-blue-100 text-blue-800';
            case 'debug':
                return 'bg-gray-100 text-gray-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    };
    const formatDateTime = (datetime) => {
        return new Date(datetime).toLocaleString('vi-VN');
    };
    const handleViewLog = (log) => {
        setSelectedLog(log);
        setShowLogDetail(true);
    };
    const handleExportLogs = async () => {
        try {
            // Simulate export
            const csvContent = logs.map(log => `"${log.datetime}","${log.level}","${log.channel}","${log.message}","${log.user_name || ''}","${log.ip_address || ''}"`).join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `system-logs-${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }
        catch (error) {
            console.error('Error exporting logs:', error);
        }
    };
    const handleClearFilters = () => {
        setFilters({
            level: '',
            channel: '',
            dateFrom: '',
            dateTo: '',
            userId: '',
            ipAddress: ''
        });
        setSearchTerm('');
    };
    const handleRefresh = () => {
        loadLogs();
        loadStats();
    };
    // Table columns
    const columns = [
        {
            key: 'datetime',
            label: 'Thời gian',
            render: (log) => (_jsx("div", { className: "text-sm", children: _jsx("div", { className: "font-medium", children: formatDateTime(log.datetime) }) }))
        },
        {
            key: 'level',
            label: 'Mức độ',
            render: (log) => (_jsxs("div", { className: "flex items-center space-x-2", children: [getLevelIcon(log.level), _jsx("span", { className: `px-2 py-1 rounded-full text-xs font-medium ${getLevelColor(log.level)}`, children: log.level.toUpperCase() })] }))
        },
        {
            key: 'channel',
            label: 'Kênh',
            render: (log) => (_jsx("span", { className: "px-2 py-1 bg-gray-100 text-gray-800 rounded text-sm", children: log.channel }))
        },
        {
            key: 'message',
            label: 'Thông điệp',
            render: (log) => (_jsxs("div", { className: "max-w-md", children: [_jsx("div", { className: "text-sm font-medium text-gray-900 truncate", children: log.message }), log.user_name && (_jsxs("div", { className: "text-xs text-gray-500 mt-1", children: ["Ng\u01B0\u1EDDi d\u00F9ng: ", log.user_name] }))] }))
        },
        {
            key: 'ip_address',
            label: 'IP Address',
            render: (log) => (_jsx("span", { className: "text-sm text-gray-600", children: log.ip_address || '-' }))
        },
        {
            key: 'actions',
            label: 'Hành động',
            render: (log) => (_jsx("div", { className: "flex items-center space-x-2", children: _jsx(Button, { variant: "ghost", size: "sm", onClick: () => handleViewLog(log), className: "text-blue-600 hover:text-blue-800", children: _jsx(Eye, { className: "w-4 h-4" }) }) }))
        }
    ];
    return (_jsxs("div", { className: "space-y-6", children: [_jsxs("div", { className: "flex justify-between items-center", children: [_jsxs("div", { children: [_jsx("h1", { className: "text-2xl font-bold text-gray-900", children: "System Logs" }), _jsx("p", { className: "text-gray-600 mt-1", children: "Qu\u1EA3n l\u00FD v\u00E0 theo d\u00F5i logs h\u1EC7 th\u1ED1ng" })] }), _jsxs("div", { className: "flex items-center space-x-3", children: [_jsxs(Button, { variant: "outline", onClick: handleRefresh, className: "flex items-center space-x-2", children: [_jsx(RefreshCw, { className: "w-4 h-4" }), _jsx("span", { children: "L\u00E0m m\u1EDBi" })] }), _jsxs(Button, { variant: "outline", onClick: handleExportLogs, className: "flex items-center space-x-2", children: [_jsx(Download, { className: "w-4 h-4" }), _jsx("span", { children: "Xu\u1EA5t CSV" })] })] })] }), _jsxs("div", { className: "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6", children: [_jsxs("div", { className: "bg-white p-6 rounded-lg shadow-sm border", children: [_jsxs("div", { className: "flex items-center justify-between", children: [_jsxs("div", { children: [_jsx("p", { className: "text-sm font-medium text-gray-600", children: "T\u1ED5ng Logs" }), _jsx("p", { className: "text-2xl font-bold text-gray-900", children: stats.total.toLocaleString() })] }), _jsx(Activity, { className: "w-8 h-8 text-blue-500" })] }), _jsxs("div", { className: "mt-2 text-sm text-gray-500", children: ["H\u00F4m nay: ", stats.todayCount, " logs"] })] }), _jsxs("div", { className: "bg-white p-6 rounded-lg shadow-sm border", children: [_jsxs("div", { className: "flex items-center justify-between", children: [_jsxs("div", { children: [_jsx("p", { className: "text-sm font-medium text-gray-600", children: "Errors" }), _jsx("p", { className: "text-2xl font-bold text-red-600", children: stats.error })] }), _jsx(XCircle, { className: "w-8 h-8 text-red-500" })] }), _jsxs("div", { className: "mt-2 text-sm text-gray-500", children: ["Critical: ", stats.critical] })] }), _jsxs("div", { className: "bg-white p-6 rounded-lg shadow-sm border", children: [_jsxs("div", { className: "flex items-center justify-between", children: [_jsxs("div", { children: [_jsx("p", { className: "text-sm font-medium text-gray-600", children: "Warnings" }), _jsx("p", { className: "text-2xl font-bold text-yellow-600", children: stats.warning })] }), _jsx(AlertTriangle, { className: "w-8 h-8 text-yellow-500" })] }), _jsxs("div", { className: "mt-2 text-sm text-gray-500", children: ["Tu\u1EA7n n\u00E0y: ", stats.weekCount, " logs"] })] }), _jsxs("div", { className: "bg-white p-6 rounded-lg shadow-sm border", children: [_jsxs("div", { className: "flex items-center justify-between", children: [_jsxs("div", { children: [_jsx("p", { className: "text-sm font-medium text-gray-600", children: "Info" }), _jsx("p", { className: "text-2xl font-bold text-blue-600", children: stats.info })] }), _jsx(Info, { className: "w-8 h-8 text-blue-500" })] }), _jsxs("div", { className: "mt-2 text-sm text-gray-500", children: ["Debug: ", stats.debug] })] })] }), _jsxs("div", { className: "bg-white p-6 rounded-lg shadow-sm border", children: [_jsxs("div", { className: "flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0", children: [_jsx("div", { className: "flex-1 max-w-md", children: _jsxs("div", { className: "relative", children: [_jsx(Search, { className: "absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" }), _jsx(Input, { type: "text", placeholder: "T\u00ECm ki\u1EBFm logs...", value: searchTerm, onChange: (e) => setSearchTerm(e.target.value), className: "pl-10" })] }) }), _jsxs("div", { className: "flex items-center space-x-3", children: [_jsxs(Button, { variant: "outline", onClick: () => setShowFilters(!showFilters), className: "flex items-center space-x-2", children: [_jsx(Filter, { className: "w-4 h-4" }), _jsx("span", { children: "B\u1ED9 l\u1ECDc" })] }), (filters.level || filters.channel || filters.userId || filters.ipAddress || filters.dateFrom) && (_jsx(Button, { variant: "ghost", onClick: handleClearFilters, className: "text-red-600 hover:text-red-800", children: "X\u00F3a b\u1ED9 l\u1ECDc" }))] })] }), showFilters && (_jsx("div", { className: "mt-6 pt-6 border-t border-gray-200", children: _jsxs("div", { className: "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4", children: [_jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: "M\u1EE9c \u0111\u1ED9" }), _jsxs("select", { value: filters.level, onChange: (e) => setFilters({ ...filters, level: e.target.value }), className: "w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500", children: [_jsx("option", { value: "", children: "T\u1EA5t c\u1EA3 m\u1EE9c \u0111\u1ED9" }), _jsx("option", { value: "emergency", children: "Emergency" }), _jsx("option", { value: "alert", children: "Alert" }), _jsx("option", { value: "critical", children: "Critical" }), _jsx("option", { value: "error", children: "Error" }), _jsx("option", { value: "warning", children: "Warning" }), _jsx("option", { value: "notice", children: "Notice" }), _jsx("option", { value: "info", children: "Info" }), _jsx("option", { value: "debug", children: "Debug" })] })] }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: "K\u00EAnh" }), _jsxs("select", { value: filters.channel, onChange: (e) => setFilters({ ...filters, channel: e.target.value }), className: "w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500", children: [_jsx("option", { value: "", children: "T\u1EA5t c\u1EA3 k\u00EAnh" }), _jsx("option", { value: "system", children: "System" }), _jsx("option", { value: "database", children: "Database" }), _jsx("option", { value: "auth", children: "Authentication" }), _jsx("option", { value: "api", children: "API" }), _jsx("option", { value: "security", children: "Security" }), _jsx("option", { value: "performance", children: "Performance" })] })] }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: "IP Address" }), _jsx(Input, { type: "text", placeholder: "192.168.1.100", value: filters.ipAddress, onChange: (e) => setFilters({ ...filters, ipAddress: e.target.value }) })] }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: "T\u1EEB ng\u00E0y" }), _jsx(Input, { type: "datetime-local", value: filters.dateFrom, onChange: (e) => setFilters({ ...filters, dateFrom: e.target.value }) })] }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: "\u0110\u1EBFn ng\u00E0y" }), _jsx(Input, { type: "datetime-local", value: filters.dateTo, onChange: (e) => setFilters({ ...filters, dateTo: e.target.value }) })] }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: "User ID" }), _jsx(Input, { type: "text", placeholder: "user_123", value: filters.userId, onChange: (e) => setFilters({ ...filters, userId: e.target.value }) })] })] }) }))] }), _jsxs("div", { className: "bg-white rounded-lg shadow-sm border", children: [_jsx(Table, { data: logs, columns: columns, loading: loading, emptyMessage: "Kh\u00F4ng c\u00F3 logs n\u00E0o \u0111\u01B0\u1EE3c t\u00ECm th\u1EA5y" }), totalPages > 1 && (_jsx("div", { className: "px-6 py-4 border-t border-gray-200", children: _jsxs("div", { className: "flex items-center justify-between", children: [_jsxs("div", { className: "text-sm text-gray-700", children: ["Trang ", currentPage, " / ", totalPages] }), _jsxs("div", { className: "flex items-center space-x-2", children: [_jsx(Button, { variant: "outline", size: "sm", onClick: () => setCurrentPage(Math.max(1, currentPage - 1)), disabled: currentPage === 1, children: "Tr\u01B0\u1EDBc" }), _jsx(Button, { variant: "outline", size: "sm", onClick: () => setCurrentPage(Math.min(totalPages, currentPage + 1)), disabled: currentPage === totalPages, children: "Sau" })] })] }) }))] }), _jsx(Modal, { isOpen: showLogDetail, onClose: () => setShowLogDetail(false), title: "Chi ti\u1EBFt Log", size: "lg", children: selectedLog && (_jsxs("div", { className: "space-y-6", children: [_jsxs("div", { className: "grid grid-cols-2 gap-4", children: [_jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-1", children: "Th\u1EDDi gian" }), _jsxs("div", { className: "flex items-center space-x-2", children: [_jsx(Clock, { className: "w-4 h-4 text-gray-400" }), _jsx("span", { className: "text-sm", children: formatDateTime(selectedLog.datetime) })] })] }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-1", children: "M\u1EE9c \u0111\u1ED9" }), _jsxs("div", { className: "flex items-center space-x-2", children: [getLevelIcon(selectedLog.level), _jsx("span", { className: `px-2 py-1 rounded-full text-xs font-medium ${getLevelColor(selectedLog.level)}`, children: selectedLog.level.toUpperCase() })] })] }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-1", children: "K\u00EAnh" }), _jsx("span", { className: "px-2 py-1 bg-gray-100 text-gray-800 rounded text-sm", children: selectedLog.channel })] }), selectedLog.user_name && (_jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-1", children: "Ng\u01B0\u1EDDi d\u00F9ng" }), _jsxs("div", { className: "flex items-center space-x-2", children: [_jsx(User, { className: "w-4 h-4 text-gray-400" }), _jsx("span", { className: "text-sm", children: selectedLog.user_name })] })] }))] }), _jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: "Th\u00F4ng \u0111i\u1EC7p" }), _jsx("div", { className: "bg-gray-50 p-4 rounded-lg", children: _jsx("p", { className: "text-sm text-gray-900", children: selectedLog.message }) })] }), Object.keys(selectedLog.context).length > 0 && (_jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: "Context" }), _jsx("div", { className: "bg-gray-50 p-4 rounded-lg", children: _jsx("pre", { className: "text-xs text-gray-800 whitespace-pre-wrap", children: JSON.stringify(selectedLog.context, null, 2) }) })] })), Object.keys(selectedLog.extra).length > 0 && (_jsxs("div", { children: [_jsx("label", { className: "block text-sm font-medium text-gray-700 mb-2", children: "Th\u00F4ng tin b\u1ED5 sung" }), _jsx("div", { className: "bg-gray-50 p-4 rounded-lg", children: _jsx("pre", { className: "text-xs text-gray-800 whitespace-pre-wrap", children: JSON.stringify(selectedLog.extra, null, 2) }) })] })), _jsxs("div", { className: "border-t pt-4", children: [_jsx("h4", { className: "text-sm font-medium text-gray-700 mb-3", children: "Th\u00F4ng tin k\u1EF9 thu\u1EADt" }), _jsxs("div", { className: "grid grid-cols-1 gap-3 text-sm", children: [selectedLog.ip_address && (_jsxs("div", { className: "flex justify-between", children: [_jsx("span", { className: "text-gray-600", children: "IP Address:" }), _jsx("span", { className: "text-gray-900", children: selectedLog.ip_address })] })), selectedLog.request_id && (_jsxs("div", { className: "flex justify-between", children: [_jsx("span", { className: "text-gray-600", children: "Request ID:" }), _jsx("span", { className: "text-gray-900 font-mono", children: selectedLog.request_id })] })), selectedLog.session_id && (_jsxs("div", { className: "flex justify-between", children: [_jsx("span", { className: "text-gray-600", children: "Session ID:" }), _jsx("span", { className: "text-gray-900 font-mono", children: selectedLog.session_id })] })), selectedLog.user_agent && (_jsxs("div", { className: "flex justify-between", children: [_jsx("span", { className: "text-gray-600", children: "User Agent:" }), _jsx("span", { className: "text-gray-900 text-xs truncate max-w-xs", children: selectedLog.user_agent })] }))] })] })] })) })] }));
};
export default SystemLogsPage;
