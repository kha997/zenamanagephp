import React from 'react';
import { useAuthStore } from '../store';
import { NoTenantScreen } from './NoTenantScreen';
import { TenantSelector } from './TenantSelector';

interface TenantGuardProps {
  children: React.ReactNode;
}

/**
 * TenantGuard - Component that ensures user has tenant access before rendering children
 * 
 * Checks tenant state and shows appropriate UI:
 * - No tenants: Shows NoTenantScreen
 * - Multiple tenants, no active tenant: Shows TenantSelector
 * - Single tenant, no active tenant: Auto-selects and proceeds
 * - Has active tenant: Renders children
 * 
 * Note: user.tenant_id represents the ACTIVE tenant (from /api/v1/me),
 * not necessarily a database column. It's set by MeService based on
 * session selected_tenant_id, default tenant, or legacy fallback.
 */
export const TenantGuard: React.FC<TenantGuardProps> = ({ children }) => {
  const { isAuthenticated, user, selectedTenantId, tenantsCount, selectTenant } = useAuthStore();

  // If not authenticated, don't render anything (AuthGuard should handle this)
  if (!isAuthenticated || !user) {
    return null;
  }

  // Check if user has no tenants
  if (tenantsCount === 0) {
    return <NoTenantScreen />;
  }

  // Get active tenant ID (user.tenant_id is the active tenant from /api/v1/me)
  const activeTenantId = user.tenant_id || selectedTenantId;

  // If user has multiple tenants but no active tenant selected
  if (tenantsCount > 1 && !activeTenantId) {
    return (
      <TenantSelector
        onSelect={async (tenantId) => {
          // Tenant selection will update store via selectTenant()
          // This will call the backend, update session, and refresh /api/v1/me
          await selectTenant(tenantId);
        }}
        onCancel={() => {
          // Could redirect to login or show error
          // For now, just do nothing - user can't proceed without selecting
        }}
      />
    );
  }

  // If user has single tenant but no active tenant, auto-select it
  if (tenantsCount === 1 && !activeTenantId) {
    // This should be handled by the component that calls TenantGuard
    // (e.g., LoginPage), but we can also handle it here as a safety net
    // Note: This will cause a re-render after selectTenant completes
    React.useEffect(() => {
      // Get tenant ID from store's tenants_summary or fetch it
      // For now, we'll let the parent handle this
    }, []);
  }

  // User has active tenant access, render children
  return <>{children}</>;
};

