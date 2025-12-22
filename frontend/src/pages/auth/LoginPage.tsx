import React, { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useNavigate, Link } from 'react-router-dom';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../../shared/ui/card';
import { Button } from '../../shared/ui/button';
import { useI18n } from '../../app/i18n-context';
import { useAuth } from '../../shared/auth/hooks';

const loginSchema = z.object({
  email: z.string().email('Invalid email address'),
  password: z.string().min(6, 'Password must be at least 6 characters'),
  remember: z.boolean().optional(),
});

type LoginFormData = z.infer<typeof loginSchema>;

const LoginPage: React.FC = () => {
  const { t } = useI18n();
  const navigate = useNavigate();
  const { login, isLoading, error, isAuthenticated } = useAuth();
  const [showPassword, setShowPassword] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      email: '',
      password: '',
      remember: false,
    },
  });

  // Redirect if already authenticated
  useEffect(() => {
    if (isAuthenticated) {
      navigate('/app/dashboard');
    }
  }, [isAuthenticated, navigate]);

  const onSubmit = async (data: LoginFormData) => {
    try {
      await login(data.email, data.password);
      navigate('/app/dashboard');
    } catch (error) {
      // Error is handled by the auth store
      console.error('Login failed:', error);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-[var(--color-surface-base)] py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full space-y-8">
        <div className="text-center">
          <h1 className="text-3xl font-bold text-[var(--color-text-primary)]">
            {t('auth.login.title', { defaultValue: 'Welcome back' })}
          </h1>
          <p className="mt-2 text-[var(--color-text-muted)]">
            {t('auth.login.subtitle', { defaultValue: 'Sign in to your account' })}
          </p>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>{t('auth.login.formTitle', { defaultValue: 'Sign In' })}</CardTitle>
            <CardDescription>
              {t('auth.login.formDescription', { defaultValue: 'Enter your credentials to access your account' })}
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
                  {t('auth.login.email', { defaultValue: 'Email Address' })}
                </label>
                <input
                  {...register('email')}
                  type="email"
                  id="email"
                  autoComplete="email"
                  className="w-full px-3 py-2 border border-[var(--color-border-default)] rounded-[var(--radius-md)] focus:outline-none focus:ring-2 focus:ring-[var(--color-semantic-primary-500)] focus:border-transparent"
                  placeholder={t('auth.login.emailPlaceholder', { defaultValue: 'Enter your email' })}
                />
                {errors.email && (
                  <p className="text-sm text-[var(--color-semantic-danger-500)]">
                    {errors.email.message}
                  </p>
                )}
              </div>

              <div className="space-y-2">
                <label htmlFor="password" className="text-sm font-medium text-[var(--color-text-primary)]">
                  {t('auth.login.password', { defaultValue: 'Password' })}
                </label>
                <div className="relative">
                  <input
                    {...register('password')}
                    type={showPassword ? 'text' : 'password'}
                    id="password"
                    autoComplete="current-password"
                    className="w-full px-3 py-2 pr-10 border border-[var(--color-border-default)] rounded-[var(--radius-md)] focus:outline-none focus:ring-2 focus:ring-[var(--color-semantic-primary-500)] focus:border-transparent"
                    placeholder={t('auth.login.passwordPlaceholder', { defaultValue: 'Enter your password' })}
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

              <div className="flex items-center justify-between">
                <div className="flex items-center">
                  <input
                    {...register('remember')}
                    type="checkbox"
                    id="remember"
                    className="h-4 w-4 text-[var(--color-semantic-primary-500)] focus:ring-[var(--color-semantic-primary-500)] border-[var(--color-border-default)] rounded"
                  />
                  <label htmlFor="remember" className="ml-2 block text-sm text-[var(--color-text-secondary)]">
                    {t('auth.login.remember', { defaultValue: 'Remember me' })}
                  </label>
                </div>

                <Link
                  to="/forgot-password"
                  className="text-sm text-[var(--color-semantic-primary-500)] hover:text-[var(--color-semantic-primary-600)]"
                >
                  {t('auth.login.forgotPassword', { defaultValue: 'Forgot password?' })}
                </Link>
              </div>

              <Button
                type="submit"
                className="w-full"
                loading={isLoading}
                disabled={isLoading}
              >
                {t('auth.login.submit', { defaultValue: 'Sign In' })}
              </Button>
            </form>

            <div className="mt-6">
              <div className="relative">
                <div className="absolute inset-0 flex items-center">
                  <div className="w-full border-t border-[var(--color-border-subtle)]" />
                </div>
                <div className="relative flex justify-center text-sm">
                  <span className="px-2 bg-[var(--color-surface-base)] text-[var(--color-text-muted)]">
                    {t('auth.login.or', { defaultValue: 'Or' })}
                  </span>
                </div>
              </div>

              <div className="mt-6 text-center">
                <p className="text-sm text-[var(--color-text-muted)]">
                  {t('auth.login.noAccount', { defaultValue: "Don't have an account?" })}{' '}
                  <Link
                    to="/register"
                    className="text-[var(--color-semantic-primary-500)] hover:text-[var(--color-semantic-primary-600)]"
                  >
                    {t('auth.login.signUp', { defaultValue: 'Sign up' })}
                  </Link>
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        <div className="text-center">
          <p className="text-xs text-[var(--color-text-muted)]">
            {t('auth.login.terms', { defaultValue: 'By signing in, you agree to our Terms of Service and Privacy Policy' })}
          </p>
        </div>
      </div>
    </div>
  );
};

export default LoginPage;