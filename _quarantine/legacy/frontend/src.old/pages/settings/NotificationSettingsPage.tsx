import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  BellIcon,
  EnvelopeIcon,
  DevicePhoneMobileIcon,
  ComputerDesktopIcon,
  CheckIcon,
  XMarkIcon,
  PlusIcon,
  TrashIcon,
  CogIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  CheckCircleIcon
} from '@heroicons/react/24/outline';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import { Card } from '../../components/ui/Card';
import { Modal } from '../../components/ui/Modal';
import { useAuthStore } from '../../store/authStore';
import { useNotificationStore } from '../../store/notificationStore';
import { formatDate } from '../../lib/utils';

// Interface cho cài đặt thông báo chung
interface GeneralNotificationSettings {
  emailEnabled: boolean;
  inAppEnabled: boolean;
  pushEnabled: boolean;
  webhookEnabled: boolean;
  quietHours: {
    enabled: boolean;
    startTime: string;
    endTime: string;
  };
  frequency: 'immediate' | 'hourly' | 'daily' | 'weekly';
}

// Interface cho quy tắc thông báo
interface NotificationRule {
  id: string;
  name: string;
  eventKey: string;
  projectId?: string;
  projectName?: string;
  minPriority: 'low' | 'normal' | 'critical';
  channels: ('inapp' | 'email' | 'webhook')[];
  isEnabled: boolean;
  conditions?: {
    userRoles?: string[];
    taskStatus?: string[];
    customFilters?: Record<string, any>;
  };
  createdAt: string;
}

// Interface cho cài đặt kênh thông báo
interface ChannelSettings {
  email: {
    address: string;
    verified: boolean;
    frequency: 'immediate' | 'digest';
  };
  webhook: {
    url: string;
    secret: string;
    enabled: boolean;
  };
  push: {
    enabled: boolean;
    deviceTokens: string[];
  };
}

// Interface cho template thông báo
interface NotificationTemplate {
  id: string;
  name: string;
  eventKey: string;
  subject: string;
  body: string;
  isDefault: boolean;
}

export const NotificationSettingsPage: React.FC = () => {
  const navigate = useNavigate();
  const { user } = useAuthStore();
  const { addNotification } = useNotificationStore();

  // States cho cài đặt chung
  const [generalSettings, setGeneralSettings] = useState<GeneralNotificationSettings>({
    emailEnabled: true,
    inAppEnabled: true,
    pushEnabled: false,
    webhookEnabled: false,
    quietHours: {
      enabled: false,
      startTime: '22:00',
      endTime: '08:00'
    },
    frequency: 'immediate'
  });

  // States cho quy tắc thông báo
  const [notificationRules, setNotificationRules] = useState<NotificationRule[]>([
    {
      id: '1',
      name: 'Task được giao cho tôi',
      eventKey: 'task.assigned',
      minPriority: 'normal',
      channels: ['inapp', 'email'],
      isEnabled: true,
      createdAt: '2024-01-15T10:00:00Z'
    },
    {
      id: '2',
      name: 'Deadline task sắp đến',
      eventKey: 'task.deadline_approaching',
      minPriority: 'critical',
      channels: ['inapp', 'email'],
      isEnabled: true,
      createdAt: '2024-01-15T10:00:00Z'
    },
    {
      id: '3',
      name: 'Change Request cần phê duyệt',
      eventKey: 'change_request.pending_approval',
      projectId: 'proj-1',
      projectName: 'Dự án ABC',
      minPriority: 'normal',
      channels: ['inapp'],
      isEnabled: true,
      createdAt: '2024-01-15T10:00:00Z'
    }
  ]);

  // States cho cài đặt kênh
  const [channelSettings, setChannelSettings] = useState<ChannelSettings>({
    email: {
      address: 'user@company.com',
      verified: true,
      frequency: 'immediate'
    },
    webhook: {
      url: '',
      secret: '',
      enabled: false
    },
    push: {
      enabled: false,
      deviceTokens: []
    }
  });

  // States cho UI
  const [activeTab, setActiveTab] = useState<'general' | 'rules' | 'channels' | 'templates'>('general');
  const [isRuleModalOpen, setIsRuleModalOpen] = useState(false);
  const [editingRule, setEditingRule] = useState<NotificationRule | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Mock data cho projects và events
  const [projects] = useState([
    { id: 'proj-1', name: 'Dự án ABC' },
    { id: 'proj-2', name: 'Dự án XYZ' },
    { id: 'proj-3', name: 'Dự án 123' }
  ]);

  const [eventTypes] = useState([
    { key: 'task.assigned', name: 'Task được giao', description: 'Khi có task mới được giao cho bạn' },
    { key: 'task.completed', name: 'Task hoàn thành', description: 'Khi task được đánh dấu hoàn thành' },
    { key: 'task.deadline_approaching', name: 'Deadline sắp đến', description: 'Khi deadline task sắp đến (1-3 ngày)' },
    { key: 'project.status_changed', name: 'Trạng thái dự án thay đổi', description: 'Khi trạng thái dự án được cập nhật' },
    { key: 'change_request.created', name: 'Change Request mới', description: 'Khi có change request mới được tạo' },
    { key: 'change_request.pending_approval', name: 'CR cần phê duyệt', description: 'Khi có change request cần phê duyệt' },
    { key: 'document.uploaded', name: 'Tài liệu mới', description: 'Khi có tài liệu mới được tải lên' },
    { key: 'comment.mentioned', name: 'Được mention', description: 'Khi bạn được mention trong comment' }
  ]);

  // Load notification settings
  useEffect(() => {
    fetchNotificationSettings();
  }, []);

  const fetchNotificationSettings = async () => {
    try {
      setIsLoading(true);
      // Simulate API call
      await new Promise(resolve => setTimeout(resolve, 1000));
      // Settings would be loaded from API
    } catch (error) {
      addNotification({
        type: 'error',
        title: 'Lỗi',
        message: 'Không thể tải cài đặt thông báo'
      });
    } finally {
      setIsLoading(false);
    }
  };

  // Handle general settings update
  const handleGeneralSettingsUpdate = async () => {
    try {
      setIsLoading(true);
      // Simulate API call
      await new Promise(resolve => setTimeout(resolve, 1000));
      
      addNotification({
        type: 'success',
        title: 'Thành công',
        message: 'Cập nhật cài đặt thông báo thành công'
      });
    } catch (error) {
      addNotification({
        type: 'error',
        title: 'Lỗi',
        message: 'Không thể cập nhật cài đặt thông báo'
      });
    } finally {
      setIsLoading(false);
    }
  };

  // Handle rule save
  const handleRuleSave = async (ruleData: Partial<NotificationRule>) => {
    try {
      setIsLoading(true);
      
      if (editingRule) {
        // Update existing rule
        setNotificationRules(prev => prev.map(rule => 
          rule.id === editingRule.id ? { ...rule, ...ruleData } : rule
        ));
      } else {
        // Create new rule
        const newRule: NotificationRule = {
          id: Date.now().toString(),
          name: ruleData.name || '',
          eventKey: ruleData.eventKey || '',
          projectId: ruleData.projectId,
          projectName: ruleData.projectName,
          minPriority: ruleData.minPriority || 'normal',
          channels: ruleData.channels || ['inapp'],
          isEnabled: true,
          createdAt: new Date().toISOString()
        };
        setNotificationRules(prev => [...prev, newRule]);
      }
      
      setIsRuleModalOpen(false);
      setEditingRule(null);
      addNotification({
        type: 'success',
        title: 'Thành công',
        message: editingRule ? 'Cập nhật quy tắc thành công' : 'Tạo quy tắc thành công'
      });
    } catch (error) {
      addNotification({
        type: 'error',
        title: 'Lỗi',
        message: 'Không thể lưu quy tắc thông báo'
      });
    } finally {
      setIsLoading(false);
    }
  };

  // Handle rule delete
  const handleRuleDelete = async (ruleId: string) => {
    try {
      setNotificationRules(prev => prev.filter(rule => rule.id !== ruleId));
      addNotification({
        type: 'success',
        title: 'Thành công',
        message: 'Xóa quy tắc thành công'
      });
    } catch (error) {
      addNotification({
        type: 'error',
        title: 'Lỗi',
        message: 'Không thể xóa quy tắc'
      });
    }
  };

  // Handle rule toggle
  const handleRuleToggle = async (ruleId: string, enabled: boolean) => {
    try {
      setNotificationRules(prev => prev.map(rule => 
        rule.id === ruleId ? { ...rule, isEnabled: enabled } : rule
      ));
    } catch (error) {
      addNotification({
        type: 'error',
        title: 'Lỗi',
        message: 'Không thể cập nhật trạng thái quy tắc'
      });
    }
  };

  // Handle channel settings update
  const handleChannelSettingsUpdate = async () => {
    try {
      setIsLoading(true);
      // Simulate API call
      await new Promise(resolve => setTimeout(resolve, 1000));
      
      addNotification({
        type: 'success',
        title: 'Thành công',
        message: 'Cập nhật cài đặt kênh thành công'
      });
    } catch (error) {
      addNotification({
        type: 'error',
        title: 'Lỗi',
        message: 'Không thể cập nhật cài đặt kênh'
      });
    } finally {
      setIsLoading(false);
    }
  };

  // Test notification
  const handleTestNotification = async (channel: string) => {
    try {
      // Simulate API call
      await new Promise(resolve => setTimeout(resolve, 500));
      
      addNotification({
        type: 'success',
        title: 'Thành công',
        message: `Đã gửi thông báo test qua ${channel}`
      });
    } catch (error) {
      addNotification({
        type: 'error',
        title: 'Lỗi',
        message: 'Không thể gửi thông báo test'
      });
    }
  };

  // Get priority badge color
  const getPriorityBadgeColor = (priority: string) => {
    switch (priority) {
      case 'critical': return 'bg-red-100 text-red-800';
      case 'normal': return 'bg-yellow-100 text-yellow-800';
      case 'low': return 'bg-gray-100 text-gray-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  // Get channel icon
  const getChannelIcon = (channel: string) => {
    switch (channel) {
      case 'inapp': return BellIcon;
      case 'email': return EnvelopeIcon;
      case 'webhook': return ComputerDesktopIcon;
      default: return BellIcon;
    }
  };

  // Tab navigation
  const tabs = [
    { id: 'general' as const, label: 'Cài đặt chung', icon: CogIcon },
    { id: 'rules' as const, label: 'Quy tắc thông báo', icon: BellIcon },
    { id: 'channels' as const, label: 'Kênh thông báo', icon: EnvelopeIcon },
    { id: 'templates' as const, label: 'Mẫu thông báo', icon: InformationCircleIcon }
  ];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Cài đặt thông báo</h1>
          <p className="text-gray-600">Quản lý cách bạn nhận thông báo từ hệ thống</p>
        </div>
      </div>

      {/* Tab Navigation */}
      <div className="border-b border-gray-200">
        <nav className="-mb-px flex space-x-8">
          {tabs.map((tab) => {
            const Icon = tab.icon;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`flex items-center space-x-2 py-2 px-1 border-b-2 font-medium text-sm ${
                  activeTab === tab.id
                    ? 'border-blue-500 text-blue-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                }`}
              >
                <Icon className="h-5 w-5" />
                <span>{tab.label}</span>
              </button>
            );
          })}
        </nav>
      </div>

      {/* Tab Content */}
      <div className="mt-6">
        {/* General Settings Tab */}
        {activeTab === 'general' && (
          <div className="space-y-6">
            {/* Global Enable/Disable */}
            <Card className="p-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4">Cài đặt chung</h3>
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div>
                    <h4 className="font-medium text-gray-900">Thông báo trong ứng dụng</h4>
                    <p className="text-sm text-gray-600">Hiển thị thông báo trong giao diện ứng dụng</p>
                  </div>
                  <label className="relative inline-flex items-center cursor-pointer">
                    <input
                      type="checkbox"
                      checked={generalSettings.inAppEnabled}
                      onChange={(e) => setGeneralSettings(prev => ({ ...prev, inAppEnabled: e.target.checked }))}
                      className="sr-only peer"
                    />
                    <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                  </label>
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <h4 className="font-medium text-gray-900">Thông báo email</h4>
                    <p className="text-sm text-gray-600">Gửi thông báo qua email</p>
                  </div>
                  <label className="relative inline-flex items-center cursor-pointer">
                    <input
                      type="checkbox"
                      checked={generalSettings.emailEnabled}
                      onChange={(e) => setGeneralSettings(prev => ({ ...prev, emailEnabled: e.target.checked }))}
                      className="sr-only peer"
                    />
                    <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                  </label>
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <h4 className="font-medium text-gray-900">Thông báo push</h4>
                    <p className="text-sm text-gray-600">Gửi thông báo push đến thiết bị di động</p>
                  </div>
                  <label className="relative inline-flex items-center cursor-pointer">
                    <input
                      type="checkbox"
                      checked={generalSettings.pushEnabled}
                      onChange={(e) => setGeneralSettings(prev => ({ ...prev, pushEnabled: e.target.checked }))}
                      className="sr-only peer"
                    />
                    <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                  </label>
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <h4 className="font-medium text-gray-900">Webhook</h4>
                    <p className="text-sm text-gray-600">Gửi thông báo qua webhook</p>
                  </div>
                  <label className="relative inline-flex items-center cursor-pointer">
                    <input
                      type="checkbox"
                      checked={generalSettings.webhookEnabled}
                      onChange={(e) => setGeneralSettings(prev => ({ ...prev, webhookEnabled: e.target.checked }))}
                      className="sr-only peer"
                    />
                    <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                  </label>
                </div>
              </div>
            </Card>

            {/* Quiet Hours */}
            <Card className="p-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4">Giờ im lặng</h3>
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div>
                    <h4 className="font-medium text-gray-900">Bật giờ im lặng</h4>
                    <p className="text-sm text-gray-600">Không gửi thông báo trong khoảng thời gian này</p>
                  </div>
                  <label className="relative inline-flex items-center cursor-pointer">
                    <input
                      type="checkbox"
                      checked={generalSettings.quietHours.enabled}
                      onChange={(e) => setGeneralSettings(prev => ({
                        ...prev,
                        quietHours: { ...prev.quietHours, enabled: e.target.checked }
                      }))}
                      className="sr-only peer"
                    />
                    <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                  </label>
                </div>

                {generalSettings.quietHours.enabled && (
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Từ
                      </label>
                      <input
                        type="time"
                        value={generalSettings.quietHours.startTime}
                        onChange={(e) => setGeneralSettings(prev => ({
                          ...prev,
                          quietHours: { ...prev.quietHours, startTime: e.target.value }
                        }))}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Đến
                      </label>
                      <input
                        type="time"
                        value={generalSettings.quietHours.endTime}
                        onChange={(e) => setGeneralSettings(prev => ({
                          ...prev,
                          quietHours: { ...prev.quietHours, endTime: e.target.value }
                        }))}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      />
                    </div>
                  </div>
                )}
              </div>
            </Card>

            {/* Frequency Settings */}
            <Card className="p-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4">Tần suất thông báo</h3>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Tần suất gửi email
                </label>
                <select
                  value={generalSettings.frequency}
                  onChange={(e) => setGeneralSettings(prev => ({ ...prev, frequency: e.target.value as any }))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="immediate">Ngay lập tức</option>
                  <option value="hourly">Mỗi giờ</option>
                  <option value="daily">Hàng ngày</option>
                  <option value="weekly">Hàng tuần</option>
                </select>
              </div>
            </Card>

            <div className="flex justify-end">
              <Button
                onClick={handleGeneralSettingsUpdate}
                loading={isLoading}
              >
                Lưu cài đặt
              </Button>
            </div>
          </div>
        )}

        {/* Notification Rules Tab */}
        {activeTab === 'rules' && (
          <div className="space-y-6">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-lg font-medium text-gray-900">Quy tắc thông báo</h3>
                <p className="text-gray-600">Tùy chỉnh khi nào bạn muốn nhận thông báo</p>
              </div>
              <Button
                onClick={() => {
                  setEditingRule(null);
                  setIsRuleModalOpen(true);
                }}
                className="flex items-center space-x-2"
              >
                <PlusIcon className="h-4 w-4" />
                <span>Thêm quy tắc</span>
              </Button>
            </div>

            <div className="space-y-4">
              {notificationRules.map((rule) => (
                <Card key={rule.id} className="p-4">
                  <div className="flex items-center justify-between">
                    <div className="flex-1">
                      <div className="flex items-center space-x-3">
                        <h4 className="font-medium text-gray-900">{rule.name}</h4>
                        <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                          getPriorityBadgeColor(rule.minPriority)
                        }`}>
                          {rule.minPriority === 'critical' ? 'Quan trọng' : 
                           rule.minPriority === 'normal' ? 'Bình thường' : 'Thấp'}
                        </span>
                        {rule.projectName && (
                          <span className="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                            {rule.projectName}
                          </span>
                        )}
                      </div>
                      <p className="text-sm text-gray-600 mt-1">
                        {eventTypes.find(e => e.key === rule.eventKey)?.description || rule.eventKey}
                      </p>
                      <div className="flex items-center space-x-2 mt-2">
                        <span className="text-xs text-gray-500">Kênh:</span>
                        {rule.channels.map((channel) => {
                          const Icon = getChannelIcon(channel);
                          return (
                            <Icon key={channel} className="h-4 w-4 text-gray-400" />
                          );
                        })}
                      </div>
                    </div>
                    <div className="flex items-center space-x-3">
                      <label className="relative inline-flex items-center cursor-pointer">
                        <input
                          type="checkbox"
                          checked={rule.isEnabled}
                          onChange={(e) => handleRuleToggle(rule.id, e.target.checked)}
                          className="sr-only peer"
                        />
                        <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                      </label>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                          setEditingRule(rule);
                          setIsRuleModalOpen(true);
                        }}
                      >
                        Sửa
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleRuleDelete(rule.id)}
                        className="text-red-600 hover:text-red-700"
                      >
                        <TrashIcon className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                </Card>
              ))}
            </div>
          </div>
        )}

        {/* Channels Tab */}
        {activeTab === 'channels' && (
          <div className="space-y-6">
            {/* Email Settings */}
            <Card className="p-6">
              <div className="flex items-center justify-between mb-4">
                <div className="flex items-center space-x-3">
                  <EnvelopeIcon className="h-6 w-6 text-blue-500" />
                  <h3 className="text-lg font-medium text-gray-900">Email</h3>
                </div>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => handleTestNotification('email')}
                >
                  Test
                </Button>
              </div>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Địa chỉ email
                  </label>
                  <div className="flex items-center space-x-2">
                    <Input
                      type="email"
                      value={channelSettings.email.address}
                      onChange={(e) => setChannelSettings(prev => ({
                        ...prev,
                        email: { ...prev.email, address: e.target.value }
                      }))}
                      className="flex-1"
                    />
                    {channelSettings.email.verified ? (
                      <CheckCircleIcon className="h-5 w-5 text-green-500" />
                    ) : (
                      <ExclamationTriangleIcon className="h-5 w-5 text-yellow-500" />
                    )}
                  </div>
                  <p className="text-xs text-gray-500 mt-1">
                    {channelSettings.email.verified ? 'Email đã được xác thực' : 'Email chưa được xác thực'}
                  </p>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Tần suất gửi
                  </label>
                  <select
                    value={channelSettings.email.frequency}
                    onChange={(e) => setChannelSettings(prev => ({
                      ...prev,
                      email: { ...prev.email, frequency: e.target.value as any }
                    }))}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="immediate">Ngay lập tức</option>
                    <option value="digest">Tổng hợp hàng ngày</option>
                  </select>
                </div>
              </div>
            </Card>

            {/* Webhook Settings */}
            <Card className="p-6">
              <div className="flex items-center justify-between mb-4">
                <div className="flex items-center space-x-3">
                  <ComputerDesktopIcon className="h-6 w-6 text-purple-500" />
                  <h3 className="text-lg font-medium text-gray-900">Webhook</h3>
                </div>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => handleTestNotification('webhook')}
                  disabled={!channelSettings.webhook.enabled}
                >
                  Test
                </Button>
              </div>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    URL Webhook
                  </label>
                  <Input
                    type="url"
                    value={channelSettings.webhook.url}
                    onChange={(e) => setChannelSettings(prev => ({
                      ...prev,
                      webhook: { ...prev.webhook, url: e.target.value }
                    }))}
                    placeholder="https://your-webhook-url.com/notifications"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Secret Key
                  </label>
                  <Input
                    type="password"
                    value={channelSettings.webhook.secret}
                    onChange={(e) => setChannelSettings(prev => ({
                      ...prev,
                      webhook: { ...prev.webhook, secret: e.target.value }
                    }))}
                    placeholder="Nhập secret key để xác thực"
                  />
                </div>
                <div className="flex items-center justify-between">
                  <div>
                    <h4 className="font-medium text-gray-900">Bật webhook</h4>
                    <p className="text-sm text-gray-600">Gửi thông báo đến URL webhook</p>
                  </div>
                  <label className="relative inline-flex items-center cursor-pointer">
                    <input
                      type="checkbox"
                      checked={channelSettings.webhook.enabled}
                      onChange={(e) => setChannelSettings(prev => ({
                        ...prev,
                        webhook: { ...prev.webhook, enabled: e.target.checked }
                      }))}
                      className="sr-only peer"
                    />
                    <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                  </label>
                </div>
              </div>
            </Card>

            {/* Push Notifications */}
            <Card className="p-6">
              <div className="flex items-center justify-between mb-4">
                <div className="flex items-center space-x-3">
                  <DevicePhoneMobileIcon className="h-6 w-6 text-green-500" />
                  <h3 className="text-lg font-medium text-gray-900">Push Notifications</h3>
                </div>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => handleTestNotification('push')}
                  disabled={!channelSettings.push.enabled}
                >
                  Test
                </Button>
              </div>
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div>
                    <h4 className="font-medium text-gray-900">Bật push notifications</h4>
                    <p className="text-sm text-gray-600">Gửi thông báo đến thiết bị di động</p>
                  </div>
                  <label className="relative inline-flex items-center cursor-pointer">
                    <input
                      type="checkbox"
                      checked={channelSettings.push.enabled}
                      onChange={(e) => setChannelSettings(prev => ({
                        ...prev,
                        push: { ...prev.push, enabled: e.target.checked }
                      }))}
                      className="sr-only peer"
                    />
                    <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                  </label>
                </div>
                {channelSettings.push.enabled && (
                  <div className="bg-blue-50 p-4 rounded-lg">
                    <p className="text-sm text-blue-800">
                      Để nhận push notifications, vui lòng cài đặt ứng dụng di động và đăng nhập.
                    </p>
                  </div>
                )}
              </div>
            </Card>

            <div className="flex justify-end">
              <Button
                onClick={handleChannelSettingsUpdate}
                loading={isLoading}
              >
                Lưu cài đặt
              </Button>
            </div>
          </div>
        )}

        {/* Templates Tab */}
        {activeTab === 'templates' && (
          <div className="space-y-6">
            <div>
              <h3 className="text-lg font-medium text-gray-900">Mẫu thông báo</h3>
              <p className="text-gray-600">Tùy chỉnh nội dung thông báo cho các sự kiện khác nhau</p>
            </div>
            
            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
              <div className="flex items-center space-x-2">
                <InformationCircleIcon className="h-5 w-5 text-yellow-600" />
                <p className="text-sm text-yellow-800">
                  Tính năng tùy chỉnh mẫu thông báo sẽ được phát triển trong phiên bản tiếp theo.
                </p>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Rule Modal */}
      <RuleModal
        isOpen={isRuleModalOpen}
        onClose={() => {
          setIsRuleModalOpen(false);
          setEditingRule(null);
        }}
        onSave={handleRuleSave}
        rule={editingRule}
        projects={projects}
        eventTypes={eventTypes}
        isLoading={isLoading}
      />
    </div>
  );
};

// Rule Modal Component
interface RuleModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: (rule: Partial<NotificationRule>) => void;
  rule: NotificationRule | null;
  projects: { id: string; name: string }[];
  eventTypes: { key: string; name: string; description: string }[];
  isLoading: boolean;
}

const RuleModal: React.FC<RuleModalProps> = ({
  isOpen,
  onClose,
  onSave,
  rule,
  projects,
  eventTypes,
  isLoading
}) => {
  const [formData, setFormData] = useState<Partial<NotificationRule>>({
    name: '',
    eventKey: '',
    projectId: '',
    minPriority: 'normal',
    channels: ['inapp']
  });
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (rule) {
      setFormData({
        name: rule.name,
        eventKey: rule.eventKey,
        projectId: rule.projectId || '',
        minPriority: rule.minPriority,
        channels: rule.channels
      });
    } else {
      setFormData({
        name: '',
        eventKey: '',
        projectId: '',
        minPriority: 'normal',
        channels: ['inapp']
      });
    }
    setErrors({});
  }, [rule, isOpen]);

  const validateForm = (): boolean => {
    const newErrors: Record<string, string> = {};

    if (!formData.name?.trim()) {
      newErrors.name = 'Tên quy tắc không được để trống';
    }

    if (!formData.eventKey) {
      newErrors.eventKey = 'Vui lòng chọn loại sự kiện';
    }

    if (!formData.channels || formData.channels.length === 0) {
      newErrors.channels = 'Vui lòng chọn ít nhất một kênh thông báo';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = () => {
    if (!validateForm()) return;

    const projectName = formData.projectId 
      ? projects.find(p => p.id === formData.projectId)?.name 
      : undefined;

    onSave({
      ...formData,
      projectName
    });
  };

  const handleChannelToggle = (channel: 'inapp' | 'email' | 'webhook') => {
    const currentChannels = formData.channels || [];
    const newChannels = currentChannels.includes(channel)
      ? currentChannels.filter(c => c !== channel)
      : [...currentChannels, channel];
    
    setFormData(prev => ({ ...prev, channels: newChannels }));
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={rule ? 'Sửa quy tắc thông báo' : 'Thêm quy tắc thông báo'}
      size="lg"
    >
      <div className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Tên quy tắc *
          </label>
          <Input
            value={formData.name || ''}
            onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
            error={errors.name}
            placeholder="Nhập tên quy tắc"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Loại sự kiện *
          </label>
          <select
            value={formData.eventKey || ''}
            onChange={(e) => setFormData(prev => ({ ...prev, eventKey: e.target.value }))}
            className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
              errors.eventKey ? 'border-red-300' : 'border-gray-300'
            }`}
          >
            <option value="">Chọn loại sự kiện</option>
            {eventTypes.map((event) => (
              <option key={event.key} value={event.key}>
                {event.name}
              </option>
            ))}
          </select>
          {errors.eventKey && (
            <p className="text-red-500 text-xs mt-1">{errors.eventKey}</p>
          )}
          {formData.eventKey && (
            <p className="text-gray-500 text-xs mt-1">
              {eventTypes.find(e => e.key === formData.eventKey)?.description}
            </p>
          )}
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Dự án (tùy chọn)
          </label>
          <select
            value={formData.projectId || ''}
            onChange={(e) => setFormData(prev => ({ ...prev, projectId: e.target.value }))}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Tất cả dự án</option>
            {projects.map((project) => (
              <option key={project.id} value={project.id}>
                {project.name}
              </option>
            ))}
          </select>
          <p className="text-gray-500 text-xs mt-1">
            Để trống nếu muốn áp dụng cho tất cả dự án
          </p>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Mức độ ưu tiên tối thiểu
          </label>
          <select
            value={formData.minPriority || 'normal'}
            onChange={(e) => setFormData(prev => ({ ...prev, minPriority: e.target.value as any }))}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="low">Thấp</option>
            <option value="normal">Bình thường</option>
            <option value="critical">Quan trọng</option>
          </select>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Kênh thông báo *
          </label>
          <div className="space-y-2">
            {[
              { key: 'inapp' as const, label: 'Trong ứng dụng', icon: BellIcon },
              { key: 'email' as const, label: 'Email', icon: EnvelopeIcon },
              { key: 'webhook' as const, label: 'Webhook', icon: ComputerDesktopIcon }
            ].map((channel) => {
              const Icon = channel.icon;
              return (
                <label key={channel.key} className="flex items-center space-x-3 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.channels?.includes(channel.key) || false}
                    onChange={() => handleChannelToggle(channel.key)}
                    className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                  <Icon className="h-5 w-5 text-gray-400" />
                  <span className="text-sm text-gray-700">{channel.label}</span>
                </label>
              );
            })}
          </div>
          {errors.channels && (
            <p className="text-red-500 text-xs mt-1">{errors.channels}</p>
          )}
        </div>
      </div>

      <div className="flex justify-end space-x-3 mt-6">
        <Button variant="outline" onClick={onClose}>
          Hủy
        </Button>
        <Button onClick={handleSubmit} loading={isLoading}>
          {rule ? 'Cập nhật' : 'Tạo quy tắc'}
        </Button>
      </div>
    </Modal>
  );
};