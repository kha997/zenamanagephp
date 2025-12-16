import React, { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { authApi } from '../api';
import { Button } from '../../../shared/ui/button';

export const RegisterPage: React.FC = () => {
  const navigate = useNavigate();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [tenantName, setTenantName] = useState('');
  const [phone, setPhone] = useState('');
  const [terms, setTerms] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSuccess, setIsSuccess] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<Record<string, string[]>>({});
  const [showPassword, setShowPassword] = useState(false);
  const [showPasswordConfirmation, setShowPasswordConfirmation] = useState(false);
  const [passwordMismatch, setPasswordMismatch] = useState(false);
  const [publicSignupEnabled, setPublicSignupEnabled] = useState(false); // Default to false, will check from API

  useEffect(() => {
    // Check feature flag from API
    const checkSignupEnabled = async () => {
      try {
        const response = await fetch('/api/public/config', {
          method: 'GET',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'include',
        });

        if (response.ok) {
          const data = await response.json();
          setPublicSignupEnabled(data.data?.public_signup_enabled ?? false);
        } else {
          // If API fails, default to disabled for safety
          setPublicSignupEnabled(false);
        }
      } catch (error) {
        console.error('Error checking signup status:', error);
        // Default to disabled on error
        setPublicSignupEnabled(false);
      }
    };

    checkSignupEnabled();
  }, []);

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

    if (!terms) {
      setError('Bạn phải chấp nhận điều khoản và điều kiện.');
      setIsSubmitting(false);
      return;
    }

    try {
      await authApi.register({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
        tenant_name: tenantName,
        phone: phone || undefined,
        terms: true,
      });
      setIsSuccess(true);
    } catch (err: any) {
      console.error('Registration failed:', err);
      
      // Handle validation errors
      if (err.response?.data?.errors) {
        setValidationErrors(err.response.data.errors);
      } else if (err.response?.data?.error) {
        const errorData = err.response.data.error;
        if (errorData.code === 'REGISTRATION_FAILED' || errorData.code === 'VALIDATION_FAILED') {
          setError(errorData.message || 'Đăng ký thất bại. Vui lòng kiểm tra thông tin và thử lại.');
        } else if (err.response?.status === 403 || err.response?.status === 404) {
          setError('Đăng ký tạm thời bị tắt. Vui lòng liên hệ quản trị viên.');
          setPublicSignupEnabled(false);
        } else {
          setError(errorData.message || 'Đã xảy ra lỗi. Vui lòng thử lại sau.');
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

  if (!publicSignupEnabled) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--bg)] px-4 py-12 sm:px-6 lg:px-8">
        <div className="w-full max-w-md space-y-8">
          <div>
            <h2 className="mt-6 text-center text-3xl font-bold tracking-tight text-[var(--text)]">
              Đăng ký tài khoản
            </h2>
          </div>
          <div className="mt-8 rounded-lg bg-[var(--surface)] px-6 py-8 shadow">
            <div className="text-center">
              <p className="text-[var(--muted)] mb-4">
                Đăng ký tạm thời bị tắt.
              </p>
              <p className="text-sm text-[var(--muted)] mb-6">
                Vui lòng liên hệ quản trị viên để được tạo tài khoản.
              </p>
              <Link
                to="/login"
                className="inline-block font-medium text-[var(--accent)] hover:text-[var(--accent-hover)]"
              >
                ← Quay lại trang đăng nhập
              </Link>
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (isSuccess) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--bg)] px-4 py-12 sm:px-6 lg:px-8">
        <div className="w-full max-w-md space-y-8">
          <div>
            <h2 className="mt-6 text-center text-3xl font-bold tracking-tight text-[var(--text)]">
              Đăng ký tài khoản
            </h2>
          </div>
          <div className="mt-8 rounded-lg bg-[var(--surface)] px-6 py-8 shadow">
            <div className="text-center" data-testid="register-success">
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
                Đăng ký thành công
              </h3>
              <p className="mt-2 text-sm text-[var(--muted)]">
                Vui lòng kiểm tra email để xác thực tài khoản của bạn.
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
    <div className="flex min-h-screen items-center justify-center bg-[var(--bg)] px-4 py-12 sm:px-6 lg:px-8">
      <div className="w-full max-w-md space-y-8">
        <div>
          <h2 className="mt-6 text-center text-3xl font-bold tracking-tight text-[var(--text)]">
            Đăng ký tài khoản
          </h2>
          <p className="mt-2 text-center text-sm text-[var(--muted)]">
            Tạo tài khoản mới để bắt đầu sử dụng ZenaManage
          </p>
        </div>
        <div className="mt-8 rounded-lg bg-[var(--surface)] px-6 py-8 shadow">
          <form onSubmit={handleSubmit} className="space-y-4" data-testid="register-form">
            {error && (
              <div className="rounded-md bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/20 dark:text-red-200" data-testid="register-error">
                {error}
              </div>
            )}

            <div>
              <label htmlFor="name" className="block text-sm font-medium text-[var(--text)]">
                Họ và tên
              </label>
              <input
                id="name"
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
                minLength={2}
                maxLength={255}
                className="mt-1 block w-full rounded-md border border-[var(--border)] bg-[var(--bg)] px-3 py-2 text-[var(--text)] shadow-sm focus:border-[var(--accent)] focus:outline-none focus:ring-1 focus:ring-[var(--accent)]"
                disabled={isSubmitting}
                autoComplete="name"
              />
              {validationErrors.name && (
                <p className="mt-1 text-sm text-red-600 dark:text-red-400">
                  {validationErrors.name[0]}
                </p>
              )}
            </div>

            <div>
              <label htmlFor="email" className="block text-sm font-medium text-[var(--text)]">
                Email
              </label>
              <input
                id="email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                maxLength={255}
                className="mt-1 block w-full rounded-md border border-[var(--border)] bg-[var(--bg)] px-3 py-2 text-[var(--text)] shadow-sm focus:border-[var(--accent)] focus:outline-none focus:ring-1 focus:ring-[var(--accent)]"
                disabled={isSubmitting}
                autoComplete="email"
                data-testid="register-email"
              />
              {validationErrors.email && (
                <p className="mt-1 text-sm text-red-600 dark:text-red-400">
                  {validationErrors.email[0]}
                </p>
              )}
            </div>

            <div>
              <label htmlFor="tenant_name" className="block text-sm font-medium text-[var(--text)]">
                Tên công ty
              </label>
              <input
                id="tenant_name"
                type="text"
                value={tenantName}
                onChange={(e) => setTenantName(e.target.value)}
                required
                minLength={2}
                maxLength={255}
                className="mt-1 block w-full rounded-md border border-[var(--border)] bg-[var(--bg)] px-3 py-2 text-[var(--text)] shadow-sm focus:border-[var(--accent)] focus:outline-none focus:ring-1 focus:ring-[var(--accent)]"
                disabled={isSubmitting}
                autoComplete="organization"
              />
              {validationErrors.tenant_name && (
                <p className="mt-1 text-sm text-red-600 dark:text-red-400">
                  {validationErrors.tenant_name[0]}
                </p>
              )}
            </div>

            <div>
              <label htmlFor="phone" className="block text-sm font-medium text-[var(--text)]">
                Số điện thoại <span className="text-[var(--muted)]">(tùy chọn)</span>
              </label>
              <input
                id="phone"
                type="tel"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                maxLength={20}
                className="mt-1 block w-full rounded-md border border-[var(--border)] bg-[var(--bg)] px-3 py-2 text-[var(--text)] shadow-sm focus:border-[var(--accent)] focus:outline-none focus:ring-1 focus:ring-[var(--accent)]"
                disabled={isSubmitting}
                autoComplete="tel"
              />
              {validationErrors.phone && (
                <p className="mt-1 text-sm text-red-600 dark:text-red-400">
                  {validationErrors.phone[0]}
                </p>
              )}
            </div>

            <div>
              <label htmlFor="password" className="block text-sm font-medium text-[var(--text)]">
                Mật khẩu
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
                  data-testid="register-password"
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
                Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ hoa, chữ thường, số và ký tự đặc biệt
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

            <div className="flex items-start">
              <input
                id="terms"
                type="checkbox"
                checked={terms}
                onChange={(e) => setTerms(e.target.checked)}
                required
                className="mt-1 h-4 w-4 rounded border-[var(--border)] text-[var(--accent)] focus:ring-[var(--accent)]"
                disabled={isSubmitting}
              />
              <label htmlFor="terms" className="ml-2 block text-sm text-[var(--text)]">
                Tôi đồng ý với{' '}
                <a href="/terms" target="_blank" className="text-[var(--accent)] hover:text-[var(--accent-hover)]">
                  điều khoản và điều kiện
                </a>
              </label>
            </div>
            {validationErrors.terms && (
              <p className="text-sm text-red-600 dark:text-red-400">
                {validationErrors.terms[0]}
              </p>
            )}

            <Button
              type="submit"
              disabled={isSubmitting || !name || !email || !password || !passwordConfirmation || !tenantName || !terms || passwordMismatch}
              className="w-full"
              data-testid="register-submit"
            >
              {isSubmitting ? 'Đang đăng ký...' : 'Đăng ký'}
            </Button>
          </form>

          <div className="mt-6 text-center">
            <p className="text-sm text-[var(--muted)]">
              Đã có tài khoản?{' '}
              <Link
                to="/login"
                className="font-medium text-[var(--accent)] hover:text-[var(--accent-hover)]"
              >
                Đăng nhập ngay
              </Link>
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default RegisterPage;


