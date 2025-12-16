import React, { useState, useEffect } from 'react';
import { Button } from '../../../shared/ui/button';
import { useAuthStore } from '../store';

export interface Tenant {
  id: string;
  name: string;
  slug?: string | null;
  is_active: boolean;
  is_current?: boolean;
}

interface TenantSelectorProps {
  onSelect: (tenantId: string) => void;
  onCancel?: () => void;
}

/**
 * TenantSelector - Modal component for selecting a tenant
 * 
 * Displays a list of available tenants and allows user to select one.
 * If only one tenant is available, it can auto-select.
 */
export const TenantSelector: React.FC<TenantSelectorProps> = ({ onSelect, onCancel }) => {
  const { selectTenant, tenantsCount } = useAuthStore();
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedTenantId, setSelectedTenantId] = useState<string | null>(null);

  useEffect(() => {
    const fetchTenants = async () => {
      try {
        setIsLoading(true);
        setError(null);
        
        // Call API to get user's tenants
        const response = await fetch('/api/v1/me/tenants', {
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

        if (!response.ok) {
          throw new Error('Failed to fetch tenants');
        }

        const data = await response.json();
        
        // Handle standardized ApiResponse format: { success: true, data: { tenants: [...], count: int } }
        // or legacy format: { ok: true, data: { tenants: [...] } }
        const tenantsData = data.data || data;
        const tenantList = (tenantsData.tenants || []) as Tenant[];
        
        if (tenantList.length > 0) {
          setTenants(tenantList);
          
          // Auto-select if only one tenant
          if (tenantList.length === 1) {
            setSelectedTenantId(tenantList[0].id);
            // Auto-select after a short delay
            setTimeout(() => {
              onSelect(tenantList[0].id);
            }, 500);
          } else {
            // Pre-select current tenant if available
            const currentTenant = tenantList.find(t => t.is_current);
            if (currentTenant) {
              setSelectedTenantId(currentTenant.id);
            } else {
              setSelectedTenantId(tenantList[0].id);
            }
          }
        } else {
          // No tenants found
          setTenants([]);
        }
      } catch (err: any) {
        console.error('Error fetching tenants:', err);
        setError(err.message || 'Không thể tải danh sách tenant');
      } finally {
        setIsLoading(false);
      }
    };

    fetchTenants();
  }, [onSelect]);

  const handleSelect = async () => {
    if (!selectedTenantId) return;

    try {
      setError(null);
      
      // Use store's selectTenant method which handles API call and state update
      await selectTenant(selectedTenantId);
      
      // Call onSelect callback after successful selection
      onSelect(selectedTenantId);
    } catch (err: any) {
      console.error('Error selecting tenant:', err);
      setError(err.message || 'Không thể chọn tenant');
    }
  };

  if (isLoading) {
    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div className="rounded-lg bg-white p-6 shadow-xl">
          <div className="text-center">
            <div className="mb-4 inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-blue-600 border-r-transparent"></div>
            <p className="text-gray-700">Đang tải danh sách tenant...</p>
          </div>
        </div>
      </div>
    );
  }

  if (error && tenants.length === 0) {
    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div className="rounded-lg bg-white p-6 shadow-xl max-w-md w-full mx-4">
          <h3 className="text-lg font-semibold text-gray-900 mb-2">Lỗi</h3>
          <p className="text-red-600 mb-4">{error}</p>
          {onCancel && (
            <Button onClick={onCancel} className="w-full">
              Đóng
            </Button>
          )}
        </div>
      </div>
    );
  }

  if (tenants.length === 0) {
    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div className="rounded-lg bg-white p-6 shadow-xl max-w-md w-full mx-4">
          <h3 className="text-lg font-semibold text-gray-900 mb-2">Không có tenant</h3>
          <p className="text-gray-600 mb-4">Bạn chưa được gán vào bất kỳ tenant nào.</p>
          {onCancel && (
            <Button onClick={onCancel} className="w-full">
              Đóng
            </Button>
          )}
        </div>
      </div>
    );
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="rounded-lg bg-white p-6 shadow-xl max-w-md w-full mx-4">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">
          Chọn Tenant
        </h3>
        
        {error && (
          <div className="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-800">
            {error}
          </div>
        )}

        <div className="space-y-2 mb-4">
          {tenants.map((tenant) => (
            <label
              key={tenant.id}
              className={`flex cursor-pointer items-center rounded-lg border-2 p-4 transition-colors ${
                selectedTenantId === tenant.id
                  ? 'border-blue-600 bg-blue-50'
                  : 'border-gray-200 hover:border-gray-300'
              }`}
            >
              <input
                type="radio"
                name="tenant"
                value={tenant.id}
                checked={selectedTenantId === tenant.id}
                onChange={(e) => setSelectedTenantId(e.target.value)}
                className="mr-3 h-4 w-4 text-blue-600 focus:ring-blue-500"
              />
              <div className="flex-1">
                <div className="font-medium text-gray-900">{tenant.name}</div>
                {tenant.slug && (
                  <div className="text-sm text-gray-500">{tenant.slug}</div>
                )}
                {tenant.is_current && (
                  <span className="mt-1 inline-block rounded bg-green-100 px-2 py-0.5 text-xs text-green-800">
                    Hiện tại
                  </span>
                )}
              </div>
            </label>
          ))}
        </div>

        <div className="flex gap-3">
          {onCancel && (
            <Button
              onClick={onCancel}
              variant="outline"
              className="flex-1"
            >
              Hủy
            </Button>
          )}
          <Button
            onClick={handleSelect}
            disabled={!selectedTenantId}
            className="flex-1"
          >
            Chọn
          </Button>
        </div>
      </div>
    </div>
  );
};

