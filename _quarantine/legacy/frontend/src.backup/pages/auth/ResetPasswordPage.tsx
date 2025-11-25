import React, { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Link, useSearchParams, useNavigate } from 'react-router-dom';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../../shared/ui/card';
import { Button } from '../../shared/ui/button';
import { useI18n } from '../../app/i18n-context';
import { authApi } from '../../shared/auth/api';

const resetPasswordSchema = z.object({
  password: z.string().min(8, 'Password must be at least 8 characters'),
  password_confirmation: z.string(),
}).refine((data) => data.password === data.password_confirmation, {
  message: "Passwords don't match",
  path: ["password_confirmation"],
});

type ResetPasswordFormData = z.infer<typeof resetPasswordSchema>;

const ResetPasswordPage: React.FC = () => {
  const { t } = useI18n();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const [isLoading, setIsLoading] = useState(false);
  const [isSuccess, setIsSuccess] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  const token = searchParams.get('token');
  const email = searchParams.get('email');

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<ResetPasswordFormData>({
    resolver: zodResolver(resetPasswordSchema),
    defaultValues: {
      password: '',
      password_confirmation: '',
    },
  });

  useEffect(() => {
    if (!token || !email) {
      navigate('/forgot-password');
    }
  }, [token, email, navigate]);

  const onSubmit = async (data: ResetPasswordFormData) => {
    if (!token || !email) return;

    setIsLoading(true);
    setError(null);

    try {
      await authApi.resetPassword({
        email,
        token,
        password: data.password,
        password_confirmation: data.password_confirmation,
      });
      setIsSuccess(true);
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Failed to reset password';
      setError(errorMessage);
    } finally {
      setIsLoading(false);
    }
  };

  if (isSuccess) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-[var(--color-surface-base)] py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-md w-full space-y-8">
          <Card>
            <CardHeader>
              <CardTitle className="text-center text-[var(--color-semantic-success-600)]">
                ✅ {t('auth.resetPassword.successTitle', { defaultValue: 'Password Reset!' })}
              </CardTitle>
              <CardDescription className="text-center">
                {t('auth.resetPassword.successDescription', { defaultValue: 'Your password has been successfully reset' })}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="text-center space-y-4">
                <p className="text-sm text-[var(--color-text-muted)]">
                  {t('auth.resetPassword.successMessage', { defaultValue: 'You can now sign in with your new password.' })}
                </p>
                <Link to="/login">
                  <Button className="w-full">
                    {t('auth.resetPassword.signIn', { defaultValue: 'Sign In' })}
                  </Button>
                </Link>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    );
  }

  if (!token || !email) {
    return null; // Will redirect in useEffect
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-[var(--color-surface-base)] py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full space-y-8">
        <div className="text-center">
          <h1 className="text-3xl font-bold text-[var(--color-text-primary)]">
            {t('auth.resetPassword.title', { defaultValue: 'Reset Password' })}
          </h1>
          <p className="mt-2 text-[var(--color-text-muted)]">
            {t('auth.resetPassword.subtitle', { defaultValue: 'Enter your new password' })}
          </p>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>{t('auth.resetPassword.formTitle', { defaultValue: 'New Password' })}</CardTitle>
            <CardDescription>
              {t('auth.resetPassword.formDescription', { defaultValue: 'Choose a strong password for your account' })}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
              {error && (
                <div className="p-3 rounded-[var(--radius-md)] bg-[var(--color-semantic-danger-50)] border border-[var(--color-semantic-danger-200)]">
                  <p className="text-sm text-[var(--color-semantic-danger-700)]">
                    {error}
                  </p>
                </div>
              )}

              <div className="space-y-2">
                <label htmlFor="password" className="text-sm font-medium text-[var(--color-text-primary)]">
                  {t('auth.resetPassword.password', { defaultValue: 'New Password' })}
                </label>
                <div className="relative">
                  <input
                    {...register('password')}
                    type={showPassword ? 'text' : 'password'}
                    id="password"
                    autoComplete="new-password"
                    className="w-full px-3 py-2 pr-10 border border-[var(--color-border-default)] rounded-[var(--radius-md)] focus:outline-none focus:ring-2 focus:ring-[var(--color-semantic-primary-500)] focus:border-transparent"
                    placeholder={t('auth.resetPassword.passwordPlaceholder', { defaultValue: 'Enter new password' })}
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute inset-y-0 right-0 pr-3 flex items-center text-[var(--color-text-muted)] hover:text-[var(--color-text-primary)]"
                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                  >
                    {showPassword ? '👁️' : '👁️‍🗨️'}
                  </button>
                </div>
                {errors.password && (
                  <p className="text-sm text-[var(--color-semantic-danger-500)]">
                    {errors.password.message}
                  </p>
                )}
              </div>

              <div className="space-y-2">
                <label htmlFor="password_confirmation" className="text-sm font-medium text-[var(--color-text-primary)]">
                  {t('auth.resetPassword.confirmPassword', { defaultValue: 'Confirm Password' })}
                </label>
                <div className="relative">
                  <input
                    {...register('password_confirmation')}
                    type={showConfirmPassword ? 'text' : 'password'}
                    id="password_confirmation"
                    autoComplete="new-password"
                    className="w-full px-3 py-2 pr-10 border border-[var(--color-border-default)] rounded-[var(--radius-md)] focus:outline-none focus:ring-2 focus:ring-[var(--color-semantic-primary-500)] focus:border-transparent"
                    placeholder={t('auth.resetPassword.confirmPasswordPlaceholder', { defaultValue: 'Confirm new password' })}
                  />
                  <button
                    type="button"
                    onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                    className="absolute inset-y-0 right-0 pr-3 flex items-center text-[var(--color-text-muted)] hover:text-[var(--color-text-primary)]"
                    aria-label={showConfirmPassword ? 'Hide password' : 'Show password'}
                  >
                    {showConfirmPassword ? '👁️' : '👁️‍🗨️'}
                  </button>
                </div>
                {errors.password_confirmation && (
                  <p className="text-sm text-[var(--color-semantic-danger-500)]">
                    {errors.password_confirmation.message}
                  </p>
                )}
              </div>

              <div className="text-xs text-[var(--color-text-muted)]">
                <p>{t('auth.resetPassword.passwordRequirements', { defaultValue: 'Password must be at least 8 characters long' })}</p>
              </div>

              <Button
                type="submit"
                className="w-full"
                loading={isLoading}
                disabled={isLoading}
              >
                {t('auth.resetPassword.submit', { defaultValue: 'Reset Password' })}
              </Button>
            </form>

            <div className="mt-6 text-center">
              <Link
                to="/login"
                className="text-sm text-[var(--color-semantic-primary-500)] hover:text-[var(--color-semantic-primary-600)]"
              >
                {t('auth.resetPassword.backToLogin', { defaultValue: 'Back to Sign In' })}
              </Link>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
};

export default ResetPasswordPage;
