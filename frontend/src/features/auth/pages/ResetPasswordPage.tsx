import React, { useState, useEffect } from 'react';
import { useNavigate, useSearchParams, Link } from 'react-router-dom';
import { authApi } from '../api';
import { Button } from '../../../shared/ui/button';

export const ResetPasswordPage: React.FC = () => {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const [token, setToken] = useState<string>('');
  const [email, setEmail] = useState<string>('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSuccess, setIsSuccess] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<Record<string, string[]>>({});
  const [showPassword, setShowPassword] = useState(false);
  const [showPasswordConfirmation, setShowPasswordConfirmation] = useState(false);
  const [passwordMismatch, setPasswordMismatch] = useState(false);

  useEffect(() => {
    const tokenParam = searchParams.get('token');
    const emailParam = searchParams.get('email');

    if (!tokenParam || !emailParam) {
      setError('Link đặt lại mật khẩu không hợp lệ. Vui lòng yêu cầu link mới.');
      return;
    }

    setToken(tokenParam);
    setEmail(emailParam);
  }, [searchParams]);

  useEffect(() => {
    // Check password match in real-time
    if (passwordConfirmation && password !== passwordConfirmation) {
      setPasswordMismatch(true);
    } else {
      setPasswordMismatch(false);
    }
  }, [password, passwordConfirmation]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setError(null);
    setValidationErrors({});

    // Client-side validation
    if (password !== passwordConfirmation) {
      setError('Mật khẩu xác nhận không khớp.');
      setIsSubmitting(false);
      return;
    }

    if (!token || !email) {
      setError('Thiếu thông tin cần thiết. Vui lòng yêu cầu link đặt lại mật khẩu mới.');
      setIsSubmitting(false);
      return;
    }

    try {
      await authApi.resetPassword({
        email,
        password,
        password_confirmation: passwordConfirmation,
        token,
      });
      setIsSuccess(true);
      
      // Redirect to login after 2 seconds
      setTimeout(() => {
        navigate('/login', { replace: true });
      }, 2000);
    } catch (err: any) {
      console.error('Password reset failed:', err);
      
      // Handle validation errors
      if (err.response?.data?.errors) {
        setValidationErrors(err.response.data.errors);
      } else if (err.response?.data?.error) {
        const errorData = err.response.data.error;
        // Map error codes to user-friendly Vietnamese messages
        if (errorData.code === 'PASSWORD_RESET_FAILED' || errorData.code === 'INVALID_TOKEN' || errorData.code === 'TOKEN_EXPIRED' || errorData.code === 'USER_NOT_FOUND') {
          setError('Link đặt lại mật khẩu không hợp lệ hoặc đã hết hạn. Vui lòng yêu cầu link mới.');
        } else if (errorData.code === 'PASSWORD_POLICY_VIOLATION') {
          setError(errorData.message || 'Mật khẩu không đáp ứng yêu cầu bảo mật.');
        } else if (errorData.code === 'RESET_FAILED' || errorData.code === 'RESET_REQUEST_FAILED' || errorData.code === 'SEND_FAILED') {
          setError('Đã xảy ra lỗi khi đặt lại mật khẩu. Vui lòng thử lại sau.');
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
              Đặt lại mật khẩu
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
                Mật khẩu đã được đặt lại thành công
              </h3>
              <p className="mt-2 text-sm text-[var(--muted)]">
                Mật khẩu đã được đặt lại thành công. Đang chuyển đến trang đăng nhập...
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

  if (!token || !email) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--bg)] px-4 py-12">
        <div className="w-full max-w-md space-y-8">
          <div>
            <h2 className="mt-6 text-center text-3xl font-bold tracking-tight text-[var(--text)]">
              Đặt lại mật khẩu
            </h2>
          </div>
          <div className="mt-8 rounded-lg bg-[var(--surface)] px-6 py-8 shadow">
            <div className="text-center">
              <p className="text-[var(--muted)] mb-4">
                {error || 'Link đặt lại mật khẩu không hợp lệ.'}
              </p>
              <Link
                to="/forgot-password"
                className="text-sm font-medium text-[var(--accent)] hover:text-[var(--accent-hover)]"
              >
                Yêu cầu link mới
              </Link>
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
            Đặt lại mật khẩu
          </h2>
          <p className="mt-2 text-center text-sm text-[var(--muted)]">
            Nhập mật khẩu mới của bạn
          </p>
        </div>
        <div className="mt-8 rounded-lg bg-[var(--surface)] px-6 py-8 shadow">
          <form onSubmit={handleSubmit} className="space-y-4">
            {error && (
              <div className="rounded-md bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/20 dark:text-red-200">
                {error}
              </div>
            )}

            <div>
              <label htmlFor="password" className="block text-sm font-medium text-[var(--text)]">
                Mật khẩu mới
              </label>
              <div className="relative mt-1">
                <input
                  id="password"
                  type={showPassword ? 'text' : 'password'}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                  minLength={8}
                  className="block w-full rounded-md border border-[var(--border)] bg-[var(--bg)] px-3 py-2 pr-10 text-[var(--text)] shadow-sm focus:border-[var(--accent)] focus:outline-none focus:ring-1 focus:ring-[var(--accent)]"
                  disabled={isSubmitting}
                  autoComplete="new-password"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute inset-y-0 right-0 flex items-center pr-3 text-[var(--muted)] hover:text-[var(--text)] focus:outline-none"
                  disabled={isSubmitting}
                  aria-label={showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'}
                >
                  {showPassword ? (
                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"
                      />
                    </svg>
                  ) : (
                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                      />
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                      />
                    </svg>
                  )}
                </button>
              </div>
              {validationErrors.password && (
                <p className="mt-1 text-sm text-red-600 dark:text-red-400">
                  {validationErrors.password[0]}
                </p>
              )}
              <p className="mt-1 text-xs text-[var(--muted)]">
                Mật khẩu phải có ít nhất 8 ký tự
              </p>
            </div>

            <div>
              <label htmlFor="password_confirmation" className="block text-sm font-medium text-[var(--text)]">
                Xác nhận mật khẩu
              </label>
              <div className="relative mt-1">
                <input
                  id="password_confirmation"
                  type={showPasswordConfirmation ? 'text' : 'password'}
                  value={passwordConfirmation}
                  onChange={(e) => setPasswordConfirmation(e.target.value)}
                  required
                  minLength={8}
                  className="block w-full rounded-md border border-[var(--border)] bg-[var(--bg)] px-3 py-2 pr-10 text-[var(--text)] shadow-sm focus:border-[var(--accent)] focus:outline-none focus:ring-1 focus:ring-[var(--accent)]"
                  disabled={isSubmitting}
                  autoComplete="new-password"
                />
                <button
                  type="button"
                  onClick={() => setShowPasswordConfirmation(!showPasswordConfirmation)}
                  className="absolute inset-y-0 right-0 flex items-center pr-3 text-[var(--muted)] hover:text-[var(--text)] focus:outline-none"
                  disabled={isSubmitting}
                  aria-label={showPasswordConfirmation ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'}
                >
                  {showPasswordConfirmation ? (
                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"
                      />
                    </svg>
                  ) : (
                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                      />
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                      />
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

            <Button
              type="submit"
              disabled={isSubmitting || !password || !passwordConfirmation || passwordMismatch}
              className="w-full"
            >
              {isSubmitting ? 'Đang đặt lại mật khẩu...' : 'Đặt lại mật khẩu'}
            </Button>
          </form>

          <div className="mt-6 text-center">
            <Link
              to="/login"
              className="text-sm font-medium text-[var(--accent)] hover:text-[var(--accent-hover)]"
            >
              ← Quay lại trang đăng nhập
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ResetPasswordPage;

