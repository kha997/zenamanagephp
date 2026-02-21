import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';

interface ContractItem {
  id: string;
  code: string;
  status: string;
  currency: string | null;
  total_value: number | null;
  created_at: string;
}

interface ContractsPagination {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
}

interface ContractsResponse {
  status: string;
  data: {
    items: ContractItem[];
    pagination: ContractsPagination;
  };
  message?: string;
}

export default function ContractsListPage() {
  const { projectId } = useParams<{ projectId: string }>();
  const [page, setPage] = useState<number>(1);
  const perPage = 15;

  const { data, isLoading, isError, error, refetch, isFetching } = useQuery<ContractsResponse>({
    queryKey: ['contracts', projectId, page, perPage],
    enabled: Boolean(projectId),
    queryFn: async () => {
      const response = await apiClient.get(`/api/v1/projects/${projectId}/contracts`, {
        params: {
          page,
          per_page: perPage,
        },
      });

      return response.data as ContractsResponse;
    },
  });

  const items = data?.data?.items ?? [];
  const pagination = data?.data?.pagination;
  const canGoPrev = page > 1;
  const canGoNext = Boolean(pagination && page < pagination.last_page);

  const formatCurrency = (value: number | null, currency: string | null): string => {
    const amount = typeof value === 'number' ? value : 0;

    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency: currency || 'USD',
    }).format(amount);
  };

  const formatDate = (value: string): string => {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return '-';
    }

    return date.toLocaleDateString();
  };

  if (!projectId) {
    return (
      <div className="p-6">
        <h1 className="text-2xl font-bold text-gray-900">Contracts</h1>
        <p className="mt-2 text-sm text-red-600">Missing project id.</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Contracts</h1>
          <p className="mt-1 text-sm text-gray-600">Project ID: {projectId}</p>
        </div>
        <Link
          to="/projects"
          className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
        >
          Back to Projects
        </Link>
      </div>

      {isLoading || isFetching ? (
        <div className="rounded-lg border border-gray-200 bg-white p-6">
          <p className="text-sm text-gray-600">Loading contracts...</p>
        </div>
      ) : null}

      {isError ? (
        <div className="rounded-lg border border-red-200 bg-red-50 p-6">
          <p className="text-sm text-red-700">
            Failed to load contracts.
            {error instanceof Error ? ` ${error.message}` : ''}
          </p>
          <button
            type="button"
            onClick={() => void refetch()}
            className="mt-3 rounded-md border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100"
          >
            Retry
          </button>
        </div>
      ) : null}

      {!isLoading && !isError ? (
        <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th scope="col" className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                    Code
                  </th>
                  <th scope="col" className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                    Status
                  </th>
                  <th scope="col" className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                    Currency
                  </th>
                  <th scope="col" className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                    Total Value
                  </th>
                  <th scope="col" className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                    Created At
                  </th>
                  <th scope="col" className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200 bg-white">
                {items.length === 0 ? (
                  <tr>
                    <td className="px-4 py-8 text-sm text-gray-500" colSpan={6}>
                      No contracts found.
                    </td>
                  </tr>
                ) : (
                  items.map((contract) => (
                    <tr key={contract.id}>
                      <td className="px-4 py-3 text-sm font-medium text-gray-900">{contract.code || '-'}</td>
                      <td className="px-4 py-3 text-sm text-gray-700">{contract.status || '-'}</td>
                      <td className="px-4 py-3 text-sm text-gray-700">{contract.currency || 'USD'}</td>
                      <td className="px-4 py-3 text-sm text-gray-700">
                        {formatCurrency(contract.total_value, contract.currency)}
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-700">{formatDate(contract.created_at)}</td>
                      <td className="px-4 py-3 text-sm">
                        <Link
                          to={`/app/projects/${projectId}/contracts/${contract.id}`}
                          className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                          aria-label={`View contract ${contract.code || contract.id}`}
                        >
                          View
                        </Link>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {pagination && pagination.total > pagination.per_page ? (
            <div className="flex items-center justify-between border-t border-gray-200 px-4 py-3">
              <p className="text-sm text-gray-600">
                Page {pagination.page} of {pagination.last_page} ({pagination.total} total)
              </p>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setPage((previous) => Math.max(1, previous - 1))}
                  disabled={!canGoPrev}
                  className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                  aria-label="Go to previous contracts page"
                >
                  Previous
                </button>
                <button
                  type="button"
                  onClick={() => setPage((previous) => previous + 1)}
                  disabled={!canGoNext}
                  className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                  aria-label="Go to next contracts page"
                >
                  Next
                </button>
              </div>
            </div>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
