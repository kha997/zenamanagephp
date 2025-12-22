import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { authApi } from '../api';
import { Button } from '../../../shared/ui/button';

export const ForgotPasswordPage: React.FC = () => {
  const [email, setEmail] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSuccess, setIsSuccess] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<Record<string, string[]>>({});

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setError(null);
    setValidationErrors({});
    setIsSuccess(false);

    try {
      await authApi.forgotPassword(email);
      setIsSuccess(true);
      setEmail(''); // Clear email for security
    } catch (err: any) {
      console.error('Password reset request failed:', err);
      
      // Handle validation errors
      if (err.response?.data?.errors) {
        setValidationErrors(err.response.data.errors);
      } else if (err.response?.data?.error?.message) {
        setError(err.response.data.error.message);
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
              Quên mật khẩu
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
                Email đã được gửi
              </h3>
              <p className="mt-2 text-sm text-[var(--muted)]">
                Nếu email tồn tại trong hệ thống, chúng tôi đã gửi link đặt lại mật khẩu đến địa chỉ email của bạn.
              </p>
              <p className="mt-2 text-sm text-[var(--muted)]">
                Vui lòng kiểm tra hộp thư và làm theo hướng dẫn trong email.
              </p>
              <div className="mt-6">
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
      </div>
    );
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-[var(--bg)] px-4 py-12">
      <div className="w-full max-w-md space-y-8">
        <div>
          <h2 className="mt-6 text-center text-3xl font-bold tracking-tight text-[var(--text)]">
            Quên mật khẩu
          </h2>
          <p className="mt-2 text-center text-sm text-[var(--muted)]">
            Nhập email để nhận link đặt lại mật khẩu
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
              <label htmlFor="email" className="block text-sm font-medium text-[var(--text)]">
                Email
              </label>
              <input
                id="email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                className="mt-1 block w-full rounded-md border border-[var(--border)] bg-[var(--bg)] px-3 py-2 text-[var(--text)] shadow-sm focus:border-[var(--accent)] focus:outline-none focus:ring-1 focus:ring-[var(--accent)]"
                disabled={isSubmitting}
                autoComplete="email"
              />
              {validationErrors.email && (
                <p className="mt-1 text-sm text-red-600 dark:text-red-400">
                  {validationErrors.email[0]}
                </p>
              )}
            </div>

            <Button
              type="submit"
              disabled={isSubmitting || !email}
              className="w-full"
            >
              {isSubmitting ? 'Đang gửi...' : 'Gửi link đặt lại mật khẩu'}
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

export default ForgotPasswordPage;

