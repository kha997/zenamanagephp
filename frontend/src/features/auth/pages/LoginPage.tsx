import React, { useState, useEffect } from 'react';
import { useNavigate, useLocation, Link } from 'react-router-dom';
import { LoginForm } from '../components/LoginForm';
import { TenantSelector } from '../components/TenantSelector';
import { NoTenantScreen } from '../components/NoTenantScreen';
import { useAuthStore } from '../store';

/**
 * Default redirect path when no redirect_path is provided from API
 */
const DEFAULT_REDIRECT = '/app/dashboard';

export const LoginPage: React.FC = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const { isAuthenticated, user, selectTenant, tenantsCount } = useAuthStore();
  const [showTenantSelector, setShowTenantSelector] = useState(false);
  const [showNoTenantScreen, setShowNoTenantScreen] = useState(false);
  const [isCheckingTenants, setIsCheckingTenants] = useState(false);
  const [publicSignupEnabled, setPublicSignupEnabled] = useState(false);

  // Check if public signup is enabled
  React.useEffect(() => {
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
        }
      } catch (error) {
        console.error('Error checking signup status:', error);
        // Default to disabled on error
        setPublicSignupEnabled(false);
      }
    };

    checkSignupEnabled();
  }, []);

  // Redirect if already authenticated
  React.useEffect(() => {
    if (isAuthenticated && user) {
      // Check if user needs to select tenant
      checkTenantsAndRedirect();
    }
  }, [isAuthenticated, user, navigate, location]);

  const checkTenantsAndRedirect = async () => {
    if (!user) return;
    
    setIsCheckingTenants(true);
    try {
      // Use canonical /api/v1/me to get user data including tenants_summary
      const response = await fetch('/api/v1/me', {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': typeof window !== 'undefined' 
            ? (window.Laravel?.csrfToken ?? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '')
            : '',
        },
        credentials: 'include',
      });

      if (response.ok) {
        const data = await response.json();
        const meData = data.data || data;
        const tenantsCount = meData.tenants_summary?.count || 0;
        const tenants = meData.tenants_summary?.items || [];
        
        if (tenantsCount === 0) {
          // No tenants - show NoTenantScreen
          setShowNoTenantScreen(true);
          setIsCheckingTenants(false);
          return;
        } else if (tenantsCount === 1) {
          // Only one tenant - auto-select and redirect
          const tenantId = tenants[0].id;
          try {
            await selectTenant(tenantId);
            const from = (location.state as any)?.from?.pathname || DEFAULT_REDIRECT;
            navigate(from, { replace: true });
          } catch (error) {
            console.error('Error selecting tenant:', error);
            // Still redirect even if tenant selection fails
            const from = (location.state as any)?.from?.pathname || DEFAULT_REDIRECT;
            navigate(from, { replace: true });
          }
        } else {
          // Multiple tenants - show selector
          setShowTenantSelector(true);
        }
      } else {
        // API error - check if user has tenant_id, otherwise show no tenant screen
        if (!user.tenant_id) {
          setShowNoTenantScreen(true);
          setIsCheckingTenants(false);
          return;
        }
        // User has tenant_id, just redirect
        const from = (location.state as any)?.from?.pathname || DEFAULT_REDIRECT;
        navigate(from, { replace: true });
      }
    } catch (error) {
      console.error('Error checking tenants:', error);
      // On error, check if user has tenant_id
      if (!user.tenant_id) {
        setShowNoTenantScreen(true);
        setIsCheckingTenants(false);
        return;
      }
      // User has tenant_id, just redirect
      const from = (location.state as any)?.from?.pathname || DEFAULT_REDIRECT;
      navigate(from, { replace: true });
    } finally {
      setIsCheckingTenants(false);
    }
  };

  const handleSuccess = async (redirectPath?: string) => {
    // After login, check tenants
    await checkTenantsAndRedirect();
  };

  const handleTenantSelect = async (tenantId: string) => {
    try {
      await selectTenant(tenantId);
      setShowTenantSelector(false);
      // Priority: location.state.from > API redirect_path > DEFAULT_REDIRECT
      const from = (location.state as any)?.from?.pathname || 
                   redirectPath || 
                   DEFAULT_REDIRECT;
      navigate(from, { replace: true });
    } catch (error) {
      console.error('Error selecting tenant:', error);
      // Still redirect even if tenant selection fails
      const from = (location.state as any)?.from?.pathname || 
                   redirectPath || 
                   DEFAULT_REDIRECT;
      navigate(from, { replace: true });
    }
  };

  if (showNoTenantScreen) {
    return <NoTenantScreen />;
  }

  if (showTenantSelector) {
    return (
      <>
        <TenantSelector
          onSelect={handleTenantSelect}
          onCancel={() => {
            setShowTenantSelector(false);
            // Still redirect even if cancelled
            const from = (location.state as any)?.from?.pathname || DEFAULT_REDIRECT;
            navigate(from, { replace: true });
          }}
        />
      </>
    );
  }

  if (isCheckingTenants) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--bg)] px-4 py-12 sm:px-6 lg:px-8">
        <div className="text-center">
          <div className="mb-4 inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-blue-600 border-r-transparent"></div>
          <p className="text-[var(--text)]">Đang kiểm tra tenant...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-[var(--bg)] px-4 py-12 sm:px-6 lg:px-8">
      <div className="w-full max-w-md space-y-8">
        <div>
          <h2 className="mt-6 text-center text-3xl font-bold tracking-tight text-[var(--text)]">
            Đăng nhập vào ZenaManage
          </h2>
          <p className="mt-2 text-center text-sm text-[var(--muted)]">
            Vui lòng đăng nhập để tiếp tục
          </p>
        </div>
        <div className="mt-8 rounded-lg bg-[var(--surface)] px-6 py-8 shadow">
          <LoginForm onSuccess={handleSuccess} />
        </div>
        {publicSignupEnabled && (
          <div className="mt-6 text-center">
            <p className="text-sm text-[var(--muted)]">
              Chưa có tài khoản?{' '}
              <Link
                to="/register"
                className="font-medium text-[var(--accent)] hover:text-[var(--accent-hover)]"
              >
                Đăng ký ngay
              </Link>
            </p>
          </div>
        )}
      </div>
    </div>
  );
};

export default LoginPage;

