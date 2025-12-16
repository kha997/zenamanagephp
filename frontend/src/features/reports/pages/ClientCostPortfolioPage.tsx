import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { AccessRestricted } from '../../../components/shared/AccessRestricted';
import { useAuthStore } from '../../auth/store';
import { useClientCostPortfolio, useExportClientCostPortfolio } from '../hooks';
import { LoadingSpinner } from '../../../components/shared/LoadingSpinner';
import { Input } from '../../../components/ui/primitives/Input';
import { Select } from '../../../components/ui/primitives/Select';
import { Button } from '../../../components/ui/primitives/Button';
import { TopOverrunClientsChart } from '../components/TopOverrunClientsChart';
import { MoneyCell } from '../components/MoneyCell';
import {
  parsePageParam,
  parseNumberParam,
  parseSortParams,
  setParam,
} from '../utils/reportFiltersUtils';
import { summarizeMoneyField } from '../utils/chartUtils';
import toast from 'react-hot-toast';

/**
 * ClientCostPortfolioPage - Full-page view for client cost portfolio
 * 
 * Round 53: Client Cost Portfolio
 * 
 * Features:
 * - Filterable, sortable table of clients with aggregated cost metrics
 * - Pagination
 * - RBAC: tenant.view_reports permission required
 */
export const ClientCostPortfolioPage: React.FC = () => {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const { hasTenantPermission } = useAuthStore();
  
  const canView = hasTenantPermission('tenant.view_reports');
  
  // Filters state - sync with URL
  const [filters, setFilters] = useState(() => {
    return {
      search: searchParams.get('search') || '',
      client_id: searchParams.get('client_id') || '',
      status: searchParams.get('status') || '',
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
      sort_by: 'overrun_amount_total',
      sort_direction: 'desc',
    }) as {
      sort_by: 'client_name' | 'contracts_value_total' | 'overrun_amount_total' | 'contracts_count';
      sort_direction: 'asc' | 'desc';
    };
  });
  
  // Search input state (for debouncing)
  const [searchInput, setSearchInput] = useState(filters.search || '');
  
  // Sync filters state with URL params when they change
  useEffect(() => {
    setFilters({
      search: searchParams.get('search') || '',
      client_id: searchParams.get('client_id') || '',
      status: searchParams.get('status') || '',
      min_overrun_amount: searchParams.get('min_overrun_amount') || '',
    });
    setPage(parsePageParam(searchParams, 'page', 1));
    setSort(
      parseSortParams(searchParams, {
        sort_by: 'overrun_amount_total',
        sort_direction: 'desc',
      }) as {
        sort_by: 'client_name' | 'contracts_value_total' | 'overrun_amount_total' | 'contracts_count';
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
  const { data, isLoading, error } = useClientCostPortfolio(
    {
      search: filters.search || undefined,
      client_id: filters.client_id || undefined,
      status: filters.status || undefined,
      min_overrun_amount: parseNumberParam(searchParams, 'min_overrun_amount'),
    },
    { page, per_page: perPage },
    sort
  );
  
  // Export mutation
  const exportMutation = useExportClientCostPortfolio();
  
  // Early return if user doesn't have view permission
  if (!canView) {
    return (
      <Container>
        <AccessRestricted
          title="Access Restricted"
          description="You don't have permission to view client cost portfolio reports. Please contact an administrator to request access."
        />
      </Container>
    );
  }
  
  const items = data?.data?.items || [];
  const pagination = data?.data?.pagination;
  
  // Compute summary for current page
  const summaryProjectsCount = items.reduce((sum, item) => sum + (item.projects_count || 0), 0);
  const summaryContractsCount = items.reduce((sum, item) => sum + (item.contracts_count || 0), 0);
  const summaryContractsValueTotal = summarizeMoneyField(items, 'contracts_value_total', 'currency');
  const summaryBudgetTotal = summarizeMoneyField(items, 'budget_total', 'currency');
  const summaryActualTotal = summarizeMoneyField(items, 'actual_total', 'currency');
  const summaryOverrunAmountTotal = summarizeMoneyField(items, 'overrun_amount_total', 'currency');
  
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
  
  // Check if any filters are active
  const hasActiveFilters = useMemo(() => {
    return !!(
      filters.search ||
      filters.client_id ||
      filters.status ||
      filters.min_overrun_amount
    );
  }, [filters]);
  
  // Reset filters
  const handleResetFilters = useCallback(() => {
    setSearchInput('');
    setFilters({ search: '', client_id: '', status: '', min_overrun_amount: '' });
    setPage(1);
    setSort({ sort_by: 'overrun_amount_total', sort_direction: 'desc' });
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
  
  // Handle export
  const handleExport = useCallback(async () => {
    try {
      const blob = await exportMutation.mutateAsync({
        search: filters.search || undefined,
        client_id: filters.client_id || undefined,
        status: filters.status || undefined,
        min_overrun_amount: parseNumberParam(searchParams, 'min_overrun_amount'),
        sort_by: sort.sort_by,
        sort_direction: sort.sort_direction,
      });
      
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `client-cost-portfolio-export-${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
      toast.success('Đã xuất báo cáo chi phí theo khách hàng thành công');
    } catch (error: any) {
      toast.error(error?.message || 'Không thể xuất báo cáo');
      console.error('Failed to export client cost portfolio:', error);
    }
  }, [filters, sort, searchParams, exportMutation]);
  
  return (
    <Container>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
              Chi phí theo khách hàng
            </h1>
            <p className="text-sm text-[var(--color-text-muted)] mt-1">
              Tổng hợp chi phí theo khách hàng (hợp đồng, budget, actual, overruns)
            </p>
          </div>
          <Button
            variant="outline"
            size="sm"
            onClick={handleExport}
            disabled={exportMutation.isPending || isLoading}
          >
            {exportMutation.isPending ? 'Đang xuất...' : 'Xuất CSV'}
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
                  placeholder="Tìm theo tên khách hàng"
                  value={searchInput}
                  onChange={(e) => setSearchInput(e.target.value)}
                />
              </div>
              
              <div>
                <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
                  Trạng thái HĐ
                </label>
                <Select
                  value={filters.status}
                  onChange={(e) => handleFilterChange({ status: e.target.value })}
                >
                  <option value="">Tất cả</option>
                  <option value="active">Active</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                  <option value="draft">Draft</option>
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

        {/* Chart */}
        {!isLoading && !error && items.length > 0 && (
          <div className="mb-6">
            <TopOverrunClientsChart items={items} maxItems={5} />
          </div>
        )}
        
        {/* Table */}
        <Card>
          <CardHeader>
            <CardTitle>Danh sách khách hàng</CardTitle>
          </CardHeader>
          <CardContent>
            {isLoading ? (
              <div className="flex justify-center py-8">
                <LoadingSpinner />
              </div>
            ) : error ? (
              <div className="text-center py-8">
                <p className="text-[var(--color-semantic-danger-600)]">
                  Không tải được danh sách khách hàng
                </p>
                <Button variant="secondary" size="sm" onClick={() => window.location.reload()} className="mt-4">
                  Thử lại
                </Button>
              </div>
            ) : items.length === 0 ? (
              <div className="text-center py-8">
                <p className="text-[var(--color-text-muted)]">
                  Hiện chưa có dữ liệu chi phí theo khách hàng.
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
                            onClick={() => handleSortChange('client_name', sort.sort_by === 'client_name' && sort.sort_direction === 'desc' ? 'asc' : 'desc')}
                            className="hover:underline"
                          >
                            Tên khách hàng {sort.sort_by === 'client_name' && (sort.sort_direction === 'desc' ? '↓' : '↑')}
                          </button>
                        </th>
                        <th className="text-right p-3 text-sm font-medium text-[var(--color-text-primary)]">
                          <button
                            onClick={() => handleSortChange('contracts_count', sort.sort_by === 'contracts_count' && sort.sort_direction === 'desc' ? 'asc' : 'desc')}
                            className="hover:underline"
                          >
                            Số HĐ {sort.sort_by === 'contracts_count' && (sort.sort_direction === 'desc' ? '↓' : '↑')}
                          </button>
                        </th>
                        <th className="text-right p-3 text-sm font-medium text-[var(--color-text-primary)]">Số dự án</th>
                        <th className="text-right p-3 text-sm font-medium text-[var(--color-text-primary)]">Tổng giá trị HĐ</th>
                        <th className="text-right p-3 text-sm font-medium text-[var(--color-text-primary)]">Budget Total</th>
                        <th className="text-right p-3 text-sm font-medium text-[var(--color-text-primary)]">Actual Total</th>
                        <th className="text-right p-3 text-sm font-medium text-[var(--color-text-primary)]">
                          <button
                            onClick={() => handleSortChange('overrun_amount_total', sort.sort_by === 'overrun_amount_total' && sort.sort_direction === 'desc' ? 'asc' : 'desc')}
                            className="hover:underline"
                          >
                            Overrun Total {sort.sort_by === 'overrun_amount_total' && (sort.sort_direction === 'desc' ? '↓' : '↑')}
                          </button>
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      {items.map((item) => (
                        <tr
                          key={item.client_id}
                          onClick={() => {
                            navigate(`/app/reports/projects-portfolio?client_id=${item.client_id}`);
                          }}
                          className="border-b border-[var(--color-border-subtle)] hover:bg-[var(--color-surface-subtle)] cursor-pointer transition-colors"
                        >
                          <td className="p-3 text-sm text-[var(--color-text-primary)] font-medium">
                            {item.client_name}
                          </td>
                          <td className="p-3 text-sm text-right text-[var(--color-text-primary)]">
                            {item.contracts_count}
                          </td>
                          <td className="p-3 text-sm text-right text-[var(--color-text-primary)]">
                            {item.projects_count}
                          </td>
                          <td className="p-3 text-sm text-right text-[var(--color-text-primary)]">
                            <MoneyCell
                              value={item.contracts_value_total}
                              currency={item.currency || 'USD'}
                              fallback="-"
                              tone="normal"
                            />
                          </td>
                          <td className="p-3 text-sm text-right text-[var(--color-text-primary)]">
                            <MoneyCell
                              value={item.budget_total}
                              currency={item.currency || 'USD'}
                              fallback="-"
                            />
                          </td>
                          <td className="p-3 text-sm text-right text-[var(--color-text-primary)]">
                            <MoneyCell
                              value={item.actual_total}
                              currency={item.currency || 'USD'}
                              fallback="-"
                            />
                          </td>
                          <td className="p-3 text-sm text-right" data-testid="overrun-total-cell">
                            <MoneyCell
                              value={item.overrun_amount_total}
                              currency={item.currency || 'USD'}
                              fallback="0"
                              showPlusWhenPositive={false}
                              tone={item.overrun_amount_total && item.overrun_amount_total > 0 ? 'danger' : 'muted'}
                            />
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
                          </td>
                          <td className="p-3 text-sm text-right text-[var(--color-text-primary)]" data-testid="summary-contracts-count">
                            {summaryContractsCount}
                          </td>
                          <td className="p-3 text-sm text-right text-[var(--color-text-primary)]" data-testid="summary-projects-count">
                            {summaryProjectsCount}
                          </td>
                          <td className="p-3 text-sm text-right" data-testid="summary-contracts-value">
                            <MoneyCell
                              value={summaryContractsValueTotal.total}
                              currency={summaryContractsValueTotal.currency || 'USD'}
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
                          <td className="p-3 text-sm text-right" data-testid="summary-actual">
                            <MoneyCell
                              value={summaryActualTotal.total}
                              currency={summaryActualTotal.currency || 'USD'}
                              fallback="-"
                              tone="normal"
                              showPlusWhenPositive={false}
                            />
                          </td>
                          <td className="p-3 text-sm text-right" data-testid="summary-overrun">
                            <MoneyCell
                              value={summaryOverrunAmountTotal.total}
                              currency={summaryOverrunAmountTotal.currency || 'USD'}
                              fallback="-"
                              tone="normal"
                              showPlusWhenPositive={false}
                            />
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

