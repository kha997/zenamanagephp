import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { AccessRestricted } from '../../../components/shared/AccessRestricted';
import { useAuthStore } from '../../auth/store';
import { useContractCostOverrunsTable, useExportContractCostOverruns } from '../hooks';
import { LoadingSpinner } from '../../../components/shared/LoadingSpinner';
import { Input } from '../../../components/ui/primitives/Input';
import { Select } from '../../../components/ui/primitives/Select';
import { Button } from '../../../components/ui/primitives/Button';
import toast from 'react-hot-toast';
import { MoneyCell } from '../components/MoneyCell';
import {
  parsePageParam,
  parseNumberParam,
  parseSortParams,
  setParam,
} from '../utils/reportFiltersUtils';
import { summarizeMoneyField } from '../utils/chartUtils';

/**
 * ContractCostOverrunsPage - Full-page view for contract cost overruns
 * 
 * Round 49: Full-page Cost Overruns + CSV Export
 * 
 * Features:
 * - Filterable, sortable table of contracts with overruns
 * - Export to CSV
 * - Pagination
 * - RBAC: tenant.view_reports permission required
 */
export const ContractCostOverrunsPage: React.FC = () => {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const { hasTenantPermission } = useAuthStore();
  
  const canView = hasTenantPermission('tenant.view_reports');
  
  // Filters state - sync with URL
  const [filters, setFilters] = useState(() => {
    return {
      search: searchParams.get('search') || '',
      status: searchParams.get('status') || '',
      type: (searchParams.get('type') as 'budget' | 'actual' | 'both') || 'both',
      min_overrun_amount: searchParams.get('min_overrun_amount') || '',
    };
  });
  
  // Pagination state
  const [page, setPage] = useState(() => {
    return parsePageParam(searchParams, 'page', 1);
  });
  const [perPage] = useState(25);
  
  // Sort state
  const [sort, setSort] = useState(() => {
    return parseSortParams(searchParams, {
      sort_by: 'overrun_amount',
      sort_direction: 'desc',
    }) as {
      sort_by: 'code' | 'overrun_amount' | 'budget_vs_contract_diff';
      sort_direction: 'asc' | 'desc';
    };
  });
  
  // Search input state (for debouncing)
  const [searchInput, setSearchInput] = useState(filters.search || '');
  
  // Sync filters state with URL params when they change
  useEffect(() => {
    setFilters({
      search: searchParams.get('search') || '',
      status: searchParams.get('status') || '',
      type: (searchParams.get('type') as 'budget' | 'actual' | 'both') || 'both',
      min_overrun_amount: searchParams.get('min_overrun_amount') || '',
    });
    setPage(parsePageParam(searchParams, 'page', 1));
    setSort(
      parseSortParams(searchParams, {
        sort_by: 'overrun_amount',
        sort_direction: 'desc',
      }) as {
        sort_by: 'code' | 'overrun_amount' | 'budget_vs_contract_diff';
        sort_direction: 'asc' | 'desc';
      }
    );
    setSearchInput(searchParams.get('search') || '');
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
        setPage(1);
      }
    }, 300);
    
    return () => clearTimeout(timer);
  }, [searchInput, filters.search, setSearchParams]);
  
  // Fetch data
  const { data, isLoading, error } = useContractCostOverrunsTable(
    {
      search: filters.search || undefined,
      status: filters.status || undefined,
      type: filters.type,
      min_overrun_amount: parseNumberParam(searchParams, 'min_overrun_amount'),
    },
    { page, per_page: perPage },
    sort
  );
  
  // Export mutation
  const exportMutation = useExportContractCostOverruns();
  
  // Early return if user doesn't have view permission
  if (!canView) {
    return (
      <Container>
        <AccessRestricted
          title="Access Restricted"
          description="You don't have permission to view cost overruns reports. Please contact an administrator to request access."
        />
      </Container>
    );
  }
  
  const items = data?.data?.items || [];
  const pagination = data?.data?.pagination;
  
  // Compute summary for current page
  const summaryContractValue = summarizeMoneyField(items, 'contract_value', 'currency');
  const summaryBudgetTotal = summarizeMoneyField(items, 'budget_total', 'currency');
  const summaryActualTotal = summarizeMoneyField(items, 'actual_total', 'currency');
  const summaryOverrunAmount = summarizeMoneyField(items, 'overrun_amount', 'currency');
  
  // Count contracts with overrun > 0
  const contractsWithOverrun = items.filter(item => item.overrun_amount && item.overrun_amount > 0).length;
  
  // Handle filter changes
  const handleFilterChange = useCallback((newFilters: Partial<typeof filters>) => {
    setFilters(prev => ({ ...prev, ...newFilters }));
    setSearchParams(prev => {
      const newParams = new URLSearchParams(prev);
      Object.entries(newFilters).forEach(([key, value]) => {
        setParam(newParams, key, value as string | number | null | undefined);
      });
      setParam(newParams, 'page', 1);
      return newParams;
    });
    setPage(1);
  }, [setSearchParams]);
  
  // Handle sort change
  const handleSortChange = useCallback((sortBy: string, direction: 'asc' | 'desc') => {
    setSort({ sort_by: sortBy as any, sort_direction: direction });
    setSearchParams(prev => {
      const newParams = new URLSearchParams(prev);
      setParam(newParams, 'sort_by', sortBy);
      setParam(newParams, 'sort_direction', direction);
      return newParams;
    });
  }, [setSearchParams]);
  
  // Handle export
  const handleExport = useCallback(async () => {
    try {
      const blob = await exportMutation.mutateAsync({
        search: filters.search || undefined,
        status: filters.status || undefined,
        type: filters.type,
        min_overrun_amount: parseNumberParam(searchParams, 'min_overrun_amount'),
        sort_by: sort.sort_by,
        sort_direction: sort.sort_direction,
      });
      
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `cost-overruns-export-${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
      toast.success('Đã xuất báo cáo vượt budget/actual thành công');
    } catch (error: any) {
      toast.error(error?.message || 'Không thể xuất báo cáo');
      console.error('Failed to export cost overruns:', error);
    }
  }, [filters, sort, searchParams, exportMutation]);
  
  // Check if any filters are active
  const hasActiveFilters = useMemo(() => {
    return !!(
      filters.search ||
      filters.status ||
      filters.type !== 'both' ||
      filters.min_overrun_amount
    );
  }, [filters]);
  
  // Reset filters
  const handleResetFilters = useCallback(() => {
    setSearchInput('');
    setFilters({ search: '', status: '', type: 'both', min_overrun_amount: '' });
    setPage(1);
    setSort({ sort_by: 'overrun_amount', sort_direction: 'desc' });
    setSearchParams(new URLSearchParams());
  }, [setSearchParams]);
  
  // Handle page change
  const handlePageChange = useCallback((newPage: number) => {
    setPage(newPage);
    setSearchParams(prev => {
      const newParams = new URLSearchParams(prev);
      setParam(newParams, 'page', newPage);
      return newParams;
    });
  }, [setSearchParams]);
  
  return (
    <Container>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
              Hợp đồng vượt Budget / Actual
            </h1>
            <p className="text-sm text-[var(--color-text-muted)] mt-1">
              Danh sách hợp đồng vượt budget hoặc vượt actual cost
            </p>
          </div>
          <Button
            onClick={handleExport}
            disabled={exportMutation.isPending}
            variant="secondary"
          >
            {exportMutation.isPending ? 'Đang xuất...' : 'Export CSV'}
          </Button>
        </div>
        
        {/* Filters */}
        <Card>
          <CardContent className="pt-6">
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div>
                <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
                  Tìm kiếm
                </label>
                <Input
                  type="text"
                  placeholder="Tìm theo mã hoặc tên HĐ"
                  value={searchInput}
                  onChange={(e) => setSearchInput(e.target.value)}
                />
              </div>
              
              <div>
                <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
                  Trạng thái
                </label>
                <Select
                  value={filters.status}
                  onChange={(e) => handleFilterChange({ status: e.target.value })}
                >
                  <option value="">Tất cả</option>
                  <option value="active">Active</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                </Select>
              </div>
              
              <div>
                <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
                  Loại
                </label>
                <Select
                  value={filters.type}
                  onChange={(e) => handleFilterChange({ type: e.target.value as any })}
                >
                  <option value="both">Cả hai</option>
                  <option value="budget">Budget overruns</option>
                  <option value="actual">Actual overruns</option>
                </Select>
              </div>
              
              <div>
                <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
                  Min Overrun Amount
                </label>
                <Input
                  type="number"
                  placeholder="0"
                  value={filters.min_overrun_amount}
                  onChange={(e) => handleFilterChange({ min_overrun_amount: e.target.value })}
                />
              </div>
            </div>
            
            {hasActiveFilters && (
              <div className="mt-4">
                <Button variant="ghost" size="sm" onClick={handleResetFilters}>
                  Xóa bộ lọc
                </Button>
              </div>
            )}
          </CardContent>
        </Card>
        
        {/* Table */}
        <Card>
          <CardHeader>
            <CardTitle>Danh sách hợp đồng</CardTitle>
          </CardHeader>
          <CardContent>
            {isLoading ? (
              <div className="flex justify-center py-8">
                <LoadingSpinner />
              </div>
            ) : error ? (
              <div className="text-center py-8">
                <p className="text-[var(--color-semantic-danger-600)]">
                  Không tải được danh sách hợp đồng vượt budget/actual
                </p>
                <Button variant="secondary" size="sm" onClick={() => window.location.reload()} className="mt-4">
                  Thử lại
                </Button>
              </div>
            ) : items.length === 0 ? (
              <div className="text-center py-8">
                <p className="text-[var(--color-text-muted)]">
                  Hiện chưa có hợp đồng nào vượt Budget/Actual theo bộ lọc.
                </p>
              </div>
            ) : (
              <>
                <div className="overflow-x-auto">
                  <table className="w-full border-collapse">
                    <thead>
                      <tr className="border-b border-[var(--color-border-subtle)]">
                        <th className="text-left p-3 text-sm font-medium text-[var(--color-text-primary)]">
                          <button
                            onClick={() => handleSortChange('code', sort.sort_by === 'code' && sort.sort_direction === 'desc' ? 'asc' : 'desc')}
                            className="hover:underline"
                          >
                            Mã HĐ {sort.sort_by === 'code' && (sort.sort_direction === 'desc' ? '↓' : '↑')}
                          </button>
                        </th>
                        <th className="text-left p-3 text-sm font-medium text-[var(--color-text-primary)]">Tên</th>
                        <th className="text-left p-3 text-sm font-medium text-[var(--color-text-primary)]">Client</th>
                        <th className="text-left p-3 text-sm font-medium text-[var(--color-text-primary)]">Project</th>
                        <th className="text-left p-3 text-sm font-medium text-[var(--color-text-primary)]">Status</th>
                        <th className="text-right p-3 text-sm font-medium text-[var(--color-text-primary)]">Contract Value</th>
                        <th className="text-right p-3 text-sm font-medium text-[var(--color-text-primary)]">
                          <button
                            onClick={() => handleSortChange('budget_vs_contract_diff', sort.sort_direction === 'desc' ? 'asc' : 'desc')}
                            className="hover:underline"
                          >
                            Budget Total + Diff {sort.sort_by === 'budget_vs_contract_diff' && (sort.sort_direction === 'desc' ? '↓' : '↑')}
                          </button>
                        </th>
                        <th className="text-right p-3 text-sm font-medium text-[var(--color-text-primary)]">
                          <button
                            onClick={() => handleSortChange('overrun_amount', sort.sort_by === 'overrun_amount' && sort.sort_direction === 'desc' ? 'asc' : 'desc')}
                            className="hover:underline"
                          >
                            Actual Total + Overrun {sort.sort_by === 'overrun_amount' && (sort.sort_direction === 'desc' ? '↓' : '↑')}
                          </button>
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      {items.map((item) => (
                        <tr
                          key={item.id}
                          onClick={() => navigate(`/app/contracts/${item.id}`)}
                          className="border-b border-[var(--color-border-subtle)] hover:bg-[var(--color-surface-subtle)] cursor-pointer transition-colors"
                        >
                          <td className="p-3 text-sm text-[var(--color-text-primary)] font-medium">
                            {item.code}
                          </td>
                          <td className="p-3 text-sm text-[var(--color-text-primary)]">
                            {item.name}
                          </td>
                          <td className="p-3 text-sm text-[var(--color-text-muted)]">
                            {item.client?.name || '-'}
                          </td>
                          <td className="p-3 text-sm text-[var(--color-text-muted)]">
                            {item.project?.name || '-'}
                          </td>
                          <td className="p-3 text-sm">
                            <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                              item.status === 'active' ? 'bg-blue-100 text-blue-800' :
                              item.status === 'completed' ? 'bg-green-100 text-green-800' :
                              item.status === 'cancelled' ? 'bg-red-100 text-red-800' :
                              'bg-gray-100 text-gray-800'
                            }`}>
                              {item.status}
                            </span>
                          </td>
                          <td className="p-3 text-sm text-right text-[var(--color-text-primary)]">
                            <MoneyCell
                              value={item.contract_value}
                              currency={item.currency || 'USD'}
                              fallback="-"
                            />
                          </td>
                          <td className="p-3 text-sm text-right">
                            <div>
                              <div className="text-[var(--color-text-primary)]">
                                <MoneyCell
                                  value={item.budget_total}
                                  currency={item.currency || 'USD'}
                                  fallback="-"
                                />
                              </div>
                              {item.budget_vs_contract_diff > 0 && (
                                <div className="text-[var(--color-semantic-warning-600)] text-xs">
                                  <MoneyCell
                                    value={item.budget_vs_contract_diff}
                                    currency={item.currency || 'USD'}
                                    fallback="0"
                                    showPlusWhenPositive={true}
                                    tone="normal"
                                    className="text-[var(--color-semantic-warning-600)]"
                                  />
                                </div>
                              )}
                            </div>
                          </td>
                          <td className="p-3 text-sm text-right" data-testid="overrun-cell">
                            <div>
                              <div className="text-[var(--color-text-primary)]">
                                <MoneyCell
                                  value={item.actual_total}
                                  currency={item.currency || 'USD'}
                                  fallback="-"
                                />
                              </div>
                              {item.overrun_amount > 0 ? (
                                <div className="text-xs">
                                  <MoneyCell
                                    value={item.overrun_amount}
                                    currency={item.currency || 'USD'}
                                    fallback="0"
                                    showPlusWhenPositive={true}
                                    tone="danger"
                                  />
                                </div>
                              ) : (
                                <div className="text-xs">
                                  <MoneyCell
                                    value={0}
                                    currency={item.currency || 'USD'}
                                    fallback="0"
                                    tone="muted"
                                  />
                                </div>
                              )}
                            </div>
                          </td>
                        </tr>
                      ))}
                      {/* Summary row */}
                      {!isLoading && !error && items.length > 0 && (
                        <tr
                          data-testid="page-summary-row"
                          className="border-t-2 border-[var(--color-border-subtle)] bg-[var(--color-surface-subtle)] font-semibold"
                        >
                          <td className="p-3 text-sm text-[var(--color-text-primary)]">
                            Tổng (trang này)
                            {items.length > 0 && (
                              <span className="ml-2 text-xs font-normal text-[var(--color-text-muted)]">
                                ({items.length} hợp đồng{contractsWithOverrun > 0 ? `, ${contractsWithOverrun} vượt` : ''})
                              </span>
                            )}
                          </td>
                          <td className="p-3 text-sm text-[var(--color-text-primary)]"></td>
                          <td className="p-3 text-sm text-[var(--color-text-muted)]"></td>
                          <td className="p-3 text-sm text-[var(--color-text-muted)]"></td>
                          <td className="p-3 text-sm"></td>
                          <td className="p-3 text-sm text-right" data-testid="summary-contract-value">
                            <MoneyCell
                              value={summaryContractValue.total}
                              currency={summaryContractValue.currency || 'USD'}
                              fallback="-"
                              tone="normal"
                              showPlusWhenPositive={false}
                            />
                          </td>
                          <td className="p-3 text-sm text-right" data-testid="summary-budget">
                            <MoneyCell
                              value={summaryBudgetTotal.total}
                              currency={summaryBudgetTotal.currency || 'USD'}
                              fallback="-"
                              tone="normal"
                              showPlusWhenPositive={false}
                            />
                          </td>
                          <td className="p-3 text-sm text-right" data-testid="summary-actual-overrun">
                            <div>
                              <div className="text-[var(--color-text-primary)]">
                                <MoneyCell
                                  value={summaryActualTotal.total}
                                  currency={summaryActualTotal.currency || 'USD'}
                                  fallback="-"
                                  tone="normal"
                                  showPlusWhenPositive={false}
                                />
                              </div>
                              <div className="text-xs">
                                <MoneyCell
                                  value={summaryOverrunAmount.total}
                                  currency={summaryOverrunAmount.currency || 'USD'}
                                  fallback="-"
                                  tone="normal"
                                  showPlusWhenPositive={false}
                                />
                              </div>
                            </div>
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
                
                {/* Pagination */}
                {pagination && pagination.last_page > 1 && (
                  <div className="flex items-center justify-between mt-4 pt-4 border-t border-[var(--color-border-subtle)]">
                    <div className="text-sm text-[var(--color-text-muted)]">
                      Hiển thị {(pagination.current_page - 1) * pagination.per_page + 1} - {Math.min(pagination.current_page * pagination.per_page, pagination.total)} của {pagination.total}
                    </div>
                    <div className="flex gap-2">
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handlePageChange(page - 1)}
                        disabled={page === 1}
                      >
                        Trước
                      </Button>
                      {Array.from({ length: Math.min(5, pagination.last_page) }, (_, i) => {
                        const pageNum = Math.max(1, Math.min(pagination.last_page - 4, page - 2)) + i;
                        return (
                          <Button
                            key={pageNum}
                            variant={pageNum === page ? 'default' : 'ghost'}
                            size="sm"
                            onClick={() => handlePageChange(pageNum)}
                          >
                            {pageNum}
                          </Button>
                        );
                      })}
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handlePageChange(page + 1)}
                        disabled={page === pagination.last_page}
                      >
                        Sau
                      </Button>
                    </div>
                  </div>
                )}
              </>
            )}
          </CardContent>
        </Card>
      </div>
    </Container>
  );
};

