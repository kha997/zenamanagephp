import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Switch } from '../../../components/ui/primitives/Switch';
import { Input } from '../../../components/ui/primitives/Input';
import { useSettings, useUpdateSecurity } from '../hooks';
import { authApi } from '../../../features/auth/api';
import toast from 'react-hot-toast';
import type { SecuritySettings } from '../types';
import { useAuthStore } from '../../auth/store';

/**
 * SecuritySettings Component
 * 
 * Form for managing security preferences: two-factor, password expiry, session timeout, login attempts
 */
export const SecuritySettings: React.FC = () => {
  const { data: settingsData, isLoading } = useSettings();
  const updateSecurity = useUpdateSecurity();
  const { hasTenantPermission } = useAuthStore();
  const canManageSettings = hasTenantPermission('tenant.manage_settings');

  const [formData, setFormData] = useState<Partial<SecuritySettings>>({
    two_factor_enabled: false,
    password_expiry_days: null,
    session_timeout_minutes: null,
    login_attempts_limit: null,
  });

  // Initialize form data from settings
  useEffect(() => {
    if (settingsData?.security_settings) {
      setFormData(settingsData.security_settings);
    }
  }, [settingsData]);

  const handleToggle = (field: keyof SecuritySettings, value: boolean) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleInputChange = (field: keyof SecuritySettings, value: string) => {
    const numValue = value === '' ? null : parseInt(value, 10);
    setFormData((prev) => ({ ...prev, [field]: numValue }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await updateSecurity.mutateAsync(formData);
      toast.success('Security settings updated successfully');
    } catch (error: any) {
      toast.error(error?.message || 'Failed to update security settings');
    }
  };

  const handleReset = () => {
    if (settingsData?.security_settings) {
      setFormData(settingsData.security_settings);
    }
  };

  const hasChanges =
    JSON.stringify(formData) !== JSON.stringify(settingsData?.security_settings);

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Security Settings</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {[1, 2, 3, 4].map((i) => (
              <div key={i} className="animate-pulse">
                <div className="h-6 bg-[var(--muted-surface)] rounded w-3/4"></div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="space-y-6">
      {/* Change Password Card */}
      <ChangePasswordCard />
      
      {/* Security Settings Card */}
      <Card>
        <CardHeader>
          <CardTitle>Security Settings</CardTitle>
          <p className="text-sm text-[var(--color-text-muted)] mt-1">
            Manage your account security preferences and authentication settings.
          </p>
        </CardHeader>
        <form onSubmit={handleSubmit}>
          <CardContent className="space-y-6">
          {/* Two-Factor Authentication */}
          <div>
            <Switch
              label="Two-Factor Authentication"
              description="Add an extra layer of security to your account"
              checked={formData.two_factor_enabled ?? false}
              onChange={(e) => handleToggle('two_factor_enabled', e.target.checked)}
            />
          </div>

          {/* Password Expiry */}
          <div>
            <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
              Password Expiry (days)
            </label>
            <Input
              type="number"
              min="30"
              value={formData.password_expiry_days?.toString() || ''}
              onChange={(e) => handleInputChange('password_expiry_days', e.target.value)}
              placeholder="e.g., 90"
            />
            <p className="text-xs text-[var(--color-text-muted)] mt-1">
              Number of days before password expires (minimum 30 days)
            </p>
          </div>

          {/* Session Timeout */}
          <div>
            <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
              Session Timeout (minutes)
            </label>
            <Input
              type="number"
              min="15"
              value={formData.session_timeout_minutes?.toString() || ''}
              onChange={(e) => handleInputChange('session_timeout_minutes', e.target.value)}
              placeholder="e.g., 60"
            />
            <p className="text-xs text-[var(--color-text-muted)] mt-1">
              Minutes of inactivity before automatic logout (minimum 15 minutes)
            </p>
          </div>

          {/* Login Attempts Limit */}
          <div>
            <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
              Login Attempts Limit
            </label>
            <Input
              type="number"
              min="3"
              max="10"
              value={formData.login_attempts_limit?.toString() || ''}
              onChange={(e) => handleInputChange('login_attempts_limit', e.target.value)}
              placeholder="e.g., 5"
            />
            <p className="text-xs text-[var(--color-text-muted)] mt-1">
              Maximum failed login attempts before account lockout (3-10)
            </p>
          </div>
        </CardContent>
        {canManageSettings && (
          <CardFooter>
            <div className="flex items-center gap-3 w-full">
              <Button
                type="button"
                variant="secondary"
                onClick={handleReset}
                disabled={!hasChanges || updateSecurity.isPending}
              >
                Reset
              </Button>
              <Button
                type="submit"
                disabled={!hasChanges || updateSecurity.isPending}
                style={{ marginLeft: 'auto' }}
              >
                {updateSecurity.isPending ? 'Saving...' : 'Save Changes'}
              </Button>
            </div>
          </CardFooter>
        )}
        </form>
      </Card>
    </div>
  );
};

/**
 * Change Password Card Component
 */
const ChangePasswordCard: React.FC = () => {
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [showCurrentPassword, setShowCurrentPassword] = useState(false);
  const [showNewPassword, setShowNewPassword] = useState(false);
  const [showPasswordConfirmation, setShowPasswordConfirmation] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<Record<string, string[]>>({});
  const [passwordMismatch, setPasswordMismatch] = useState(false);

  useEffect(() => {
    // Check password match in real-time
    if (passwordConfirmation && newPassword !== passwordConfirmation) {
      setPasswordMismatch(true);
    } else {
      setPasswordMismatch(false);
    }
  }, [newPassword, passwordConfirmation]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setError(null);
    setValidationErrors({});

    // Client-side validation
    if (newPassword !== passwordConfirmation) {
      setError('Mật khẩu xác nhận không khớp.');
      setIsSubmitting(false);
      return;
    }

    try {
      await authApi.changePassword({
        current_password: currentPassword,
        password: newPassword,
        password_confirmation: passwordConfirmation,
      });
      
      toast.success('Mật khẩu đã được thay đổi. Vui lòng đăng nhập lại.');
      
      // Clear form
      setCurrentPassword('');
      setNewPassword('');
      setPasswordConfirmation('');
      
      // Note: All tokens are revoked after password change
      // User will need to login again on all devices
    } catch (err: any) {
      console.error('Password change failed:', err);
      
      // Handle validation errors
      if (err.response?.data?.errors) {
        setValidationErrors(err.response.data.errors);
      } else if (err.response?.data?.error) {
        const errorData = err.response.data.error;
        if (errorData.code === 'PASSWORD_POLICY_VIOLATION') {
          setError(errorData.message || 'Mật khẩu không đáp ứng yêu cầu bảo mật.');
        } else if (errorData.message) {
          setError(errorData.message);
        } else {
          setError('Đã xảy ra lỗi. Vui lòng thử lại sau.');
        }
      } else if (err.message) {
        setError(err.message);
      } else {
        setError('Đã xảy ra lỗi. Vui lòng thử lại sau.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleReset = () => {
    setCurrentPassword('');
    setNewPassword('');
    setPasswordConfirmation('');
    setError(null);
    setValidationErrors({});
    setPasswordMismatch(false);
  };

  const hasChanges = currentPassword || newPassword || passwordConfirmation;

  return (
    <Card>
      <CardHeader>
        <CardTitle>Change Password</CardTitle>
        <p className="text-sm text-[var(--color-text-muted)] mt-1">
          Update your account password. Make sure to use a strong password.
        </p>
        <div className="mt-3 rounded-md bg-yellow-50 p-3 text-sm text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-200">
          <strong>Lưu ý:</strong> Sau khi đổi mật khẩu, bạn sẽ phải đăng nhập lại trên tất cả thiết bị.
        </div>
      </CardHeader>
      <form onSubmit={handleSubmit} data-testid="change-password-form">
        <CardContent className="space-y-4">
          {error && (
            <div className="rounded-md bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/20 dark:text-red-200">
              {error}
            </div>
          )}

          {/* Current Password */}
          <div>
            <label htmlFor="current_password" className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
              Current Password
            </label>
            <div className="relative">
              <Input
                id="current_password"
                type={showCurrentPassword ? 'text' : 'password'}
                value={currentPassword}
                onChange={(e) => setCurrentPassword(e.target.value)}
                required
                className="pr-10"
                disabled={isSubmitting}
                autoComplete="current-password"
                data-testid="current-password"
              />
              <button
                type="button"
                onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                className="absolute inset-y-0 right-0 flex items-center pr-3 text-[var(--color-text-muted)] hover:text-[var(--color-text-primary)] focus:outline-none"
                disabled={isSubmitting}
                aria-label={showCurrentPassword ? 'Hide password' : 'Show password'}
              >
                {showCurrentPassword ? (
                  <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                  </svg>
                ) : (
                  <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                )}
              </button>
            </div>
            {validationErrors.current_password && (
              <p className="mt-1 text-sm text-red-600 dark:text-red-400">
                {validationErrors.current_password[0]}
              </p>
            )}
          </div>

          {/* New Password */}
          <div>
            <label htmlFor="new_password" className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
              New Password
            </label>
            <div className="relative">
              <Input
                id="new_password"
                type={showNewPassword ? 'text' : 'password'}
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                required
                minLength={8}
                className="pr-10"
                disabled={isSubmitting}
                autoComplete="new-password"
                data-testid="new-password"
              />
              <button
                type="button"
                onClick={() => setShowNewPassword(!showNewPassword)}
                className="absolute inset-y-0 right-0 flex items-center pr-3 text-[var(--color-text-muted)] hover:text-[var(--color-text-primary)] focus:outline-none"
                disabled={isSubmitting}
                aria-label={showNewPassword ? 'Hide password' : 'Show password'}
              >
                {showNewPassword ? (
                  <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                  </svg>
                ) : (
                  <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                )}
              </button>
            </div>
            {validationErrors.password && (
              <p className="mt-1 text-sm text-red-600 dark:text-red-400">
                {validationErrors.password[0]}
              </p>
            )}
            <p className="mt-1 text-xs text-[var(--color-text-muted)]">
              Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ hoa, chữ thường, số và ký tự đặc biệt
            </p>
          </div>

          {/* Password Confirmation */}
          <div>
            <label htmlFor="password_confirmation" className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
              Confirm New Password
            </label>
            <div className="relative">
              <Input
                id="password_confirmation"
                type={showPasswordConfirmation ? 'text' : 'password'}
                value={passwordConfirmation}
                onChange={(e) => setPasswordConfirmation(e.target.value)}
                required
                minLength={8}
                className="pr-10"
                disabled={isSubmitting}
                autoComplete="new-password"
                data-testid="password-confirmation"
              />
              <button
                type="button"
                onClick={() => setShowPasswordConfirmation(!showPasswordConfirmation)}
                className="absolute inset-y-0 right-0 flex items-center pr-3 text-[var(--color-text-muted)] hover:text-[var(--color-text-primary)] focus:outline-none"
                disabled={isSubmitting}
                aria-label={showPasswordConfirmation ? 'Hide password' : 'Show password'}
              >
                {showPasswordConfirmation ? (
                  <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                  </svg>
                ) : (
                  <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                )}
              </button>
            </div>
            {passwordMismatch && (
              <p className="mt-1 text-sm text-red-600 dark:text-red-400">
                Mật khẩu xác nhận không khớp
              </p>
            )}
            {validationErrors.password_confirmation && (
              <p className="mt-1 text-sm text-red-600 dark:text-red-400">
                {validationErrors.password_confirmation[0]}
              </p>
            )}
          </div>
        </CardContent>
        <CardFooter>
          <div className="flex items-center gap-3 w-full">
            <Button
              type="button"
              variant="secondary"
              onClick={handleReset}
              disabled={!hasChanges || isSubmitting}
            >
              Reset
            </Button>
            <Button
              type="submit"
              disabled={!hasChanges || isSubmitting || passwordMismatch || !currentPassword || !newPassword || !passwordConfirmation}
              style={{ marginLeft: 'auto' }}
              data-testid="change-password-submit"
            >
              {isSubmitting ? 'Changing Password...' : 'Change Password'}
            </Button>
          </div>
        </CardFooter>
      </form>
    </Card>
  );
};

