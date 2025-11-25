import React, { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Link } from 'react-router-dom';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../../shared/ui/card';
import { Button } from '../../shared/ui/button';
import { useI18n } from '../../app/i18n-context';
import { authApi } from '../../shared/auth/api';

const forgotPasswordSchema = z.object({
  email: z.string().email('Invalid email address'),
});

type ForgotPasswordFormData = z.infer<typeof forgotPasswordSchema>;

const ForgotPasswordPage: React.FC = () => {
  const { t } = useI18n();
  const [isLoading, setIsLoading] = useState(false);
  const [isSuccess, setIsSuccess] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<ForgotPasswordFormData>({
    resolver: zodResolver(forgotPasswordSchema),
    defaultValues: {
      email: '',
    },
  });

  const onSubmit = async (data: ForgotPasswordFormData) => {
    setIsLoading(true);
    setError(null);

    try {
      await authApi.forgotPassword(data);
      setIsSuccess(true);
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Failed to send reset email';
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
                ✅ {t('auth.forgotPassword.successTitle', { defaultValue: 'Email Sent!' })}
              </CardTitle>
              <CardDescription className="text-center">
                {t('auth.forgotPassword.successDescription', { defaultValue: 'Check your email for password reset instructions' })}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="text-center space-y-4">
                <p className="text-sm text-[var(--color-text-muted)]">
                  {t('auth.forgotPassword.successMessage', { defaultValue: 'We\'ve sent a password reset link to your email address. Please check your inbox and follow the instructions to reset your password.' })}
                </p>
                <div className="flex flex-col gap-2">
                  <Link
                    to="/login"
                    className="text-sm text-[var(--color-semantic-primary-500)] hover:text-[var(--color-semantic-primary-600)]"
                  >
                    {t('auth.forgotPassword.backToLogin', { defaultValue: 'Back to Sign In' })}
                  </Link>
                  <Button
                    variant="outline"
                    onClick={() => setIsSuccess(false)}
                    className="w-full"
                  >
                    {t('auth.forgotPassword.tryAgain', { defaultValue: 'Try Again' })}
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-[var(--color-surface-base)] py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full space-y-8">
        <div className="text-center">
          <h1 className="text-3xl font-bold text-[var(--color-text-primary)]">
            {t('auth.forgotPassword.title', { defaultValue: 'Forgot Password?' })}
          </h1>
          <p className="mt-2 text-[var(--color-text-muted)]">
            {t('auth.forgotPassword.subtitle', { defaultValue: 'Enter your email to receive reset instructions' })}
          </p>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>{t('auth.forgotPassword.formTitle', { defaultValue: 'Reset Password' })}</CardTitle>
            <CardDescription>
              {t('auth.forgotPassword.formDescription', { defaultValue: 'We\'ll send you a link to reset your password' })}
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
                <label htmlFor="email" className="text-sm font-medium text-[var(--color-text-primary)]">
                  {t('auth.forgotPassword.email', { defaultValue: 'Email Address' })}
                </label>
                <input
                  {...register('email')}
                  type="email"
                  id="email"
                  autoComplete="email"
                  className="w-full px-3 py-2 border border-[var(--color-border-default)] rounded-[var(--radius-md)] focus:outline-none focus:ring-2 focus:ring-[var(--color-semantic-primary-500)] focus:border-transparent"
                  placeholder={t('auth.forgotPassword.emailPlaceholder', { defaultValue: 'Enter your email' })}
                />
                {errors.email && (
                  <p className="text-sm text-[var(--color-semantic-danger-500)]">
                    {errors.email.message}
                  </p>
                )}
              </div>

              <Button
                type="submit"
                className="w-full"
                loading={isLoading}
                disabled={isLoading}
              >
                {t('auth.forgotPassword.submit', { defaultValue: 'Send Reset Link' })}
              </Button>
            </form>

            <div className="mt-6 text-center">
              <Link
                to="/login"
                className="text-sm text-[var(--color-semantic-primary-500)] hover:text-[var(--color-semantic-primary-600)]"
              >
                {t('auth.forgotPassword.backToLogin', { defaultValue: 'Back to Sign In' })}
              </Link>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
};

export default ForgotPasswordPage;
