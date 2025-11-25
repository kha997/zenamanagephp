import React, { useState, useEffect, useRef } from 'react';
import { useQuery } from '@tanstack/react-query';
import axios from 'axios';
import { useAuthStore } from '../../auth/store';
import { useSwitchTenant } from '../hooks';
import toast from 'react-hot-toast';

/**
 * TenantSwitcher Component
 * 
 * Displays current workspace (tenant) and allows switching between available tenants.
 * 
 * Behavior:
 * - If user has 1 tenant: Shows label only (no dropdown)
 * - If user has multiple tenants: Shows dropdown with list of tenants
 * - If user has no tenants: Component doesn't render (TenantGuard handles this)
 * 
 * Integration:
 * - Uses useAuthStore to get current tenant and tenants count
 * - Fetches tenants list from /api/v1/me/tenants endpoint
 * - Uses useSwitchTenant hook to handle tenant switching
 * - Invalidates auth/me query after successful switch
 */
export const TenantSwitcher: React.FC = () => {
  const { user, tenantsCount } = useAuthStore();
  const { mutateAsync: switchTenant, isPending } = useSwitchTenant();
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  // Fetch tenants list from API
  const { data: tenantsData } = useQuery({
    queryKey: ['tenant', 'list'],
    queryFn: async () => {
      const response = await axios.get<{
        success?: boolean;
        data?: {
          tenants: Array<{
            id: string | number;
            name: string;
            slug?: string;
            is_active?: boolean;
            is_current?: boolean;
            is_default?: boolean;
            role?: string;
          }>;
          count: number;
          current_tenant_id: string | number | null;
        };
      }>('/api/v1/me/tenants', {
        withCredentials: true,
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': typeof window !== 'undefined' 
            ? (window.Laravel?.csrfToken ?? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content)
            : undefined,
          ...(typeof window !== 'undefined' && window.localStorage.getItem('auth_token') 
            ? { 'Authorization': `Bearer ${window.localStorage.getItem('auth_token')}` }
            : {}),
        },
      });
      return response.data?.data || { tenants: [], count: 0, current_tenant_id: null };
    },
    enabled: tenantsCount > 0,
    staleTime: 30 * 1000, // 30 seconds
  });

  const tenants = tenantsData?.tenants || [];
  const currentTenantId = tenantsData?.current_tenant_id || user?.tenant_id || null;

  // Get current tenant name
  const currentTenant = tenants.find((t) => t.id === currentTenantId) || tenants[0];
  const currentTenantName = currentTenant?.name || 'Workspace';

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => document.removeEventListener('mousedown', handleClickOutside);
    }
  }, [isOpen]);

  // Close dropdown on Escape key
  useEffect(() => {
    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && isOpen) {
        setIsOpen(false);
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [isOpen]);

  // Don't render if user has no tenants (TenantGuard handles this)
  if (tenantsCount === 0) {
    return null;
  }

  // If user has only 1 tenant, show simple label (no dropdown)
  if (tenantsCount === 1) {
    return (
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: 8,
          padding: '6px 12px',
          borderRadius: 8,
          background: 'transparent',
          color: 'var(--text)',
          fontSize: 14,
          fontWeight: 400,
        }}
        title="Workspace"
      >
        <span style={{ color: 'var(--muted)' }}>Workspace:</span>
        <span style={{ fontWeight: 500 }}>{currentTenantName}</span>
      </div>
    );
  }

  // Handle tenant switch
  const handleSwitchTenant = async (tenantId: string | number) => {
    try {
      await switchTenant(tenantId);
      setIsOpen(false);
      // Note: useSwitchTenant hook already invalidates ['auth', 'me'] query
      // Auth store will refresh via checkAuth() when needed
    } catch (error: any) {
      console.error('[TenantSwitcher] Switch tenant failed:', error);
      toast.error(error?.message || 'Failed to switch workspace. Please try again.');
    }
  };

  // Filter out current tenant from list
  const otherTenants = tenants.filter((t) => t.id !== currentTenantId);

  return (
    <div ref={dropdownRef} style={{ position: 'relative' }}>
      <button
        type="button"
        aria-label="Switch workspace"
        aria-expanded={isOpen}
        onClick={() => !isPending && setIsOpen(!isOpen)}
        disabled={isPending}
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: 8,
          padding: '6px 12px',
          borderRadius: 8,
          border: '1px solid var(--border)',
          background: isOpen ? 'var(--surface)' : 'transparent',
          color: 'var(--text)',
          fontSize: 14,
          fontWeight: 400,
          cursor: isPending ? 'not-allowed' : 'pointer',
          opacity: isPending ? 0.6 : 1,
          transition: 'all 150ms cubic-bezier(0.2, 0, 0, 1)',
          minWidth: 120,
        }}
        onMouseEnter={(e) => {
          if (!isPending) {
            e.currentTarget.style.borderColor = 'var(--accent)';
            e.currentTarget.style.background = 'var(--surface)';
          }
        }}
        onMouseLeave={(e) => {
          if (!isPending) {
            e.currentTarget.style.borderColor = 'var(--border)';
            e.currentTarget.style.background = isOpen ? 'var(--surface)' : 'transparent';
          }
        }}
        onFocus={(e) => {
          e.currentTarget.style.boxShadow = `0 0 0 2px var(--ring)`;
        }}
        onBlur={(e) => {
          e.currentTarget.style.boxShadow = 'none';
        }}
      >
        <span style={{ flex: 1, textAlign: 'left', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
          {isPending ? 'Switching...' : currentTenantName}
        </span>
        {!isPending && (
          <svg
            width="12"
            height="12"
            viewBox="0 0 12 12"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            style={{
              transform: isOpen ? 'rotate(180deg)' : 'rotate(0deg)',
              transition: 'transform 150ms',
              flexShrink: 0,
            }}
          >
            <path
              d="M3 4.5L6 7.5L9 4.5"
              stroke="currentColor"
              strokeWidth="1.5"
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </svg>
        )}
        {isPending && (
          <svg
            width="12"
            height="12"
            viewBox="0 0 12 12"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            style={{
              animation: 'spin 1s linear infinite',
              flexShrink: 0,
            }}
          >
            <circle
              cx="6"
              cy="6"
              r="5"
              stroke="currentColor"
              strokeWidth="1.5"
              strokeDasharray="8 8"
              strokeLinecap="round"
              fill="none"
            />
          </svg>
        )}
      </button>

      {/* Dropdown */}
      {isOpen && !isPending && (
        <>
          {/* Backdrop */}
          <div
            style={{
              position: 'fixed',
              top: 0,
              left: 0,
              right: 0,
              bottom: 0,
              zIndex: 40,
            }}
            onClick={() => setIsOpen(false)}
          />
          {/* Dropdown menu */}
          <div
            role="menu"
            aria-label="Workspace list"
            style={{
              position: 'absolute',
              top: 'calc(100% + 8px)',
              left: 0,
              minWidth: 200,
              maxWidth: 300,
              background: 'var(--surface)',
              border: '1px solid var(--border)',
              borderRadius: 12,
              boxShadow: '0 6px 20px rgba(0,0,0,0.08)',
              zIndex: 50,
              padding: 8,
              maxHeight: 300,
              overflowY: 'auto',
            }}
          >
            {/* Current tenant (highlighted) */}
            {currentTenant && (
              <div
                style={{
                  padding: '8px 12px',
                  fontSize: 14,
                  fontWeight: 500,
                  color: 'var(--text)',
                  background: 'var(--muted-surface)',
                  borderRadius: 8,
                  marginBottom: 4,
                }}
              >
                {currentTenant.name}
                <span
                  style={{
                    display: 'block',
                    fontSize: 12,
                    fontWeight: 400,
                    color: 'var(--muted)',
                    marginTop: 2,
                  }}
                >
                  Current workspace
                </span>
              </div>
            )}

            {/* Other tenants */}
            {otherTenants.length > 0 && (
              <>
                {otherTenants.map((tenant) => (
                  <button
                    key={tenant.id}
                    type="button"
                    role="menuitem"
                    onClick={() => handleSwitchTenant(tenant.id)}
                    style={{
                      width: '100%',
                      padding: '8px 12px',
                      textAlign: 'left',
                      background: 'transparent',
                      border: 'none',
                      color: 'var(--text)',
                      fontSize: 14,
                      fontWeight: 400,
                      cursor: 'pointer',
                      borderRadius: 8,
                      transition: 'background 150ms',
                    }}
                    onMouseEnter={(e) => {
                      e.currentTarget.style.background = 'var(--muted-surface)';
                    }}
                    onMouseLeave={(e) => {
                      e.currentTarget.style.background = 'transparent';
                    }}
                  >
                    {tenant.name}
                  </button>
                ))}
              </>
            )}
          </div>
        </>
      )}

      {/* Spinner animation */}
      <style>{`
        @keyframes spin {
          from { transform: rotate(0deg); }
          to { transform: rotate(360deg); }
        }
      `}</style>
    </div>
  );
};

export default TenantSwitcher;

