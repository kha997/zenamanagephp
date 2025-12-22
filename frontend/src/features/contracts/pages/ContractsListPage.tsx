import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { AccessRestricted } from '../../../components/shared/AccessRestricted';
import { useAuthStore } from '../../auth/store';
import { useContractsList } from '../hooks';
import { contractsApi } from '../api';
import { LoadingSpinner } from '../../../components/shared/LoadingSpinner';
import { Input } from '../../../components/ui/primitives/Input';
import { Select } from '../../../components/ui/primitives/Select';
import { Button } from '../../../components/ui/primitives/Button';
import type { ContractFilters } from '../types';
import toast from 'react-hot-toast';

/**
 * ContractsListPage - Danh sách hợp đồng
 * 
 * Round 39: React UI cho Contracts & Payment Schedule
 * Round 41: Added filter/search/sort with URL sync
 * 
 * Features:
 * - Hiển thị danh sách hợp đồng với table
 * - RBAC: tenant.view_contracts để xem
 * - Click row để đi tới chi tiết
 * - Loading, empty, error states
 * - Filter/search/sort với URL sync
 */
export const ContractsListPage: React.FC = () => {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const { hasTenantPermission } = useAuthStore();
  
  const canView = hasTenantPermission('tenant.view_contracts');
  const canManage = hasTenantPermission('tenant.manage_contracts');
  
  // Filters state - sync with URL
  const [filters, setFilters] = useState<ContractFilters>(() => {
    const search = searchParams.get('search') || '';
    const status = searchParams.get('status') || '';
    const sort_by = searchParams.get('sort_by') || '';
    const sort_direction = (searchParams.get('sort_direction') as 'asc' | 'desc') || 'desc';
    return {
      search,
      status,
      sort_by: sort_by || undefined,
      sort_direction: sort_direction || undefined,
    };
  });
  
  // Search input state (for debouncing)
  const [searchInput, setSearchInput] = useState(filters.search || '');
  
  // Sync filters state with URL params when they change
  useEffect(() => {
    const newFilters: ContractFilters = {
      search: searchParams.get('search') || '',
      status: searchParams.get('status') || '',
      sort_by: searchParams.get('sort_by') || undefined,
      sort_direction: (searchParams.get('sort_direction') as 'asc' | 'desc') || undefined,
    };
    setFilters(newFilters);
    setSearchInput(newFilters.search || '');
  }, [searchParams]);
  
  // Debounce search input
  useEffect(() => {
    const timer = setTimeout(() => {
      if (searchInput !== filters.search) {
        setFilters(prev => ({ ...prev, search: searchInput }));
        setSearchParams(prev => {
          const newParams = new URLSearchParams(prev);
          if (searchInput) {
            newParams.set('search', searchInput);
          } else {
            newParams.delete('search');
          }
          newParams.set('page', '1');
          return newParams;
        });
      }
    }, 300);
    
    return () => clearTimeout(timer);
  }, [searchInput, filters.search, setSearchParams]);
  
  // Fetch data with filters
  const { data: contractsData, isLoading, error } = useContractsList(filters, { page: 1, per_page: 50 });
  
  // Early return if user doesn't have view permission
  if (!canView) {
    return (
      <Container>
        <AccessRestricted
          title="Access Restricted"
          description="You don't have permission to view contracts. Please contact an administrator to request access."
        />
      </Container>
    );
  }
  
  const contracts = contractsData?.data || [];
  
  // Format currency
  const formatCurrency = (amount: number, currency: string = 'USD') => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount);
  };
  
  // Format date
  const formatDate = (dateString?: string) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('vi-VN');
  };
  
  // Status badge
  const getStatusBadge = (status: string) => {
    const statusConfig: Record<string, { label: string; className: string }> = {
      draft: { label: 'Draft', className: 'bg-gray-100 text-gray-800' },
      active: { label: 'Active', className: 'bg-blue-100 text-blue-800' },
      completed: { label: 'Completed', className: 'bg-green-100 text-green-800' },
      cancelled: { label: 'Cancelled', className: 'bg-red-100 text-red-800' },
    };
    
    const config = statusConfig[status] || statusConfig.draft;
    return (
      <span className={`px-2 py-1 text-xs font-medium rounded-full ${config.className}`}>
        {config.label}
      </span>
    );
  };
  
  // Filter options
  const statusOptions = useMemo(() => [
    { value: '', label: 'Tất cả trạng thái' },
    { value: 'draft', label: 'Draft' },
    { value: 'active', label: 'Active' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
  ], []);
  
  const sortOptions = useMemo(() => [
    { value: '', label: 'Mặc định (Ngày tạo mới nhất)' },
    { value: 'code_asc', label: 'Mã HĐ (A-Z)' },
    { value: 'code_desc', label: 'Mã HĐ (Z-A)' },
    { value: 'signed_at_desc', label: 'Ngày ký (Mới nhất)' },
    { value: 'signed_at_asc', label: 'Ngày ký (Cũ nhất)' },
    { value: 'total_value_desc', label: 'Giá trị (Cao → Thấp)' },
    { value: 'total_value_asc', label: 'Giá trị (Thấp → Cao)' },
  ], []);
  
  // Handle filter changes
  const handleFilterChange = useCallback((newFilters: ContractFilters) => {
    setFilters(newFilters);
    setSearchParams(prev => {
      const newParams = new URLSearchParams(prev);
      Object.entries(newFilters).forEach(([key, value]) => {
        if (value && value !== '') {
          newParams.set(key, String(value));
        } else {
          newParams.delete(key);
        }
      });
      newParams.set('page', '1');
      return newParams;
    });
  }, [setSearchParams]);
  
  // Handle status filter change
  const handleStatusChange = useCallback((status: string) => {
    handleFilterChange({ ...filters, status: status || undefined });
  }, [filters, handleFilterChange]);
  
  // Handle sort change
  const handleSortChange = useCallback((sortValue: string) => {
    if (!sortValue) {
      handleFilterChange({ ...filters, sort_by: undefined, sort_direction: undefined });
      return;
    }
    
    // Parse sort value (format: "field_direction")
    const [sort_by, sort_direction] = sortValue.split('_');
    handleFilterChange({
      ...filters,
      sort_by: sort_by || undefined,
      sort_direction: (sort_direction as 'asc' | 'desc') || undefined,
    });
  }, [filters, handleFilterChange]);
  
  // Get current sort value for select
  const currentSortValue = useMemo(() => {
    if (!filters.sort_by || !filters.sort_direction) return '';
    return `${filters.sort_by}_${filters.sort_direction}`;
  }, [filters.sort_by, filters.sort_direction]);
  
  // Reset filters
  const handleResetFilters = useCallback(() => {
    setSearchInput('');
    setFilters({});
    setSearchParams(new URLSearchParams());
  }, [setSearchParams]);

  // Handle export contracts
  const handleExportContracts = useCallback(async () => {
    try {
      const blob = await contractsApi.exportContracts(filters);
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `contracts_export_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
      toast.success('Đã xuất danh sách hợp đồng thành công');
    } catch (error: any) {
      toast.error(error?.message || 'Không thể xuất danh sách hợp đồng');
      console.error('Failed to export contracts:', error);
    }
  }, [filters]);
  
  // Check if any filters are active
  const hasActiveFilters = useMemo(() => {
    return !!(
      filters.search ||
      filters.status ||
      filters.sort_by
    );
  }, [filters]);
  
  if (isLoading) {
    return (
      <Container>
        <div className="space-y-6">
          <div>
            <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">Hợp đồng</h1>
          </div>
          <Card>
            <CardContent className="p-6">
              <div className="flex justify-center items-center py-12">
                <LoadingSpinner size="lg" message="Loading contracts..." />
              </div>
            </CardContent>
          </Card>
        </div>
      </Container>
    );
  }
  
  if (error) {
    return (
      <Container>
        <div className="space-y-6">
          <div>
            <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">Hợp đồng</h1>
          </div>
          <Card>
            <CardContent className="p-6">
              <div className="text-center py-12">
                <p className="text-red-600 mb-4">Error loading contracts</p>
                <p className="text-sm text-[var(--color-text-muted)]">
                  {error instanceof Error ? error.message : 'An unknown error occurred'}
                </p>
              </div>
            </CardContent>
          </Card>
        </div>
      </Container>
    );
  }
  
  return (
    <Container>
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">Hợp đồng</h1>
        </div>
        
        {/* Filter Bar */}
        <Card>
          <CardContent className="p-4">
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              {/* Search */}
              <div className="md:col-span-1">
                <Input
                  type="text"
                  placeholder="Tìm theo mã / tên hợp đồng"
                  value={searchInput}
                  onChange={(e) => setSearchInput(e.target.value)}
                  leadingIcon="🔍"
                />
              </div>
              
              {/* Status Filter */}
              <div className="md:col-span-1">
                <Select
                  options={statusOptions}
                  value={filters.status || ''}
                  onChange={(value) => handleStatusChange(value)}
                  placeholder="Trạng thái"
                />
              </div>
              
              {/* Sort */}
              <div className="md:col-span-1">
                <Select
                  options={sortOptions}
                  value={currentSortValue}
                  onChange={(value) => handleSortChange(value)}
                  placeholder="Sắp xếp"
                />
              </div>
              
              {/* Actions */}
              <div className="md:col-span-1 flex items-end gap-2">
                {hasActiveFilters && (
                  <Button
                    variant="secondary"
                    size="sm"
                    onClick={handleResetFilters}
                    className="flex-1"
                  >
                    Xóa bộ lọc
                  </Button>
                )}
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={handleExportContracts}
                  className={hasActiveFilters ? 'flex-1' : 'w-full'}
                >
                  Export CSV
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
        
        {contracts.length === 0 ? (
          <Card>
            <CardContent className="p-6">
              <div className="text-center py-12">
                <p className="text-[var(--color-text-muted)] mb-2">Chưa có hợp đồng</p>
                {canManage ? (
                  <p className="text-sm text-[var(--color-text-muted)]">
                    Hãy tạo hợp đồng từ backend (UI tạo hợp đồng sẽ được thêm sau)
                  </p>
                ) : (
                  <p className="text-sm text-[var(--color-text-muted)]">
                    Không có hợp đồng nào trong workspace này
                  </p>
                )}
              </div>
            </CardContent>
          </Card>
        ) : (
          <Card>
            <CardHeader>
              <CardTitle>Danh sách hợp đồng</CardTitle>
            </CardHeader>
            <CardContent className="p-0">
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead className="bg-[var(--color-surface-subtle)] border-b border-[var(--color-border-subtle)]">
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-medium text-[var(--color-text-secondary)] uppercase tracking-wider">
                        Code
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-[var(--color-text-secondary)] uppercase tracking-wider">
                        Tên HĐ
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-[var(--color-text-secondary)] uppercase tracking-wider">
                        Khách hàng
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-[var(--color-text-secondary)] uppercase tracking-wider">
                        Dự án
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-[var(--color-text-secondary)] uppercase tracking-wider">
                        Giá trị
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-[var(--color-text-secondary)] uppercase tracking-wider">
                        Trạng thái
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[var(--color-border-subtle)]">
                    {contracts.map((contract) => (
                      <tr
                        key={contract.id}
                        onClick={() => navigate(`/app/contracts/${contract.id}`)}
                        className="hover:bg-[var(--color-surface-hover)] cursor-pointer transition-colors"
                      >
                        <td className="px-4 py-3 whitespace-nowrap text-sm font-medium text-[var(--color-text-primary)]">
                          {contract.code}
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap text-sm text-[var(--color-text-primary)]">
                          {contract.name}
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap text-sm text-[var(--color-text-secondary)]">
                          {contract.client?.name || '-'}
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap text-sm text-[var(--color-text-secondary)]">
                          {contract.project?.name || '-'}
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap text-sm text-[var(--color-text-primary)]">
                          {formatCurrency(contract.total_value, contract.currency)}
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap">
                          {getStatusBadge(contract.status)}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    </Container>
  );
};

