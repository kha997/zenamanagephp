import React, { useState, useEffect } from 'react';
import { Search, Filter, Download, RefreshCw, AlertTriangle, Info, CheckCircle, XCircle, Eye, Calendar, Clock, User, Activity } from 'lucide-react';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import { Modal } from '../../components/ui/Modal';
import { Table } from '../../components/ui/Table';

// Types cho System Logs
interface SystemLog {
  id: string;
  level: 'emergency' | 'alert' | 'critical' | 'error' | 'warning' | 'notice' | 'info' | 'debug';
  message: string;
  context: Record<string, any>;
  channel: string;
  datetime: string;
  extra: Record<string, any>;
  user_id?: string;
  user_name?: string;
  ip_address?: string;
  user_agent?: string;
  request_id?: string;
  session_id?: string;
}

interface LogFilters {
  level: string;
  channel: string;
  dateFrom: string;
  dateTo: string;
  userId: string;
  ipAddress: string;
}

interface LogStats {
  total: number;
  emergency: number;
  alert: number;
  critical: number;
  error: number;
  warning: number;
  notice: number;
  info: number;
  debug: number;
  todayCount: number;
  weekCount: number;
}

const SystemLogsPage: React.FC = () => {
  // State management
  const [logs, setLogs] = useState<SystemLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedLog, setSelectedLog] = useState<SystemLog | null>(null);
  const [showLogDetail, setShowLogDetail] = useState(false);
  const [showFilters, setShowFilters] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [stats, setStats] = useState<LogStats>({
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
  const [filters, setFilters] = useState<LogFilters>({
    level: '',
    channel: '',
    dateFrom: '',
    dateTo: '',
    userId: '',
    ipAddress: ''
  });

  // Mock data cho demo
  const mockLogs: SystemLog[] = [
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

  const mockStats: LogStats = {
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
        filteredLogs = filteredLogs.filter(log => 
          log.message.toLowerCase().includes(searchTerm.toLowerCase()) ||
          log.channel.toLowerCase().includes(searchTerm.toLowerCase()) ||
          log.user_name?.toLowerCase().includes(searchTerm.toLowerCase())
        );
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
    } catch (error) {
      console.error('Error loading logs:', error);
    } finally {
      setLoading(false);
    }
  };

  const loadStats = async () => {
    try {
      // Simulate API call
      await new Promise(resolve => setTimeout(resolve, 500));
      setStats(mockStats);
    } catch (error) {
      console.error('Error loading stats:', error);
    }
  };

  // Utility functions
  const getLevelIcon = (level: string) => {
    switch (level) {
      case 'emergency':
      case 'alert':
      case 'critical':
        return <XCircle className="w-4 h-4 text-red-500" />;
      case 'error':
        return <AlertTriangle className="w-4 h-4 text-red-400" />;
      case 'warning':
        return <AlertTriangle className="w-4 h-4 text-yellow-500" />;
      case 'notice':
      case 'info':
        return <Info className="w-4 h-4 text-blue-500" />;
      case 'debug':
        return <CheckCircle className="w-4 h-4 text-gray-500" />;
      default:
        return <Info className="w-4 h-4 text-gray-500" />;
    }
  };

  const getLevelColor = (level: string) => {
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

  const formatDateTime = (datetime: string) => {
    return new Date(datetime).toLocaleString('vi-VN');
  };

  const handleViewLog = (log: SystemLog) => {
    setSelectedLog(log);
    setShowLogDetail(true);
  };

  const handleExportLogs = async () => {
    try {
      // Simulate export
      const csvContent = logs.map(log => 
        `"${log.datetime}","${log.level}","${log.channel}","${log.message}","${log.user_name || ''}","${log.ip_address || ''}"`
      ).join('\n');
      
      const blob = new Blob([csvContent], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `system-logs-${new Date().toISOString().split('T')[0]}.csv`;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch (error) {
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
      render: (log: SystemLog) => (
        <div className="text-sm">
          <div className="font-medium">{formatDateTime(log.datetime)}</div>
        </div>
      )
    },
    {
      key: 'level',
      label: 'Mức độ',
      render: (log: SystemLog) => (
        <div className="flex items-center space-x-2">
          {getLevelIcon(log.level)}
          <span className={`px-2 py-1 rounded-full text-xs font-medium ${getLevelColor(log.level)}`}>
            {log.level.toUpperCase()}
          </span>
        </div>
      )
    },
    {
      key: 'channel',
      label: 'Kênh',
      render: (log: SystemLog) => (
        <span className="px-2 py-1 bg-gray-100 text-gray-800 rounded text-sm">
          {log.channel}
        </span>
      )
    },
    {
      key: 'message',
      label: 'Thông điệp',
      render: (log: SystemLog) => (
        <div className="max-w-md">
          <div className="text-sm font-medium text-gray-900 truncate">
            {log.message}
          </div>
          {log.user_name && (
            <div className="text-xs text-gray-500 mt-1">
              Người dùng: {log.user_name}
            </div>
          )}
        </div>
      )
    },
    {
      key: 'ip_address',
      label: 'IP Address',
      render: (log: SystemLog) => (
        <span className="text-sm text-gray-600">
          {log.ip_address || '-'}
        </span>
      )
    },
    {
      key: 'actions',
      label: 'Hành động',
      render: (log: SystemLog) => (
        <div className="flex items-center space-x-2">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => handleViewLog(log)}
            className="text-blue-600 hover:text-blue-800"
          >
            <Eye className="w-4 h-4" />
          </Button>
        </div>
      )
    }
  ];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">System Logs</h1>
          <p className="text-gray-600 mt-1">Quản lý và theo dõi logs hệ thống</p>
        </div>
        <div className="flex items-center space-x-3">
          <Button
            variant="outline"
            onClick={handleRefresh}
            className="flex items-center space-x-2"
          >
            <RefreshCw className="w-4 h-4" />
            <span>Làm mới</span>
          </Button>
          <Button
            variant="outline"
            onClick={handleExportLogs}
            className="flex items-center space-x-2"
          >
            <Download className="w-4 h-4" />
            <span>Xuất CSV</span>
          </Button>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div className="bg-white p-6 rounded-lg shadow-sm border">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Tổng Logs</p>
              <p className="text-2xl font-bold text-gray-900">{stats.total.toLocaleString()}</p>
            </div>
            <Activity className="w-8 h-8 text-blue-500" />
          </div>
          <div className="mt-2 text-sm text-gray-500">
            Hôm nay: {stats.todayCount} logs
          </div>
        </div>

        <div className="bg-white p-6 rounded-lg shadow-sm border">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Errors</p>
              <p className="text-2xl font-bold text-red-600">{stats.error}</p>
            </div>
            <XCircle className="w-8 h-8 text-red-500" />
          </div>
          <div className="mt-2 text-sm text-gray-500">
            Critical: {stats.critical}
          </div>
        </div>

        <div className="bg-white p-6 rounded-lg shadow-sm border">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Warnings</p>
              <p className="text-2xl font-bold text-yellow-600">{stats.warning}</p>
            </div>
            <AlertTriangle className="w-8 h-8 text-yellow-500" />
          </div>
          <div className="mt-2 text-sm text-gray-500">
            Tuần này: {stats.weekCount} logs
          </div>
        </div>

        <div className="bg-white p-6 rounded-lg shadow-sm border">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Info</p>
              <p className="text-2xl font-bold text-blue-600">{stats.info}</p>
            </div>
            <Info className="w-8 h-8 text-blue-500" />
          </div>
          <div className="mt-2 text-sm text-gray-500">
            Debug: {stats.debug}
          </div>
        </div>
      </div>

      {/* Search and Filters */}
      <div className="bg-white p-6 rounded-lg shadow-sm border">
        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
          <div className="flex-1 max-w-md">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
              <Input
                type="text"
                placeholder="Tìm kiếm logs..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-10"
              />
            </div>
          </div>
          <div className="flex items-center space-x-3">
            <Button
              variant="outline"
              onClick={() => setShowFilters(!showFilters)}
              className="flex items-center space-x-2"
            >
              <Filter className="w-4 h-4" />
              <span>Bộ lọc</span>
            </Button>
            {(filters.level || filters.channel || filters.userId || filters.ipAddress || filters.dateFrom) && (
              <Button
                variant="ghost"
                onClick={handleClearFilters}
                className="text-red-600 hover:text-red-800"
              >
                Xóa bộ lọc
              </Button>
            )}
          </div>
        </div>

        {/* Advanced Filters */}
        {showFilters && (
          <div className="mt-6 pt-6 border-t border-gray-200">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Mức độ
                </label>
                <select
                  value={filters.level}
                  onChange={(e) => setFilters({ ...filters, level: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">Tất cả mức độ</option>
                  <option value="emergency">Emergency</option>
                  <option value="alert">Alert</option>
                  <option value="critical">Critical</option>
                  <option value="error">Error</option>
                  <option value="warning">Warning</option>
                  <option value="notice">Notice</option>
                  <option value="info">Info</option>
                  <option value="debug">Debug</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Kênh
                </label>
                <select
                  value={filters.channel}
                  onChange={(e) => setFilters({ ...filters, channel: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">Tất cả kênh</option>
                  <option value="system">System</option>
                  <option value="database">Database</option>
                  <option value="auth">Authentication</option>
                  <option value="api">API</option>
                  <option value="security">Security</option>
                  <option value="performance">Performance</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  IP Address
                </label>
                <Input
                  type="text"
                  placeholder="192.168.1.100"
                  value={filters.ipAddress}
                  onChange={(e) => setFilters({ ...filters, ipAddress: e.target.value })}
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Từ ngày
                </label>
                <Input
                  type="datetime-local"
                  value={filters.dateFrom}
                  onChange={(e) => setFilters({ ...filters, dateFrom: e.target.value })}
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Đến ngày
                </label>
                <Input
                  type="datetime-local"
                  value={filters.dateTo}
                  onChange={(e) => setFilters({ ...filters, dateTo: e.target.value })}
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  User ID
                </label>
                <Input
                  type="text"
                  placeholder="user_123"
                  value={filters.userId}
                  onChange={(e) => setFilters({ ...filters, userId: e.target.value })}
                />
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Logs Table */}
      <div className="bg-white rounded-lg shadow-sm border">
        <Table
          data={logs}
          columns={columns}
          loading={loading}
          emptyMessage="Không có logs nào được tìm thấy"
        />
        
        {/* Pagination */}
        {totalPages > 1 && (
          <div className="px-6 py-4 border-t border-gray-200">
            <div className="flex items-center justify-between">
              <div className="text-sm text-gray-700">
                Trang {currentPage} / {totalPages}
              </div>
              <div className="flex items-center space-x-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setCurrentPage(Math.max(1, currentPage - 1))}
                  disabled={currentPage === 1}
                >
                  Trước
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setCurrentPage(Math.min(totalPages, currentPage + 1))}
                  disabled={currentPage === totalPages}
                >
                  Sau
                </Button>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Log Detail Modal */}
      <Modal
        isOpen={showLogDetail}
        onClose={() => setShowLogDetail(false)}
        title="Chi tiết Log"
        size="lg"
      >
        {selectedLog && (
          <div className="space-y-6">
            {/* Basic Info */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Thời gian
                </label>
                <div className="flex items-center space-x-2">
                  <Clock className="w-4 h-4 text-gray-400" />
                  <span className="text-sm">{formatDateTime(selectedLog.datetime)}</span>
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Mức độ
                </label>
                <div className="flex items-center space-x-2">
                  {getLevelIcon(selectedLog.level)}
                  <span className={`px-2 py-1 rounded-full text-xs font-medium ${getLevelColor(selectedLog.level)}`}>
                    {selectedLog.level.toUpperCase()}
                  </span>
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Kênh
                </label>
                <span className="px-2 py-1 bg-gray-100 text-gray-800 rounded text-sm">
                  {selectedLog.channel}
                </span>
              </div>
              {selectedLog.user_name && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Người dùng
                  </label>
                  <div className="flex items-center space-x-2">
                    <User className="w-4 h-4 text-gray-400" />
                    <span className="text-sm">{selectedLog.user_name}</span>
                  </div>
                </div>
              )}
            </div>

            {/* Message */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Thông điệp
              </label>
              <div className="bg-gray-50 p-4 rounded-lg">
                <p className="text-sm text-gray-900">{selectedLog.message}</p>
              </div>
            </div>

            {/* Context */}
            {Object.keys(selectedLog.context).length > 0 && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Context
                </label>
                <div className="bg-gray-50 p-4 rounded-lg">
                  <pre className="text-xs text-gray-800 whitespace-pre-wrap">
                    {JSON.stringify(selectedLog.context, null, 2)}
                  </pre>
                </div>
              </div>
            )}

            {/* Extra Info */}
            {Object.keys(selectedLog.extra).length > 0 && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Thông tin bổ sung
                </label>
                <div className="bg-gray-50 p-4 rounded-lg">
                  <pre className="text-xs text-gray-800 whitespace-pre-wrap">
                    {JSON.stringify(selectedLog.extra, null, 2)}
                  </pre>
                </div>
              </div>
            )}

            {/* Technical Details */}
            <div className="border-t pt-4">
              <h4 className="text-sm font-medium text-gray-700 mb-3">Thông tin kỹ thuật</h4>
              <div className="grid grid-cols-1 gap-3 text-sm">
                {selectedLog.ip_address && (
                  <div className="flex justify-between">
                    <span className="text-gray-600">IP Address:</span>
                    <span className="text-gray-900">{selectedLog.ip_address}</span>
                  </div>
                )}
                {selectedLog.request_id && (
                  <div className="flex justify-between">
                    <span className="text-gray-600">Request ID:</span>
                    <span className="text-gray-900 font-mono">{selectedLog.request_id}</span>
                  </div>
                )}
                {selectedLog.session_id && (
                  <div className="flex justify-between">
                    <span className="text-gray-600">Session ID:</span>
                    <span className="text-gray-900 font-mono">{selectedLog.session_id}</span>
                  </div>
                )}
                {selectedLog.user_agent && (
                  <div className="flex justify-between">
                    <span className="text-gray-600">User Agent:</span>
                    <span className="text-gray-900 text-xs truncate max-w-xs">{selectedLog.user_agent}</span>
                  </div>
                )}
              </div>
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
};

export default SystemLogsPage;