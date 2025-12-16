import React, { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { authApi } from '../api';
import { Button } from '../../../shared/ui/button';

export const ChangePasswordPage: React.FC = () => {
  const navigate = useNavigate();
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSuccess, setIsSuccess] = useState(false);
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
    setIsSuccess(false);

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
      
      setIsSuccess(true);
      
      // Clear form
      setCurrentPassword('');
      setNewPassword('');
      setPasswordConfirmation('');
      
      // Redirect to login after 3 seconds (since tokens are revoked)
      setTimeout(() => {
        navigate('/login', { 
          state: { message: 'Mật khẩu đã được thay đổi. Vui lòng đăng nhập lại.' } 
        });
      }, 3000);
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

  if (isSuccess) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--bg)] px-4 py-12">
        <div className="w-full max-w-md space-y-8">
          <div>
            <h2 className="mt-6 text-center text-3xl font-bold tracking-tight text-[var(--text)]">
              Đổi mật khẩu
            </h2>
          </div>
          <div className="mt-8 rounded-lg bg-[var(--surface)] px-6 py-8 shadow">
            <div className="text-center">
              <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                <svg
                  className="h-6 w-6 text-green-600"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M5 13l4 4L19 7"
                  />
                </svg>
              </div>
              <h3 className="mt-4 text-lg font-medium text-[var(--text)]">
                Mật khẩu đã được thay đổi
              </h3>
              <p className="mt-2 text-sm text-[var(--muted)]">
                Mật khẩu của bạn đã được cập nhật thành công.
              </p>
              <p className="mt-2 text-sm text-[var(--muted)]">
                Vì lý do bảo mật, bạn sẽ được chuyển đến trang đăng nhập trong vài giây.
              </p>
              <div className="mt-6">
                <Link
                  to="/login"
                  className="text-sm font-medium text-[var(--accent)] hover:text-[var(--accent-hover)]"
                >
                  Đăng nhập ngay →
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-[var(--bg)] px-4 py-12">
      <div className="w-full max-w-md space-y-8">
        <div>
          <h2 className="mt-6 text-center text-3xl font-bold tracking-tight text-[var(--text)]">
            Đổi mật khẩu
          </h2>
          <p className="mt-2 text-center text-sm text-[var(--muted)]">
            Nhập mật khẩu hiện tại và mật khẩu mới
          </p>
        </div>
        <div className="mt-8 rounded-lg bg-[var(--surface)] px-6 py-8 shadow">
          <div className="mb-4 rounded-md bg-yellow-50 p-3 text-sm text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-200">
            <strong>Lưu ý:</strong> Sau khi đổi mật khẩu, bạn sẽ phải đăng nhập lại trên tất cả thiết bị.
          </div>
          
          <form onSubmit={handleSubmit} className="space-y-4" data-testid="change-password-form">
            {error && (
              <div className="rounded-md bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/20 dark:text-red-200">
                {error}
              </div>
            )}

            <div>
              <label htmlFor="current_password" className="block text-sm font-medium text-[var(--text)]">
                Mật khẩu hiện tại
              </label>
              <div className="relative mt-1">
                <input
                  id="current_password"
                  type={showCurrentPassword ? 'text' : 'password'}
                  value={currentPassword}
                  onChange={(e) => setCurrentPassword(e.target.value)}
                  required
                  className="block w-full rounded-md border border-[var(--border)] bg-[var(--bg)] px-3 py-2 pr-10 text-[var(--text)] shadow-sm focus:border-[var(--accent)] focus:outline-none focus:ring-1 focus:ring-[var(--accent)]"
                  disabled={isSubmitting}
                  autoComplete="current-password"
                />
                <button
                  type="button"
                  className="absolute inset-y-0 right-0 flex items-center pr-3"
                  onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                >
                  {showCurrentPassword ? (
                    <svg className="h-5 w-5 text-[var(--muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                  ) : (
                    <svg className="h-5 w-5 text-[var(--muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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

            <div>
              <label htmlFor="new_password" className="block text-sm font-medium text-[var(--text)]">
                Mật khẩu mới
              </label>
              <div className="relative mt-1">
                <input
                  id="new_password"
                  type={showNewPassword ? 'text' : 'password'}
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  required
                  className="block w-full rounded-md border border-[var(--border)] bg-[var(--bg)] px-3 py-2 pr-10 text-[var(--text)] shadow-sm focus:border-[var(--accent)] focus:outline-none focus:ring-1 focus:ring-[var(--accent)]"
                  disabled={isSubmitting}
                  autoComplete="new-password"
                />
                <button
                  type="button"
                  className="absolute inset-y-0 right-0 flex items-center pr-3"
                  onClick={() => setShowNewPassword(!showNewPassword)}
                >
                  {showNewPassword ? (
                    <svg className="h-5 w-5 text-[var(--muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                  ) : (
                    <svg className="h-5 w-5 text-[var(--muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
            </div>

            <div>
              <label htmlFor="password_confirmation" className="block text-sm font-medium text-[var(--text)]">
                Xác nhận mật khẩu mới
              </label>
              <div className="relative mt-1">
                <input
                  id="password_confirmation"
                  type={showPasswordConfirmation ? 'text' : 'password'}
                  value={passwordConfirmation}
                  onChange={(e) => setPasswordConfirmation(e.target.value)}
                  required
                  className={`block w-full rounded-md border ${
                    passwordMismatch ? 'border-red-500' : 'border-[var(--border)]'
                  } bg-[var(--bg)] px-3 py-2 pr-10 text-[var(--text)] shadow-sm focus:border-[var(--accent)] focus:outline-none focus:ring-1 focus:ring-[var(--accent)]`}
                  disabled={isSubmitting}
                  autoComplete="new-password"
                />
                <button
                  type="button"
                  className="absolute inset-y-0 right-0 flex items-center pr-3"
                  onClick={() => setShowPasswordConfirmation(!showPasswordConfirmation)}
                >
                  {showPasswordConfirmation ? (
                    <svg className="h-5 w-5 text-[var(--muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                  ) : (
                    <svg className="h-5 w-5 text-[var(--muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  )}
                </button>
              </div>
              {passwordMismatch && (
                <p className="mt-1 text-sm text-red-600 dark:text-red-400">
                  Mật khẩu xác nhận không khớp.
                </p>
              )}
              {validationErrors.password_confirmation && (
                <p className="mt-1 text-sm text-red-600 dark:text-red-400">
                  {validationErrors.password_confirmation[0]}
                </p>
              )}
            </div>

            <Button
              type="submit"
              disabled={isSubmitting || !currentPassword || !newPassword || !passwordConfirmation || passwordMismatch}
              className="w-full"
            >
              {isSubmitting ? 'Đang xử lý...' : 'Đổi mật khẩu'}
            </Button>
          </form>

          <div className="mt-6 text-center">
            <Link
              to="/app/dashboard"
              className="text-sm font-medium text-[var(--accent)] hover:text-[var(--accent-hover)]"
            >
              ← Quay lại trang chủ
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ChangePasswordPage;

