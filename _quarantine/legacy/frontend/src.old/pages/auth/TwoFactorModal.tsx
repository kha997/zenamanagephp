import React, { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../../shared/ui/card';
import { Button } from '../../shared/ui/button';
import { Badge } from '../../shared/ui/badge';
import { useI18n } from '../../app/i18n-context';
import { authApi } from '../../shared/auth/api';

const twoFactorSchema = z.object({
  code: z.string().length(6, 'Code must be 6 digits'),
});

type TwoFactorFormData = z.infer<typeof twoFactorSchema>;

interface TwoFactorModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

const TwoFactorModal: React.FC<TwoFactorModalProps> = ({
  isOpen,
  onClose,
  onSuccess,
}) => {
  const { t } = useI18n();
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [resendCooldown, setResendCooldown] = useState(0);

  const {
    register,
    handleSubmit,
    formState: { errors },
    reset,
  } = useForm<TwoFactorFormData>({
    resolver: zodResolver(twoFactorSchema),
    defaultValues: {
      code: '',
    },
  });

  // Resend cooldown timer
  useEffect(() => {
    if (resendCooldown > 0) {
      const timer = setTimeout(() => setResendCooldown(resendCooldown - 1), 1000);
      return () => clearTimeout(timer);
    }
  }, [resendCooldown]);

  const onSubmit = async (data: TwoFactorFormData) => {
    setIsLoading(true);
    setError(null);

    try {
      await authApi.verifyTwoFactor(data);
      onSuccess();
      onClose();
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Invalid verification code';
      setError(errorMessage);
    } finally {
      setIsLoading(false);
    }
  };

  const handleResend = async () => {
    try {
      await authApi.resendTwoFactor();
      setResendCooldown(60); // 60 seconds cooldown
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Failed to resend code';
      setError(errorMessage);
    }
  };

  const handleClose = () => {
    reset();
    setError(null);
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            🔐 {t('auth.twoFactor.title', { defaultValue: 'Two-Factor Authentication' })}
          </CardTitle>
          <CardDescription>
            {t('auth.twoFactor.description', { defaultValue: 'Enter the 6-digit code from your authenticator app' })}
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
              <label htmlFor="code" className="text-sm font-medium text-[var(--color-text-primary)]">
                {t('auth.twoFactor.code', { defaultValue: 'Verification Code' })}
              </label>
              <input
                {...register('code')}
                type="text"
                id="code"
                maxLength={6}
                className="w-full px-3 py-2 text-center text-lg font-mono border border-[var(--color-border-default)] rounded-[var(--radius-md)] focus:outline-none focus:ring-2 focus:ring-[var(--color-semantic-primary-500)] focus:border-transparent"
                placeholder="000000"
                autoComplete="one-time-code"
              />
              {errors.code && (
                <p className="text-sm text-[var(--color-semantic-danger-500)]">
                  {errors.code.message}
                </p>
              )}
            </div>

            <div className="text-center">
              <p className="text-xs text-[var(--color-text-muted)] mb-2">
                {t('auth.twoFactor.help', { defaultValue: 'Check your authenticator app for the current code' })}
              </p>
            </div>

            <div className="flex gap-2">
              <Button
                type="button"
                variant="outline"
                onClick={handleClose}
                className="flex-1"
              >
                {t('auth.twoFactor.cancel', { defaultValue: 'Cancel' })}
              </Button>
              <Button
                type="submit"
                loading={isLoading}
                disabled={isLoading}
                className="flex-1"
              >
                {t('auth.twoFactor.verify', { defaultValue: 'Verify' })}
              </Button>
            </div>

            <div className="text-center">
              <button
                type="button"
                onClick={handleResend}
                disabled={resendCooldown > 0}
                className="text-sm text-[var(--color-semantic-primary-500)] hover:text-[var(--color-semantic-primary-600)] disabled:text-[var(--color-text-muted)] disabled:cursor-not-allowed"
              >
                {resendCooldown > 0
                  ? t('auth.twoFactor.resendCooldown', { defaultValue: `Resend in ${resendCooldown}s` })
                  : t('auth.twoFactor.resend', { defaultValue: 'Resend Code' })
                }
              </button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
};

export default TwoFactorModal;
