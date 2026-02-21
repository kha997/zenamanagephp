import { FormEvent, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';

type ContractStatus = 'draft' | 'active' | 'closed' | 'cancelled';

interface ContractRecord {
  id: string;
  code: string;
  title: string;
  status: ContractStatus;
  currency: string | null;
  total_value: number | null;
  created_at?: string;
  updated_at?: string;
}

interface ContractFormState {
  code: string;
  title: string;
  status: ContractStatus;
  currency: string;
  total_value: string;
}

const CONTRACT_STATUS_OPTIONS: Array<{ value: ContractStatus; label: string }> = [
  { value: 'draft', label: 'Draft' },
  { value: 'active', label: 'Active' },
  { value: 'closed', label: 'Closed' },
  { value: 'cancelled', label: 'Cancelled' },
];

function extractContract(payload: unknown): ContractRecord | null {
  const primary = payload as any;
  const contract = primary?.data?.data ?? primary?.data ?? primary;

  if (!contract || typeof contract !== 'object') {
    return null;
  }

  return contract as ContractRecord;
}

function isForbiddenOrNotFound(error: unknown): boolean {
  const status = (error as { response?: { status?: number } })?.response?.status;
  return status === 403 || status === 404;
}

function extractValidationErrors(error: unknown): Record<string, string[]> {
  const data = (error as { response?: { data?: { data?: unknown } } })?.response?.data?.data;
  if (!data || typeof data !== 'object' || Array.isArray(data)) {
    return {};
  }

  return data as Record<string, string[]>;
}

function toForm(contract: ContractRecord): ContractFormState {
  return {
    code: contract.code || '',
    title: contract.title || '',
    status: contract.status || 'draft',
    currency: contract.currency || 'USD',
    total_value: contract.total_value != null ? String(contract.total_value) : '',
  };
}

export default function ContractDetailPage() {
  const { projectId, contractId } = useParams<{ projectId: string; contractId: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [isEditing, setIsEditing] = useState(false);
  const [form, setForm] = useState<ContractFormState>({
    code: '',
    title: '',
    status: 'draft',
    currency: 'USD',
    total_value: '',
  });
  const [actionError, setActionError] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<Record<string, string[]>>({});

  const {
    data: detailResponse,
    isLoading,
    isError,
    error,
    refetch,
  } = useQuery({
    queryKey: ['contract', projectId, contractId],
    enabled: Boolean(projectId && contractId),
    queryFn: async () => {
      const response = await apiClient.get(`/api/v1/projects/${projectId}/contracts/${contractId}`);
      return response.data as unknown;
    },
  });

  const contract = useMemo(() => extractContract(detailResponse), [detailResponse]);

  const updateMutation = useMutation({
    mutationFn: async () => {
      if (!projectId || !contractId) {
        throw new Error('Missing project or contract id.');
      }

      const totalValue = form.total_value.trim() === '' ? null : Number(form.total_value);
      const payload = {
        code: form.code.trim().toUpperCase(),
        title: form.title.trim(),
        status: form.status,
        currency: form.currency.trim().toUpperCase(),
        total_value: totalValue,
      };

      const response = await apiClient.put(`/api/v1/projects/${projectId}/contracts/${contractId}`, payload);
      return response.data as unknown;
    },
    onSuccess: (raw) => {
      const updatedContract = extractContract(raw);
      if (!updatedContract) {
        setActionError('Contract updated but response payload was unexpected.');
        return;
      }

      queryClient.setQueryData(['contract', projectId, contractId], { data: updatedContract });
      queryClient.invalidateQueries({ queryKey: ['contracts', projectId] });
      setIsEditing(false);
      setValidationErrors({});
      setActionError(null);
    },
    onError: (mutationError: unknown) => {
      if (isForbiddenOrNotFound(mutationError)) {
        setActionError('Not found or no access.');
        setValidationErrors({});
        return;
      }

      const status = (mutationError as { response?: { status?: number } })?.response?.status;
      if (status === 422) {
        setValidationErrors(extractValidationErrors(mutationError));
        setActionError('Please correct the highlighted fields and try again.');
        return;
      }

      const message =
        (mutationError as { response?: { data?: { message?: string } } })?.response?.data?.message ||
        (mutationError as Error)?.message ||
        'Failed to update contract. Please try again.';

      setValidationErrors({});
      setActionError(message);
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async () => {
      if (!projectId || !contractId) {
        throw new Error('Missing project or contract id.');
      }

      await apiClient.delete(`/api/v1/projects/${projectId}/contracts/${contractId}`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['contracts', projectId] });
      void navigate(`/app/projects/${projectId}/contracts`);
    },
    onError: (mutationError: unknown) => {
      if (isForbiddenOrNotFound(mutationError)) {
        setActionError('Not found or no access.');
        return;
      }

      const message =
        (mutationError as { response?: { data?: { message?: string } } })?.response?.data?.message ||
        (mutationError as Error)?.message ||
        'Failed to delete contract. Please try again.';

      setActionError(message);
    },
  });

  const formatCurrency = (value: number | null, currency: string | null): string => {
    const amount = typeof value === 'number' ? value : 0;

    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency: currency || 'USD',
    }).format(amount);
  };

  const formatDate = (value?: string): string => {
    if (!value) {
      return '-';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return '-';
    }

    return date.toLocaleString();
  };

  const startEdit = () => {
    if (!contract) {
      return;
    }

    setForm(toForm(contract));
    setValidationErrors({});
    setActionError(null);
    setIsEditing(true);
  };

  const cancelEdit = () => {
    setIsEditing(false);
    setValidationErrors({});
    setActionError(null);
  };

  const onSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setActionError(null);
    setValidationErrors({});
    updateMutation.mutate();
  };

  const onDelete = () => {
    if (deleteMutation.isPending) {
      return;
    }

    const confirmed = window.confirm('Delete this contract? This action cannot be undone.');
    if (!confirmed) {
      return;
    }

    setActionError(null);
    deleteMutation.mutate();
  };

  if (!projectId || !contractId) {
    return (
      <div className="p-6">
        <h1 className="text-2xl font-bold text-gray-900">Contract Detail</h1>
        <p className="mt-2 text-sm text-red-600">Missing project or contract id.</p>
      </div>
    );
  }

  if (isLoading) {
    return (
      <div className="rounded-lg border border-gray-200 bg-white p-6">
        <p className="text-sm text-gray-600">Loading contract...</p>
      </div>
    );
  }

  if (isError || !contract) {
    const message = isForbiddenOrNotFound(error)
      ? 'Not found or no access.'
      : `Failed to load contract.${error instanceof Error ? ` ${error.message}` : ''}`;

    return (
      <div className="space-y-4">
        <div className="rounded-lg border border-red-200 bg-red-50 p-6">
          <p className="text-sm text-red-700">{message}</p>
          <button
            type="button"
            onClick={() => void refetch()}
            className="mt-3 rounded-md border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100"
            aria-label="Retry loading contract"
          >
            Retry
          </button>
        </div>
        <Link
          to={`/app/projects/${projectId}/contracts`}
          className="inline-flex rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          aria-label="Back to contracts list"
        >
          Back to contracts list
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Contract Detail</h1>
          <p className="mt-1 text-sm text-gray-600">Project ID: {projectId}</p>
        </div>
        <div className="flex items-center gap-2">
          {!isEditing ? (
            <button
              type="button"
              onClick={startEdit}
              className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              aria-label="Edit contract"
            >
              Edit
            </button>
          ) : null}
          <Link
            to={`/app/projects/${projectId}/contracts`}
            className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            aria-label="Back to contracts list"
          >
            Back to contracts list
          </Link>
        </div>
      </div>

      {isEditing ? (
        <div className="rounded-lg border border-gray-200 bg-white p-6">
          <form className="space-y-5" onSubmit={onSubmit}>
            <div>
              <label htmlFor="code" className="block text-sm font-medium text-gray-700">
                Code *
              </label>
              <input
                id="code"
                name="code"
                type="text"
                required
                value={form.code}
                onChange={(event) => setForm((previous) => ({ ...previous, code: event.target.value }))}
                className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              />
              {validationErrors.code?.[0] ? <p className="mt-1 text-sm text-red-600">{validationErrors.code[0]}</p> : null}
            </div>

            <div>
              <label htmlFor="title" className="block text-sm font-medium text-gray-700">
                Title *
              </label>
              <input
                id="title"
                name="title"
                type="text"
                required
                value={form.title}
                onChange={(event) => setForm((previous) => ({ ...previous, title: event.target.value }))}
                className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              />
              {validationErrors.title?.[0] ? <p className="mt-1 text-sm text-red-600">{validationErrors.title[0]}</p> : null}
            </div>

            <div className="grid gap-5 md:grid-cols-3">
              <div>
                <label htmlFor="status" className="block text-sm font-medium text-gray-700">
                  Status
                </label>
                <select
                  id="status"
                  name="status"
                  value={form.status}
                  onChange={(event) => setForm((previous) => ({ ...previous, status: event.target.value as ContractStatus }))}
                  className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                >
                  {CONTRACT_STATUS_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
                {validationErrors.status?.[0] ? <p className="mt-1 text-sm text-red-600">{validationErrors.status[0]}</p> : null}
              </div>

              <div>
                <label htmlFor="currency" className="block text-sm font-medium text-gray-700">
                  Currency
                </label>
                <input
                  id="currency"
                  name="currency"
                  type="text"
                  maxLength={3}
                  value={form.currency}
                  onChange={(event) => setForm((previous) => ({ ...previous, currency: event.target.value }))}
                  className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                />
                {validationErrors.currency?.[0] ? <p className="mt-1 text-sm text-red-600">{validationErrors.currency[0]}</p> : null}
              </div>

              <div>
                <label htmlFor="total_value" className="block text-sm font-medium text-gray-700">
                  Total Value
                </label>
                <input
                  id="total_value"
                  name="total_value"
                  type="number"
                  min="0"
                  step="0.01"
                  value={form.total_value}
                  onChange={(event) => setForm((previous) => ({ ...previous, total_value: event.target.value }))}
                  className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                />
                {validationErrors.total_value?.[0] ? (
                  <p className="mt-1 text-sm text-red-600">{validationErrors.total_value[0]}</p>
                ) : null}
              </div>
            </div>

            {actionError ? (
              <div className="rounded-md border border-red-200 bg-red-50 p-3">
                <p className="text-sm text-red-700">{actionError}</p>
              </div>
            ) : null}

            <div className="flex items-center gap-2 border-t border-gray-200 pt-4">
              <button
                type="submit"
                disabled={updateMutation.isPending}
                className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                aria-label="Save contract changes"
              >
                {updateMutation.isPending ? 'Saving...' : 'Save'}
              </button>
              <button
                type="button"
                onClick={cancelEdit}
                className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                aria-label="Cancel contract editing"
              >
                Cancel
              </button>
            </div>
          </form>
        </div>
      ) : (
        <div className="rounded-lg border border-gray-200 bg-white p-6">
          <dl className="grid gap-4 md:grid-cols-2">
            <div>
              <dt className="text-xs font-semibold uppercase tracking-wide text-gray-500">Code</dt>
              <dd className="mt-1 text-sm text-gray-900">{contract.code || '-'}</dd>
            </div>
            <div>
              <dt className="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</dt>
              <dd className="mt-1 text-sm text-gray-900">{contract.status || '-'}</dd>
            </div>
            <div>
              <dt className="text-xs font-semibold uppercase tracking-wide text-gray-500">Currency</dt>
              <dd className="mt-1 text-sm text-gray-900">{contract.currency || 'USD'}</dd>
            </div>
            <div>
              <dt className="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Value</dt>
              <dd className="mt-1 text-sm text-gray-900">{formatCurrency(contract.total_value, contract.currency)}</dd>
            </div>
            <div>
              <dt className="text-xs font-semibold uppercase tracking-wide text-gray-500">Created At</dt>
              <dd className="mt-1 text-sm text-gray-900">{formatDate(contract.created_at)}</dd>
            </div>
            {contract.updated_at ? (
              <div>
                <dt className="text-xs font-semibold uppercase tracking-wide text-gray-500">Updated At</dt>
                <dd className="mt-1 text-sm text-gray-900">{formatDate(contract.updated_at)}</dd>
              </div>
            ) : null}
          </dl>

          <div className="mt-6 border-t border-gray-200 pt-4">
            <button
              type="button"
              onClick={onDelete}
              disabled={deleteMutation.isPending}
              className="rounded-md border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
              aria-label="Delete contract"
            >
              {deleteMutation.isPending ? 'Deleting...' : 'Delete'}
            </button>
          </div>

          {actionError ? (
            <div className="mt-4 rounded-md border border-red-200 bg-red-50 p-3">
              <p className="text-sm text-red-700">{actionError}</p>
            </div>
          ) : null}
        </div>
      )}
    </div>
  );
}
