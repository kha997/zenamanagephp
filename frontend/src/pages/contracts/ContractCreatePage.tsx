import { FormEvent, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQueryClient } from '@tanstack/react-query';
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

export default function ContractCreatePage() {
  const { projectId } = useParams<{ projectId: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [form, setForm] = useState<ContractFormState>({
    code: '',
    title: '',
    status: 'draft',
    currency: 'USD',
    total_value: '',
  });
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<Record<string, string[]>>({});

  const canSubmit = useMemo(() => {
    return projectId && form.code.trim() && form.title.trim();
  }, [form.code, form.title, projectId]);

  const createMutation = useMutation({
    mutationFn: async () => {
      if (!projectId) {
        throw new Error('Missing project id.');
      }

      const totalValue = form.total_value.trim() === '' ? null : Number(form.total_value);

      const payload = {
        code: form.code.trim().toUpperCase(),
        title: form.title.trim(),
        status: form.status,
        currency: form.currency.trim().toUpperCase(),
        total_value: totalValue,
      };

      const response = await apiClient.post(`/api/v1/projects/${projectId}/contracts`, payload);
      return response.data as unknown;
    },
    onSuccess: (raw) => {
      const contract = extractContract(raw);

      if (!contract?.id) {
        setErrorMessage('Contract created but response payload was unexpected.');
        return;
      }

      queryClient.invalidateQueries({ queryKey: ['contracts', projectId] });
      void navigate(`/app/projects/${projectId}/contracts/${contract.id}`);
    },
    onError: (error: unknown) => {
      if (isForbiddenOrNotFound(error)) {
        setErrorMessage('Not found or no access.');
        setValidationErrors({});
        return;
      }

      const status = (error as { response?: { status?: number } })?.response?.status;
      if (status === 422) {
        const errors = extractValidationErrors(error);
        setValidationErrors(errors);
        setErrorMessage('Please correct the highlighted fields and try again.');
        return;
      }

      const message =
        (error as { response?: { data?: { message?: string } } })?.response?.data?.message ||
        (error as Error)?.message ||
        'Failed to create contract. Please try again.';

      setValidationErrors({});
      setErrorMessage(message);
    },
  });

  const onSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setErrorMessage(null);
    setValidationErrors({});
    createMutation.mutate();
  };

  if (!projectId) {
    return (
      <div className="p-6">
        <h1 className="text-2xl font-bold text-gray-900">New Contract</h1>
        <p className="mt-2 text-sm text-red-600">Missing project id.</p>
      </div>
    );
  }

  return (
    <div className="max-w-3xl space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">New Contract</h1>
          <p className="mt-1 text-sm text-gray-600">Project ID: {projectId}</p>
        </div>
        <Link
          to={`/app/projects/${projectId}/contracts`}
          className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          aria-label="Back to contracts list"
        >
          Back to contracts list
        </Link>
      </div>

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

          {errorMessage ? (
            <div className="rounded-md border border-red-200 bg-red-50 p-3">
              <p className="text-sm text-red-700">{errorMessage}</p>
            </div>
          ) : null}

          <div className="flex items-center gap-2 border-t border-gray-200 pt-4">
            <button
              type="submit"
              disabled={!canSubmit || createMutation.isPending}
              className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
              aria-label="Create contract"
            >
              {createMutation.isPending ? 'Creating...' : 'Create Contract'}
            </button>
            <Link
              to={`/app/projects/${projectId}/contracts`}
              className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              aria-label="Cancel contract creation"
            >
              Cancel
            </Link>
          </div>
        </form>
      </div>
    </div>
  );
}
