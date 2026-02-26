import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../../components/ui/Card';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import { Label } from '../../components/ui/label';
import { Switch } from '../../components/ui/Switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../../components/ui/Tabs';
import { Badge } from '../../components/ui/Badge';
import { Alert, AlertDescription } from '../../components/ui/alert';
import { Modal } from '../../components/ui/Modal';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../../components/ui/Select';
import { Textarea } from '../../components/ui/Textarea';
import { 
  Settings, 
  Shield, 
  Database, 
  Server, 
  Users, 
  Clock, 
  Mail, 
  FileText, 
  Download,
  Upload,
  AlertTriangle,
  CheckCircle,
  XCircle,
  RefreshCw,
  Save,
  Eye,
  EyeOff
} from 'lucide-react';

// Types cho system settings
interface SystemConfig {
  siteName: string;
  siteUrl: string;
  adminEmail: string;
  timezone: string;
  language: string;
  maintenanceMode: boolean;
  registrationEnabled: boolean;
  emailVerificationRequired: boolean;
  maxFileUploadSize: number;
  sessionTimeout: number;
}

interface SecuritySettings {
  passwordMinLength: number;
  passwordRequireUppercase: boolean;
  passwordRequireNumbers: boolean;
  passwordRequireSymbols: boolean;
  maxLoginAttempts: number;
  lockoutDuration: number;
  twoFactorEnabled: boolean;
  ipWhitelist: string[];
  sessionSecure: boolean;
}

interface BackupSettings {
  autoBackupEnabled: boolean;
  backupFrequency: 'daily' | 'weekly' | 'monthly';
  backupTime: string;
  retentionDays: number;
  includeFiles: boolean;
  includeDatabase: boolean;
  backupLocation: 'local' | 's3' | 'gdrive';
  lastBackup?: string;
  nextBackup?: string;
}

interface EmailSettings {
  driver: 'smtp' | 'sendmail' | 'mailgun' | 'ses';
  host: string;
  port: number;
  username: string;
  password: string;
  encryption: 'tls' | 'ssl' | 'none';
  fromAddress: string;
  fromName: string;
  testEmailSent: boolean;
}

interface SystemStatus {
  phpVersion: string;
  laravelVersion: string;
  databaseVersion: string;
  diskSpace: {
    total: string;
    used: string;
    free: string;
    percentage: number;
  };
  memoryUsage: {
    used: string;
    total: string;
    percentage: number;
  };
  uptime: string;
  lastUpdate: string;
}

const SystemSettingsPage: React.FC = () => {
  // State management
  const [activeTab, setActiveTab] = useState('general');
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [showPasswordModal, setShowPasswordModal] = useState(false);
  const [showBackupModal, setShowBackupModal] = useState(false);
  const [showTestEmailModal, setShowTestEmailModal] = useState(false);
  const [passwordVisible, setPasswordVisible] = useState(false);
  
  // Settings state
  const [systemConfig, setSystemConfig] = useState<SystemConfig>({
    siteName: 'Z.E.N.A Project Management',
    siteUrl: 'https://zena.local',
    adminEmail: 'admin@zena.local',
    timezone: 'Asia/Ho_Chi_Minh',
    language: 'vi',
    maintenanceMode: false,
    registrationEnabled: true,
    emailVerificationRequired: true,
    maxFileUploadSize: 10,
    sessionTimeout: 120
  });

  const [securitySettings, setSecuritySettings] = useState<SecuritySettings>({
    passwordMinLength: 8,
    passwordRequireUppercase: true,
    passwordRequireNumbers: true,
    passwordRequireSymbols: false,
    maxLoginAttempts: 5,
    lockoutDuration: 15,
    twoFactorEnabled: false,
    ipWhitelist: [],
    sessionSecure: true
  });

  const [backupSettings, setBackupSettings] = useState<BackupSettings>({
    autoBackupEnabled: true,
    backupFrequency: 'daily',
    backupTime: '02:00',
    retentionDays: 30,
    includeFiles: true,
    includeDatabase: true,
    backupLocation: 'local',
    lastBackup: '2024-01-15 02:00:00',
    nextBackup: '2024-01-16 02:00:00'
  });

  const [emailSettings, setEmailSettings] = useState<EmailSettings>({
    driver: 'smtp',
    host: 'smtp.gmail.com',
    port: 587,
    username: '',
    password: '',
    encryption: 'tls',
    fromAddress: 'noreply@zena.local',
    fromName: 'Z.E.N.A System',
    testEmailSent: false
  });

  const [systemStatus, setSystemStatus] = useState<SystemStatus>({
    phpVersion: '8.0.30',
    laravelVersion: '10.48.4',
    databaseVersion: 'MySQL 8.0.35',
    diskSpace: {
      total: '100 GB',
      used: '45 GB',
      free: '55 GB',
      percentage: 45
    },
    memoryUsage: {
      used: '2.1 GB',
      total: '8 GB',
      percentage: 26
    },
    uptime: '15 days, 8 hours',
    lastUpdate: '2024-01-15 14:30:00'
  });

  const [newIpAddress, setNewIpAddress] = useState('');
  const [testEmail, setTestEmail] = useState('');
  const [backupNow, setBackupNow] = useState(false);

  // Load system settings
  useEffect(() => {
    loadSystemSettings();
  }, []);

  const loadSystemSettings = async () => {
    setLoading(true);
    try {
      // Simulate API call
      await new Promise(resolve => setTimeout(resolve, 1000));
      // Load settings from API
    } catch (error) {
      console.error('Error loading system settings:', error);
    } finally {
      setLoading(false);
    }
  };

  // Save settings functions
  const saveGeneralSettings = async () => {
    setSaving(true);
    try {
      // Simulate API call
      await new Promise(resolve => setTimeout(resolve, 1000));
      console.log('Saving general settings:', systemConfig);
    } catch (error) {
      console.error('Error saving general settings:', error);
    } finally {
      setSaving(false);
    }
  };

  const saveSecuritySettings = async () => {
    setSaving(true);
    try {
      await new Promise(resolve => setTimeout(resolve, 1000));
      console.log('Saving security settings:', securitySettings);
    } catch (error) {
      console.error('Error saving security settings:', error);
    } finally {
      setSaving(false);
    }
  };

  const saveBackupSettings = async () => {
    setSaving(true);
    try {
      await new Promise(resolve => setTimeout(resolve, 1000));
      console.log('Saving backup settings:', backupSettings);
    } catch (error) {
      console.error('Error saving backup settings:', error);
    } finally {
      setSaving(false);
    }
  };

  const saveEmailSettings = async () => {
    setSaving(true);
    try {
      await new Promise(resolve => setTimeout(resolve, 1000));
      console.log('Saving email settings:', emailSettings);
    } catch (error) {
      console.error('Error saving email settings:', error);
    } finally {
      setSaving(false);
    }
  };

  // Utility functions
  const addIpToWhitelist = () => {
    if (newIpAddress && !securitySettings.ipWhitelist.includes(newIpAddress)) {
      setSecuritySettings(prev => ({
        ...prev,
        ipWhitelist: [...prev.ipWhitelist, newIpAddress]
      }));
      setNewIpAddress('');
    }
  };

  const removeIpFromWhitelist = (ip: string) => {
    setSecuritySettings(prev => ({
      ...prev,
      ipWhitelist: prev.ipWhitelist.filter(item => item !== ip)
    }));
  };

  const sendTestEmail = async () => {
    try {
      await new Promise(resolve => setTimeout(resolve, 2000));
      setEmailSettings(prev => ({ ...prev, testEmailSent: true }));
      setShowTestEmailModal(false);
      setTestEmail('');
    } catch (error) {
      console.error('Error sending test email:', error);
    }
  };

  const runBackupNow = async () => {
    setBackupNow(true);
    try {
      await new Promise(resolve => setTimeout(resolve, 3000));
      setBackupSettings(prev => ({
        ...prev,
        lastBackup: new Date().toISOString().slice(0, 19).replace('T', ' ')
      }));
      setShowBackupModal(false);
    } catch (error) {
      console.error('Error running backup:', error);
    } finally {
      setBackupNow(false);
    }
  };

  const refreshSystemStatus = async () => {
    setLoading(true);
    try {
      await new Promise(resolve => setTimeout(resolve, 1500));
      setSystemStatus(prev => ({
        ...prev,
        lastUpdate: new Date().toISOString().slice(0, 19).replace('T', ' ')
      }));
    } catch (error) {
      console.error('Error refreshing system status:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <RefreshCw className="h-8 w-8 animate-spin" />
        <span className="ml-2">Đang tải cài đặt hệ thống...</span>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Cài đặt hệ thống</h1>
          <p className="text-muted-foreground">
            Quản lý cấu hình và cài đặt hệ thống Z.E.N.A
          </p>
        </div>
        <Button onClick={refreshSystemStatus} variant="outline">
          <RefreshCw className="h-4 w-4 mr-2" />
          Làm mới
        </Button>
      </div>

      {/* System Status Alert */}
      {systemStatus.diskSpace.percentage > 80 && (
        <Alert>
          <AlertTriangle className="h-4 w-4" />
          <AlertDescription>
            Cảnh báo: Dung lượng ổ đĩa đã sử dụng {systemStatus.diskSpace.percentage}%. 
            Vui lòng kiểm tra và dọn dẹp dữ liệu.
          </AlertDescription>
        </Alert>
      )}

      {/* Main Content */}
      <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-4">
        <TabsList className="grid w-full grid-cols-5">
          <TabsTrigger value="general" className="flex items-center gap-2">
            <Settings className="h-4 w-4" />
            Chung
          </TabsTrigger>
          <TabsTrigger value="security" className="flex items-center gap-2">
            <Shield className="h-4 w-4" />
            Bảo mật
          </TabsTrigger>
          <TabsTrigger value="backup" className="flex items-center gap-2">
            <Database className="h-4 w-4" />
            Sao lưu
          </TabsTrigger>
          <TabsTrigger value="email" className="flex items-center gap-2">
            <Mail className="h-4 w-4" />
            Email
          </TabsTrigger>
          <TabsTrigger value="status" className="flex items-center gap-2">
            <Server className="h-4 w-4" />
            Trạng thái
          </TabsTrigger>
        </TabsList>

        {/* General Settings Tab */}
        <TabsContent value="general" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Cài đặt chung</CardTitle>
              <CardDescription>
                Cấu hình các thông tin cơ bản của hệ thống
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="siteName">Tên website</Label>
                  <Input
                    id="siteName"
                    value={systemConfig.siteName}
                    onChange={(e) => setSystemConfig(prev => ({ ...prev, siteName: e.target.value }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="siteUrl">URL website</Label>
                  <Input
                    id="siteUrl"
                    value={systemConfig.siteUrl}
                    onChange={(e) => setSystemConfig(prev => ({ ...prev, siteUrl: e.target.value }))}
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="adminEmail">Email quản trị</Label>
                  <Input
                    id="adminEmail"
                    type="email"
                    value={systemConfig.adminEmail}
                    onChange={(e) => setSystemConfig(prev => ({ ...prev, adminEmail: e.target.value }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="timezone">Múi giờ</Label>
                  <Select
                    value={systemConfig.timezone}
                    onValueChange={(value) => setSystemConfig(prev => ({ ...prev, timezone: value }))}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="Asia/Ho_Chi_Minh">Việt Nam (UTC+7)</SelectItem>
                      <SelectItem value="UTC">UTC (UTC+0)</SelectItem>
                      <SelectItem value="America/New_York">New York (UTC-5)</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="language">Ngôn ngữ mặc định</Label>
                  <Select
                    value={systemConfig.language}
                    onValueChange={(value) => setSystemConfig(prev => ({ ...prev, language: value }))}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="vi">Tiếng Việt</SelectItem>
                      <SelectItem value="en">English</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="maxFileSize">Kích thước file tối đa (MB)</Label>
                  <Input
                    id="maxFileSize"
                    type="number"
                    value={systemConfig.maxFileUploadSize}
                    onChange={(e) => setSystemConfig(prev => ({ ...prev, maxFileUploadSize: parseInt(e.target.value) }))}
                  />
                </div>
              </div>

              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <Label>Chế độ bảo trì</Label>
                    <p className="text-sm text-muted-foreground">
                      Khi bật, chỉ admin mới có thể truy cập hệ thống
                    </p>
                  </div>
                  <Switch
                    checked={systemConfig.maintenanceMode}
                    onCheckedChange={(checked) => setSystemConfig(prev => ({ ...prev, maintenanceMode: checked }))}
                  />
                </div>

                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <Label>Cho phép đăng ký</Label>
                    <p className="text-sm text-muted-foreground">
                      Người dùng có thể tự đăng ký tài khoản mới
                    </p>
                  </div>
                  <Switch
                    checked={systemConfig.registrationEnabled}
                    onCheckedChange={(checked) => setSystemConfig(prev => ({ ...prev, registrationEnabled: checked }))}
                  />
                </div>

                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <Label>Yêu cầu xác thực email</Label>
                    <p className="text-sm text-muted-foreground">
                      Người dùng phải xác thực email trước khi sử dụng
                    </p>
                  </div>
                  <Switch
                    checked={systemConfig.emailVerificationRequired}
                    onCheckedChange={(checked) => setSystemConfig(prev => ({ ...prev, emailVerificationRequired: checked }))}
                  />
                </div>
              </div>

              <div className="flex justify-end">
                <Button onClick={saveGeneralSettings} disabled={saving}>
                  {saving ? (
                    <RefreshCw className="h-4 w-4 mr-2 animate-spin" />
                  ) : (
                    <Save className="h-4 w-4 mr-2" />
                  )}
                  Lưu cài đặt
                </Button>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Security Settings Tab */}
        <TabsContent value="security" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Cài đặt bảo mật</CardTitle>
              <CardDescription>
                Cấu hình các chính sách bảo mật cho hệ thống
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              {/* Password Policy */}
              <div className="space-y-4">
                <h3 className="text-lg font-medium">Chính sách mật khẩu</h3>
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label htmlFor="passwordMinLength">Độ dài tối thiểu</Label>
                    <Input
                      id="passwordMinLength"
                      type="number"
                      min="6"
                      max="32"
                      value={securitySettings.passwordMinLength}
                      onChange={(e) => setSecuritySettings(prev => ({ ...prev, passwordMinLength: parseInt(e.target.value) }))}
                    />
                  </div>
                </div>
                
                <div className="space-y-4">
                  <div className="flex items-center justify-between">
                    <Label>Yêu cầu chữ hoa</Label>
                    <Switch
                      checked={securitySettings.passwordRequireUppercase}
                      onCheckedChange={(checked) => setSecuritySettings(prev => ({ ...prev, passwordRequireUppercase: checked }))}
                    />
                  </div>
                  <div className="flex items-center justify-between">
                    <Label>Yêu cầu số</Label>
                    <Switch
                      checked={securitySettings.passwordRequireNumbers}
                      onCheckedChange={(checked) => setSecuritySettings(prev => ({ ...prev, passwordRequireNumbers: checked }))}
                    />
                  </div>
                  <div className="flex items-center justify-between">
                    <Label>Yêu cầu ký tự đặc biệt</Label>
                    <Switch
                      checked={securitySettings.passwordRequireSymbols}
                      onCheckedChange={(checked) => setSecuritySettings(prev => ({ ...prev, passwordRequireSymbols: checked }))}
                    />
                  </div>
                </div>
              </div>

              {/* Login Security */}
              <div className="space-y-4">
                <h3 className="text-lg font-medium">Bảo mật đăng nhập</h3>
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label htmlFor="maxLoginAttempts">Số lần đăng nhập sai tối đa</Label>
                    <Input
                      id="maxLoginAttempts"
                      type="number"
                      min="3"
                      max="10"
                      value={securitySettings.maxLoginAttempts}
                      onChange={(e) => setSecuritySettings(prev => ({ ...prev, maxLoginAttempts: parseInt(e.target.value) }))}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="lockoutDuration">Thời gian khóa (phút)</Label>
                    <Input
                      id="lockoutDuration"
                      type="number"
                      min="5"
                      max="60"
                      value={securitySettings.lockoutDuration}
                      onChange={(e) => setSecuritySettings(prev => ({ ...prev, lockoutDuration: parseInt(e.target.value) }))}
                    />
                  </div>
                </div>

                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <Label>Xác thực 2 yếu tố (2FA)</Label>
                    <p className="text-sm text-muted-foreground">
                      Yêu cầu mã xác thực từ ứng dụng authenticator
                    </p>
                  </div>
                  <Switch
                    checked={securitySettings.twoFactorEnabled}
                    onCheckedChange={(checked) => setSecuritySettings(prev => ({ ...prev, twoFactorEnabled: checked }))}
                  />
                </div>
              </div>

              {/* IP Whitelist */}
              <div className="space-y-4">
                <h3 className="text-lg font-medium">Danh sách IP được phép</h3>
                <div className="flex gap-2">
                  <Input
                    placeholder="Nhập địa chỉ IP (vd: 192.168.1.1)"
                    value={newIpAddress}
                    onChange={(e) => setNewIpAddress(e.target.value)}
                  />
                  <Button onClick={addIpToWhitelist} variant="outline">
                    Thêm
                  </Button>
                </div>
                <div className="space-y-2">
                  {securitySettings.ipWhitelist.map((ip, index) => (
                    <div key={index} className="flex items-center justify-between p-2 border rounded">
                      <span className="font-mono">{ip}</span>
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => removeIpFromWhitelist(ip)}
                      >
                        <XCircle className="h-4 w-4" />
                      </Button>
                    </div>
                  ))}
                  {securitySettings.ipWhitelist.length === 0 && (
                    <p className="text-sm text-muted-foreground">Chưa có IP nào trong danh sách</p>
                  )}
                </div>
              </div>

              <div className="flex justify-end">
                <Button onClick={saveSecuritySettings} disabled={saving}>
                  {saving ? (
                    <RefreshCw className="h-4 w-4 mr-2 animate-spin" />
                  ) : (
                    <Save className="h-4 w-4 mr-2" />
                  )}
                  Lưu cài đặt
                </Button>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Backup Settings Tab */}
        <TabsContent value="backup" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Cài đặt sao lưu</CardTitle>
              <CardDescription>
                Cấu hình tự động sao lưu dữ liệu hệ thống
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label>Tự động sao lưu</Label>
                  <p className="text-sm text-muted-foreground">
                    Kích hoạt sao lưu tự động theo lịch
                  </p>
                </div>
                <Switch
                  checked={backupSettings.autoBackupEnabled}
                  onCheckedChange={(checked) => setBackupSettings(prev => ({ ...prev, autoBackupEnabled: checked }))}
                />
              </div>

              {backupSettings.autoBackupEnabled && (
                <div className="space-y-4">
                  <div className="grid grid-cols-3 gap-4">
                    <div className="space-y-2">
                      <Label htmlFor="backupFrequency">Tần suất sao lưu</Label>
                      <Select
                        value={backupSettings.backupFrequency}
                        onValueChange={(value: 'daily' | 'weekly' | 'monthly') => 
                          setBackupSettings(prev => ({ ...prev, backupFrequency: value }))
                        }
                      >
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="daily">Hàng ngày</SelectItem>
                          <SelectItem value="weekly">Hàng tuần</SelectItem>
                          <SelectItem value="monthly">Hàng tháng</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="backupTime">Thời gian sao lưu</Label>
                      <Input
                        id="backupTime"
                        type="time"
                        value={backupSettings.backupTime}
                        onChange={(e) => setBackupSettings(prev => ({ ...prev, backupTime: e.target.value }))}
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="retentionDays">Lưu trữ (ngày)</Label>
                      <Input
                        id="retentionDays"
                        type="number"
                        min="7"
                        max="365"
                        value={backupSettings.retentionDays}
                        onChange={(e) => setBackupSettings(prev => ({ ...prev, retentionDays: parseInt(e.target.value) }))}
                      />
                    </div>
                  </div>

                  <div className="space-y-4">
                    <div className="flex items-center justify-between">
                      <Label>Sao lưu files</Label>
                      <Switch
                        checked={backupSettings.includeFiles}
                        onCheckedChange={(checked) => setBackupSettings(prev => ({ ...prev, includeFiles: checked }))}
                      />
                    </div>
                    <div className="flex items-center justify-between">
                      <Label>Sao lưu database</Label>
                      <Switch
                        checked={backupSettings.includeDatabase}
                        onCheckedChange={(checked) => setBackupSettings(prev => ({ ...prev, includeDatabase: checked }))}
                      />
                    </div>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="backupLocation">Vị trí lưu trữ</Label>
                    <Select
                      value={backupSettings.backupLocation}
                      onValueChange={(value: 'local' | 's3' | 'gdrive') => 
                        setBackupSettings(prev => ({ ...prev, backupLocation: value }))
                      }
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="local">Máy chủ local</SelectItem>
                        <SelectItem value="s3">Amazon S3</SelectItem>
                        <SelectItem value="gdrive">Google Drive</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>
              )}

              {/* Backup Status */}
              <div className="space-y-4">
                <h3 className="text-lg font-medium">Trạng thái sao lưu</h3>
                <div className="grid grid-cols-2 gap-4">
                  <div className="p-4 border rounded-lg">
                    <div className="flex items-center gap-2 mb-2">
                      <CheckCircle className="h-5 w-5 text-green-500" />
                      <span className="font-medium">Lần sao lưu cuối</span>
                    </div>
                    <p className="text-sm text-muted-foreground">
                      {backupSettings.lastBackup || 'Chưa có'}
                    </p>
                  </div>
                  <div className="p-4 border rounded-lg">
                    <div className="flex items-center gap-2 mb-2">
                      <Clock className="h-5 w-5 text-blue-500" />
                      <span className="font-medium">Lần sao lưu tiếp theo</span>
                    </div>
                    <p className="text-sm text-muted-foreground">
                      {backupSettings.nextBackup || 'Chưa lên lịch'}
                    </p>
                  </div>
                </div>
              </div>

              <div className="flex justify-between">
                <Button 
                  onClick={() => setShowBackupModal(true)} 
                  variant="outline"
                >
                  <Download className="h-4 w-4 mr-2" />
                  Sao lưu ngay
                </Button>
                <Button onClick={saveBackupSettings} disabled={saving}>
                  {saving ? (
                    <RefreshCw className="h-4 w-4 mr-2 animate-spin" />
                  ) : (
                    <Save className="h-4 w-4 mr-2" />
                  )}
                  Lưu cài đặt
                </Button>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Email Settings Tab */}
        <TabsContent value="email" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Cài đặt Email</CardTitle>
              <CardDescription>
                Cấu hình máy chủ email để gửi thông báo
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="emailDriver">Driver email</Label>
                  <Select
                    value={emailSettings.driver}
                    onValueChange={(value: 'smtp' | 'sendmail' | 'mailgun' | 'ses') => 
                      setEmailSettings(prev => ({ ...prev, driver: value }))
                    }
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="smtp">SMTP</SelectItem>
                      <SelectItem value="sendmail">Sendmail</SelectItem>
                      <SelectItem value="mailgun">Mailgun</SelectItem>
                      <SelectItem value="ses">Amazon SES</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="emailEncryption">Mã hóa</Label>
                  <Select
                    value={emailSettings.encryption}
                    onValueChange={(value: 'tls' | 'ssl' | 'none') => 
                      setEmailSettings(prev => ({ ...prev, encryption: value }))
                    }
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="tls">TLS</SelectItem>
                      <SelectItem value="ssl">SSL</SelectItem>
                      <SelectItem value="none">Không</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              {emailSettings.driver === 'smtp' && (
                <div className="space-y-4">
                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-2">
                      <Label htmlFor="emailHost">SMTP Host</Label>
                      <Input
                        id="emailHost"
                        value={emailSettings.host}
                        onChange={(e) => setEmailSettings(prev => ({ ...prev, host: e.target.value }))}
                        placeholder="smtp.gmail.com"
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="emailPort">SMTP Port</Label>
                      <Input
                        id="emailPort"
                        type="number"
                        value={emailSettings.port}
                        onChange={(e) => setEmailSettings(prev => ({ ...prev, port: parseInt(e.target.value) }))}
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-2">
                      <Label htmlFor="emailUsername">Username</Label>
                      <Input
                        id="emailUsername"
                        value={emailSettings.username}
                        onChange={(e) => setEmailSettings(prev => ({ ...prev, username: e.target.value }))}
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="emailPassword">Password</Label>
                      <div className="relative">
                        <Input
                          id="emailPassword"
                          type={passwordVisible ? 'text' : 'password'}
                          value={emailSettings.password}
                          onChange={(e) => setEmailSettings(prev => ({ ...prev, password: e.target.value }))}
                        />
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
                          onClick={() => setPasswordVisible(!passwordVisible)}
                        >
                          {passwordVisible ? (
                            <EyeOff className="h-4 w-4" />
                          ) : (
                            <Eye className="h-4 w-4" />
                          )}
                        </Button>
                      </div>
                    </div>
                  </div>
                </div>
              )}

              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="fromAddress">Email gửi</Label>
                  <Input
                    id="fromAddress"
                    type="email"
                    value={emailSettings.fromAddress}
                    onChange={(e) => setEmailSettings(prev => ({ ...prev, fromAddress: e.target.value }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="fromName">Tên người gửi</Label>
                  <Input
                    id="fromName"
                    value={emailSettings.fromName}
                    onChange={(e) => setEmailSettings(prev => ({ ...prev, fromName: e.target.value }))}
                  />
                </div>
              </div>

              {emailSettings.testEmailSent && (
                <Alert>
                  <CheckCircle className="h-4 w-4" />
                  <AlertDescription>
                    Email test đã được gửi thành công!
                  </AlertDescription>
                </Alert>
              )}

              <div className="flex justify-between">
                <Button 
                  onClick={() => setShowTestEmailModal(true)} 
                  variant="outline"
                >
                  <Mail className="h-4 w-4 mr-2" />
                  Gửi email test
                </Button>
                <Button onClick={saveEmailSettings} disabled={saving}>
                  {saving ? (
                    <RefreshCw className="h-4 w-4 mr-2 animate-spin" />
                  ) : (
                    <Save className="h-4 w-4 mr-2" />
                  )}
                  Lưu cài đặt
                </Button>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* System Status Tab */}
        <TabsContent value="status" className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {/* System Information */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Server className="h-5 w-5" />
                  Thông tin hệ thống
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <div className="flex justify-between">
                    <span className="text-sm font-medium">PHP Version:</span>
                    <Badge variant="outline">{systemStatus.phpVersion}</Badge>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-sm font-medium">Laravel Version:</span>
                    <Badge variant="outline">{systemStatus.laravelVersion}</Badge>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-sm font-medium">Database:</span>
                    <Badge variant="outline">{systemStatus.databaseVersion}</Badge>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-sm font-medium">Uptime:</span>
                    <span className="text-sm">{systemStatus.uptime}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-sm font-medium">Cập nhật cuối:</span>
                    <span className="text-sm">{systemStatus.lastUpdate}</span>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Resource Usage */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Database className="h-5 w-5" />
                  Sử dụng tài nguyên
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                {/* Disk Usage */}
                <div className="space-y-2">
                  <div className="flex justify-between text-sm">
                    <span>Ổ đĩa</span>
                    <span>{systemStatus.diskSpace.used} / {systemStatus.diskSpace.total}</span>
                  </div>
                  <div className="w-full bg-gray-200 rounded-full h-2">
                    <div 
                      className={`h-2 rounded-full ${
                        systemStatus.diskSpace.percentage > 80 
                          ? 'bg-red-500' 
                          : systemStatus.diskSpace.percentage > 60 
                          ? 'bg-yellow-500' 
                          : 'bg-green-500'
                      }`}
                      style={{ width: `${systemStatus.diskSpace.percentage}%` }}
                    ></div>
                  </div>
                  <p className="text-xs text-muted-foreground">
                    {systemStatus.diskSpace.percentage}% đã sử dụng
                  </p>
                </div>

                {/* Memory Usage */}
                <div className="space-y-2">
                  <div className="flex justify-between text-sm">
                    <span>Bộ nhớ</span>
                    <span>{systemStatus.memoryUsage.used} / {systemStatus.memoryUsage.total}</span>
                  </div>
                  <div className="w-full bg-gray-200 rounded-full h-2">
                    <div 
                      className={`h-2 rounded-full ${
                        systemStatus.memoryUsage.percentage > 80 
                          ? 'bg-red-500' 
                          : systemStatus.memoryUsage.percentage > 60 
                          ? 'bg-yellow-500' 
                          : 'bg-blue-500'
                      }`}
                      style={{ width: `${systemStatus.memoryUsage.percentage}%` }}
                    ></div>
                  </div>
                  <p className="text-xs text-muted-foreground">
                    {systemStatus.memoryUsage.percentage}% đã sử dụng
                  </p>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* System Health Checks */}
          <Card>
            <CardHeader>
              <CardTitle>Kiểm tra sức khỏe hệ thống</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="flex items-center gap-3 p-3 border rounded-lg">
                  <CheckCircle className="h-5 w-5 text-green-500" />
                  <div>
                    <p className="font-medium">Database</p>
                    <p className="text-sm text-muted-foreground">Kết nối bình thường</p>
                  </div>
                </div>
                <div className="flex items-center gap-3 p-3 border rounded-lg">
                  <CheckCircle className="h-5 w-5 text-green-500" />
                  <div>
                    <p className="font-medium">Cache</p>
                    <p className="text-sm text-muted-foreground">Hoạt động tốt</p>
                  </div>
                </div>
                <div className="flex items-center gap-3 p-3 border rounded-lg">
                  <CheckCircle className="h-5 w-5 text-green-500" />
                  <div>
                    <p className="font-medium">Queue</p>
                    <p className="text-sm text-muted-foreground">Đang xử lý</p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {/* Modals */}
      {/* Test Email Modal */}
      <Modal 
        isOpen={showTestEmailModal} 
        onClose={() => setShowTestEmailModal(false)}
        title="Gửi email test"
      >
        <div className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="testEmail">Email nhận</Label>
            <Input
              id="testEmail"
              type="email"
              placeholder="Nhập email để test"
              value={testEmail}
              onChange={(e) => setTestEmail(e.target.value)}
            />
          </div>
          <div className="flex justify-end gap-2">
            <Button variant="outline" onClick={() => setShowTestEmailModal(false)}>
              Hủy
            </Button>
            <Button onClick={sendTestEmail} disabled={!testEmail}>
              <Mail className="h-4 w-4 mr-2" />
              Gửi test
            </Button>
          </div>
        </div>
      </Modal>

      {/* Backup Now Modal */}
      <Modal 
        isOpen={showBackupModal} 
        onClose={() => setShowBackupModal(false)}
        title="Sao lưu ngay"
      >
        <div className="space-y-4">
          <p>Bạn có chắc chắn muốn thực hiện sao lưu ngay bây giờ?</p>
          <div className="flex justify-end gap-2">
            <Button variant="outline" onClick={() => setShowBackupModal(false)}>
              Hủy
            </Button>
            <Button onClick={runBackupNow} disabled={backupNow}>
              {backupNow ? (
                <RefreshCw className="h-4 w-4 mr-2 animate-spin" />
              ) : (
                <Download className="h-4 w-4 mr-2" />
              )}
              {backupNow ? 'Đang sao lưu...' : 'Sao lưu ngay'}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
};

export default SystemSettingsPage;