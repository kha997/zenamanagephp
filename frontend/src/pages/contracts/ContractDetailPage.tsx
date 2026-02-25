import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { apiClient } from '@/lib/api-client';

type ContractStatus = 'draft' | 'active' | 'closed' | 'cancelled';
type PaymentStatus = 'planned' | 'paid' | 'overdue';
type PaymentFrequency = 'weekly' | 'biweekly' | 'monthly' | 'quarterly';
type ContractTab = 'overview' | 'edit' | 'payments';
type PaymentFormMode = 'create' | 'edit';
type PaymentFilterStatus = 'all' | PaymentStatus;
type PaymentSortKey = 'due_date' | 'amount' | 'status' | 'created_at';
type SortDirection = 'asc' | 'desc';

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

interface ContractPaymentRecord {
  id: string;
  tenant_id?: string;
  contract_id?: string;
  name: string;
  amount: number;
  due_date: string | null;
  status: PaymentStatus;
  paid_at: string | null;
  note: string | null;
  created_at?: string;
  updated_at?: string;
}

interface AnyPagination {
  page?: number;
  per_page?: number;
  total?: number;
  last_page?: number;
  current_page?: number;
  total_pages?: number;
}

interface PaymentFormState {
  name: string;
  amount: string;
  due_date: string;
  status: PaymentStatus;
  paid_at: string;
  note: string;
}

interface SchedulePreviewRow {
  name: string;
  amount: number;
  due_date: string | null;
  status: PaymentStatus;
}

type DeleteConfirmState = { type: 'contract' } | { type: 'payment'; payment: ContractPaymentRecord } | null;

const CONTRACT_STATUS_OPTIONS: Array<{ value: ContractStatus; label: string }> = [
  { value: 'draft', label: 'Draft' },
  { value: 'active', label: 'Active' },
  { value: 'closed', label: 'Closed' },
  { value: 'cancelled', label: 'Cancelled' },
];

const PAYMENT_STATUS_OPTIONS: Array<{ value: PaymentStatus; label: string }> = [
  { value: 'planned', label: 'Planned' },
  { value: 'paid', label: 'Paid' },
  { value: 'overdue', label: 'Overdue' },
];

const PAYMENT_FREQUENCY_OPTIONS: Array<{ value: PaymentFrequency; label: string }> = [
  { value: 'weekly', label: 'Weekly' },
  { value: 'biweekly', label: 'Biweekly' },
  { value: 'monthly', label: 'Monthly' },
  { value: 'quarterly', label: 'Quarterly' },
];

const PAYMENT_FIELDS: Array<keyof PaymentFormState> = ['name', 'amount', 'due_date', 'status', 'paid_at', 'note'];

function extractContract(payload: unknown): ContractRecord | null {
  const primary = payload as any;
  const contract = primary?.data?.data ?? primary?.data ?? primary;

  if (!contract || typeof contract !== 'object') {
    return null;
  }

  return contract as ContractRecord;
}

function extractPaymentsPayload(response: unknown): { items: ContractPaymentRecord[]; pagination: AnyPagination | null } {
  const payload = response as any;
  const container = payload?.data ?? payload;

  // SSOT shape: data.items + data.pagination
  const rawItems = container?.items ?? container?.data?.items ?? container?.data ?? payload?.items ?? [];
  const pagination = container?.pagination ?? container?.data?.pagination ?? container?.meta?.pagination ?? null;

  let items: ContractPaymentRecord[] = [];
  if (Array.isArray(rawItems)) {
    items = rawItems as ContractPaymentRecord[];
  } else if (rawItems && typeof rawItems === 'object') {
    const values = Object.values(rawItems);
    items = Array.isArray(values) ? (values as ContractPaymentRecord[]) : [];
  }

  return { items, pagination };
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

function extractApiErrorMessage(error: unknown, fallbackMessage: string): string {
  if (isForbiddenOrNotFound(error)) {
    return 'Not found or no access';
  }

  const serverMessage = (error as { response?: { data?: { message?: string } } })?.response?.data?.message;
  return serverMessage ? `${fallbackMessage} ${serverMessage}` : fallbackMessage;
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

function toDateInputValue(value?: string | null): string {
  if (!value) {
    return '';
  }

  const datePart = value.includes('T') ? value.split('T')[0] : value;
  return /^\d{4}-\d{2}-\d{2}$/.test(datePart) ? datePart : '';
}

function toPaymentForm(payment?: ContractPaymentRecord): PaymentFormState {
  return {
    name: payment?.name || '',
    amount: payment?.amount != null ? String(payment.amount) : '',
    due_date: toDateInputValue(payment?.due_date),
    status: payment?.status || 'planned',
    paid_at: toDateInputValue(payment?.paid_at),
    note: payment?.note || '',
  };
}

function normalizeOptionalText(value: string): string | null {
  const trimmed = value.trim();
  return trimmed === '' ? null : trimmed;
}

function normalizeDateField(value: string): string | null {
  const trimmed = value.trim();
  return trimmed === '' ? null : trimmed;
}

function truncateText(value: string | null | undefined, maxLength = 60): string {
  if (!value) {
    return '-';
  }

  return value.length > maxLength ? `${value.slice(0, maxLength)}...` : value;
}

function parseDateInput(value: string): Date | null {
  if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return null;
  }

  const date = new Date(`${value}T00:00:00`);
  return Number.isNaN(date.getTime()) ? null : date;
}

function formatDateInput(value: Date): string {
  const year = value.getFullYear();
  const month = String(value.getMonth() + 1).padStart(2, '0');
  const day = String(value.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function addFrequency(date: Date, frequency: PaymentFrequency, steps: number): Date {
  const clone = new Date(date.getTime());
  if (frequency === 'weekly') {
    clone.setDate(clone.getDate() + steps * 7);
    return clone;
  }

  if (frequency === 'biweekly') {
    clone.setDate(clone.getDate() + steps * 14);
    return clone;
  }

  if (frequency === 'quarterly') {
    clone.setMonth(clone.getMonth() + steps * 3);
    return clone;
  }

  clone.setMonth(clone.getMonth() + steps);
  return clone;
}

function splitAmountByCount(totalValue: number, count: number): number[] {
  if (count <= 0) {
    return [];
  }

  const totalCents = Math.round(totalValue * 100);
  const baseCents = Math.floor(totalCents / count);
  const lastCents = totalCents - baseCents * (count - 1);

  return Array.from({ length: count }, (_, index) => (index === count - 1 ? lastCents : baseCents) / 100);
}

function formatCurrency(amount: number | null, currency: string | null): string {
  const value = typeof amount === 'number' && Number.isFinite(amount) ? amount : 0;
  const normalizedCurrency = (currency || 'USD').toUpperCase();

  try {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency: normalizedCurrency,
    }).format(value);
  } catch (_error) {
    try {
      return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
      }).format(value);
    } catch (_fallbackError) {
      return `${value.toFixed(2)} ${normalizedCurrency}`;
    }
  }
}

function formatDate(dateString?: string | null): string {
  if (!dateString) {
    return '-';
  }

  const datePart = dateString.includes('T') ? dateString.split('T')[0] : dateString;
  if (/^\d{4}-\d{2}-\d{2}$/.test(datePart)) {
    return datePart;
  }

  const date = new Date(dateString);
  if (Number.isNaN(date.getTime())) {
    return '-';
  }

  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function getTodayDateString(): string {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

export default function ContractDetailPage() {
  const { projectId, contractId } = useParams<{ projectId: string; contractId: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [activeTab, setActiveTab] = useState<ContractTab>('overview');
  const [form, setForm] = useState<ContractFormState>({
    code: '',
    title: '',
    status: 'draft',
    currency: 'USD',
    total_value: '',
  });
  const [actionError, setActionError] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<Record<string, string[]>>({});

  const [paymentsPage, setPaymentsPage] = useState<number>(1);
  const paymentsPerPage = 15;
  const [paymentStatusFilter, setPaymentStatusFilter] = useState<PaymentFilterStatus>('all');
  const [paymentNameSearch, setPaymentNameSearch] = useState<string>('');
  const [paymentSortKey, setPaymentSortKey] = useState<PaymentSortKey>('due_date');
  const [paymentSortDirection, setPaymentSortDirection] = useState<SortDirection>('asc');

  const [paymentModalMode, setPaymentModalMode] = useState<PaymentFormMode | null>(null);
  const [selectedPayment, setSelectedPayment] = useState<ContractPaymentRecord | null>(null);
  const [paymentForm, setPaymentForm] = useState<PaymentFormState>(toPaymentForm());
  const [paymentValidationErrors, setPaymentValidationErrors] = useState<Record<string, string[]>>({});
  const [paymentFormError, setPaymentFormError] = useState<string | null>(null);
  const [scheduleCount, setScheduleCount] = useState<string>('4');
  const [scheduleFirstDueDate, setScheduleFirstDueDate] = useState<string>('');
  const [scheduleFrequency, setScheduleFrequency] = useState<PaymentFrequency>('monthly');
  const [scheduleNamePrefix, setScheduleNamePrefix] = useState<string>('Payment');
  const [schedulePreviewRows, setSchedulePreviewRows] = useState<SchedulePreviewRow[]>([]);
  const [scheduleError, setScheduleError] = useState<string | null>(null);
  const [deleteConfirmState, setDeleteConfirmState] = useState<DeleteConfirmState>(null);
  const confirmDeleteButtonRef = useRef<HTMLButtonElement | null>(null);

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

  const {
    data: paymentsResponse,
    isLoading: isPaymentsLoading,
    isError: isPaymentsError,
    error: paymentsError,
    refetch: refetchPayments,
    isFetching: isPaymentsFetching,
  } = useQuery({
    queryKey: ['contractPayments', contractId, paymentsPage, paymentsPerPage],
    enabled: Boolean(contractId && activeTab === 'payments'),
    queryFn: async () => {
      const response = await apiClient.get(`/api/v1/contracts/${contractId}/payments`, {
        params: {
          page: paymentsPage,
          per_page: paymentsPerPage,
        },
      });

      return response.data as unknown;
    },
  });

  const paymentsPayload = useMemo(
    () => (paymentsResponse ? extractPaymentsPayload(paymentsResponse) : { items: [], pagination: null }),
    [paymentsResponse]
  );

  const payments = paymentsPayload.items;
  const paymentsPagination = paymentsPayload.pagination;
  const paymentsCurrentPage = paymentsPagination?.current_page ?? paymentsPagination?.page ?? paymentsPage;
  const paymentsLastPage = paymentsPagination?.last_page ?? paymentsPagination?.total_pages ?? 1;
  const paymentsTotalItems = paymentsPagination?.total ?? payments.length;
  const filteredAndSortedPayments = useMemo(() => {
    const normalizedSearch = paymentNameSearch.trim().toLowerCase();
    const directionFactor = paymentSortDirection === 'asc' ? 1 : -1;

    return payments
      .filter((payment) => {
        if (paymentStatusFilter !== 'all' && payment.status !== paymentStatusFilter) {
          return false;
        }

        if (normalizedSearch !== '' && !(payment.name || '').toLowerCase().includes(normalizedSearch)) {
          return false;
        }

        return true;
      })
      .map((payment, index) => ({ payment, index }))
      .sort((a, b) => {
        let comparison = 0;

        if (paymentSortKey === 'due_date') {
          const aDueRaw = a.payment.due_date ? Date.parse(a.payment.due_date) : null;
          const bDueRaw = b.payment.due_date ? Date.parse(b.payment.due_date) : null;
          const aDue = aDueRaw != null && !Number.isNaN(aDueRaw) ? aDueRaw : null;
          const bDue = bDueRaw != null && !Number.isNaN(bDueRaw) ? bDueRaw : null;

          // Null due_date sorts last for asc and first for desc via direction inversion.
          if (aDue == null && bDue == null) {
            comparison = 0;
          } else if (aDue == null) {
            comparison = 1;
          } else if (bDue == null) {
            comparison = -1;
          } else {
            comparison = aDue - bDue;
          }
        } else if (paymentSortKey === 'amount') {
          comparison = (a.payment.amount || 0) - (b.payment.amount || 0);
        } else if (paymentSortKey === 'status') {
          comparison = (a.payment.status || '').localeCompare(b.payment.status || '');
        } else if (paymentSortKey === 'created_at') {
          const aCreated = a.payment.created_at ? Date.parse(a.payment.created_at) : 0;
          const bCreated = b.payment.created_at ? Date.parse(b.payment.created_at) : 0;
          const safeACreated = Number.isNaN(aCreated) ? 0 : aCreated;
          const safeBCreated = Number.isNaN(bCreated) ? 0 : bCreated;
          comparison = safeACreated - safeBCreated;
        }

        if (comparison !== 0) {
          return comparison * directionFactor;
        }

        const idCompare = String(a.payment.id || '').localeCompare(String(b.payment.id || ''));
        if (idCompare !== 0) {
          return idCompare;
        }
        return a.index - b.index;
      })
      .map((entry) => entry.payment);
  }, [paymentNameSearch, paymentSortDirection, paymentSortKey, paymentStatusFilter, payments]);
  const paymentsSummary = useMemo(() => {
    let totalPaid = 0;
    let totalPlanned = 0;
    let totalOverdue = 0;

    payments.forEach((payment) => {
      if (payment.status === 'paid') {
        totalPaid += payment.amount || 0;
      } else if (payment.status === 'planned') {
        totalPlanned += payment.amount || 0;
      } else if (payment.status === 'overdue') {
        totalOverdue += payment.amount || 0;
      }
    });

    const contractTotalValue = typeof contract?.total_value === 'number' ? contract.total_value : 0;
    const remaining = Math.max(0, contractTotalValue - totalPaid);

    return {
      totalPaid,
      totalPlanned,
      totalOverdue,
      remaining,
    };
  }, [contract?.total_value, payments]);

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
      setValidationErrors({});
      setActionError(null);
      setActiveTab('overview');
      toast.success('Contract updated successfully.');
    },
    onError: (mutationError: unknown) => {
      if (isForbiddenOrNotFound(mutationError)) {
        setActionError('Not found or no access');
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
        setActionError('Not found or no access');
        return;
      }

      const message =
        (mutationError as { response?: { data?: { message?: string } } })?.response?.data?.message ||
        (mutationError as Error)?.message ||
        'Failed to delete contract. Please try again.';

      setActionError(message);
    },
  });

  const createPaymentMutation = useMutation({
    mutationFn: async () => {
      if (!contractId) {
        throw new Error('Missing contract id.');
      }

      const amountNumber = Number(paymentForm.amount);
      const payload = {
        name: paymentForm.name.trim(),
        amount: amountNumber,
        due_date: normalizeDateField(paymentForm.due_date),
        status: paymentForm.status || 'planned',
        paid_at: normalizeDateField(paymentForm.paid_at),
        note: normalizeOptionalText(paymentForm.note),
      };

      const response = await apiClient.post(`/api/v1/contracts/${contractId}/payments`, payload);
      return response.data as unknown;
    },
    onSuccess: async () => {
      setPaymentModalMode(null);
      setSelectedPayment(null);
      setPaymentForm(toPaymentForm());
      setPaymentValidationErrors({});
      setPaymentFormError(null);
      await refetchPayments();
      toast.success('Payment created successfully.');
    },
    onError: (mutationError: unknown) => {
      if (isForbiddenOrNotFound(mutationError)) {
        setPaymentFormError('Not found or no access');
        setPaymentValidationErrors({});
        return;
      }

      const status = (mutationError as { response?: { status?: number } })?.response?.status;
      if (status === 422) {
        setPaymentValidationErrors(extractValidationErrors(mutationError));
        setPaymentFormError('Please correct the highlighted fields and try again.');
        return;
      }

      setPaymentValidationErrors({});
      setPaymentFormError(extractApiErrorMessage(mutationError, 'Failed to create payment.'));
    },
  });

  const updatePaymentMutation = useMutation({
    mutationFn: async (args: { paymentId: string; payload: Partial<Record<keyof PaymentFormState, unknown>> }) => {
      if (!contractId) {
        throw new Error('Missing contract id.');
      }

      const response = await apiClient.put(`/api/v1/contracts/${contractId}/payments/${args.paymentId}`, args.payload);
      return response.data as unknown;
    },
    onSuccess: async () => {
      setPaymentModalMode(null);
      setSelectedPayment(null);
      setPaymentForm(toPaymentForm());
      setPaymentValidationErrors({});
      setPaymentFormError(null);
      await refetchPayments();
      toast.success('Payment updated successfully.');
    },
    onError: (mutationError: unknown) => {
      if (isForbiddenOrNotFound(mutationError)) {
        setPaymentFormError('Not found or no access');
        setPaymentValidationErrors({});
        return;
      }

      const status = (mutationError as { response?: { status?: number } })?.response?.status;
      if (status === 422) {
        setPaymentValidationErrors(extractValidationErrors(mutationError));
        setPaymentFormError('Please correct the highlighted fields and try again.');
        return;
      }

      setPaymentValidationErrors({});
      setPaymentFormError(extractApiErrorMessage(mutationError, 'Failed to update payment.'));
    },
  });

  const deletePaymentMutation = useMutation({
    mutationFn: async (paymentId: string) => {
      if (!contractId) {
        throw new Error('Missing contract id.');
      }

      await apiClient.delete(`/api/v1/contracts/${contractId}/payments/${paymentId}`);
    },
    onSuccess: async () => {
      await refetchPayments();
      toast.success('Payment deleted successfully.');
    },
    onError: (mutationError: unknown) => {
      toast.error(extractApiErrorMessage(mutationError, 'Failed to delete payment.'));
    },
  });

  const applyScheduleMutation = useMutation({
    mutationFn: async (rows: SchedulePreviewRow[]) => {
      if (!contractId) {
        throw new Error('Missing contract id.');
      }

      for (let index = 0; index < rows.length; index += 1) {
        const row = rows[index];
        try {
          await apiClient.post(`/api/v1/contracts/${contractId}/payments`, {
            name: row.name,
            amount: row.amount,
            due_date: row.due_date,
            status: row.status,
          });
        } catch (error) {
          const wrappedError = new Error(`Schedule apply failed at row ${index + 1}`);
          (wrappedError as Error & { cause?: unknown; rowIndex?: number }).cause = error;
          (wrappedError as Error & { cause?: unknown; rowIndex?: number }).rowIndex = index;
          throw wrappedError;
        }
      }
    },
    onSuccess: async () => {
      setScheduleError(null);
      setSchedulePreviewRows([]);
      await refetchPayments();
      toast.success('Schedule applied successfully.');
    },
    onError: (mutationError: unknown) => {
      const payload = mutationError as Error & { cause?: unknown; rowIndex?: number };
      const failedIndex = typeof payload.rowIndex === 'number' ? payload.rowIndex + 1 : null;
      const sourceError = payload.cause ?? mutationError;
      const baseMessage = extractApiErrorMessage(sourceError, 'Failed to apply schedule.');
      setScheduleError(failedIndex ? `Stopped at payment #${failedIndex}. ${baseMessage}` : baseMessage);
    },
  });

  const openEditTab = () => {
    if (!contract) {
      return;
    }

    setForm(toForm(contract));
    setValidationErrors({});
    setActionError(null);
    setActiveTab('edit');
  };

  const openOverviewTab = () => {
    setValidationErrors({});
    setActionError(null);
    setActiveTab('overview');
  };

  const openPaymentsTab = () => {
    setActiveTab('payments');
  };

  const openCreatePaymentModal = () => {
    setPaymentModalMode('create');
    setSelectedPayment(null);
    setPaymentForm(toPaymentForm());
    setPaymentValidationErrors({});
    setPaymentFormError(null);
  };

  const openEditPaymentModal = (payment: ContractPaymentRecord) => {
    setPaymentModalMode('edit');
    setSelectedPayment(payment);
    setPaymentForm(toPaymentForm(payment));
    setPaymentValidationErrors({});
    setPaymentFormError(null);
  };

  const closePaymentModal = () => {
    setPaymentModalMode(null);
    setSelectedPayment(null);
    setPaymentForm(toPaymentForm());
    setPaymentValidationErrors({});
    setPaymentFormError(null);
  };

  const onSubmitContract = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setActionError(null);
    setValidationErrors({});
    updateMutation.mutate();
  };

  const onDeleteContract = () => {
    if (deleteMutation.isPending) {
      return;
    }

    setDeleteConfirmState({ type: 'contract' });
  };

  const onSubmitPayment = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setPaymentValidationErrors({});
    setPaymentFormError(null);

    if (paymentModalMode === 'create') {
      createPaymentMutation.mutate();
      return;
    }

    if (paymentModalMode === 'edit' && selectedPayment) {
      const original = toPaymentForm(selectedPayment);
      const payload: Partial<Record<keyof PaymentFormState, unknown>> = {};

      for (const field of PAYMENT_FIELDS) {
        const currentValue = paymentForm[field];
        const originalValue = original[field];

        if (field === 'amount') {
          const currentAmount = Number(currentValue);
          const originalAmount = Number(originalValue);
          if (currentAmount !== originalAmount) {
            payload.amount = currentAmount;
          }
          continue;
        }

        if (field === 'name') {
          const currentName = currentValue.trim();
          const originalName = originalValue.trim();
          if (currentName !== originalName) {
            payload.name = currentName;
          }
          continue;
        }

        if (field === 'due_date' || field === 'paid_at') {
          const currentDate = normalizeDateField(currentValue);
          const originalDate = normalizeDateField(originalValue);
          if (currentDate !== originalDate) {
            payload[field] = currentDate;
          }
          continue;
        }

        if (field === 'note') {
          const currentNote = normalizeOptionalText(currentValue);
          const originalNote = normalizeOptionalText(originalValue);
          if (currentNote !== originalNote) {
            payload.note = currentNote;
          }
          continue;
        }

        if (field === 'status' && currentValue !== originalValue) {
          payload.status = currentValue;
        }
      }

      if (Object.keys(payload).length === 0) {
        closePaymentModal();
        return;
      }

      updatePaymentMutation.mutate({ paymentId: selectedPayment.id, payload });
    }
  };

  const onDeletePayment = (payment: ContractPaymentRecord) => {
    if (deletePaymentMutation.isPending) {
      return;
    }

    setDeleteConfirmState({ type: 'payment', payment });
  };

  const isDeletePending = deleteMutation.isPending || deletePaymentMutation.isPending;

  const closeDeleteConfirm = () => {
    if (isDeletePending) {
      return;
    }

    setDeleteConfirmState(null);
  };

  const onConfirmDelete = () => {
    if (!deleteConfirmState || isDeletePending) {
      return;
    }

    if (deleteConfirmState.type === 'contract') {
      setActionError(null);
      deleteMutation.mutate(undefined, {
        onSettled: () => setDeleteConfirmState(null),
      });
      return;
    }

    deletePaymentMutation.mutate(deleteConfirmState.payment.id, {
      onSettled: () => setDeleteConfirmState(null),
    });
  };

  useEffect(() => {
    if (!deleteConfirmState) {
      return undefined;
    }

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        closeDeleteConfirm();
      }
    };

    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, [deleteConfirmState, isDeletePending]);

  useEffect(() => {
    if (!deleteConfirmState) {
      return;
    }

    confirmDeleteButtonRef.current?.focus();
  }, [deleteConfirmState]);

  const onMarkPaymentPaid = (payment: ContractPaymentRecord) => {
    if (payment.status === 'paid') {
      return;
    }

    updatePaymentMutation.mutate({
      paymentId: payment.id,
      payload: {
        status: 'paid',
        paid_at: getTodayDateString(),
      },
    });
  };

  const renderStatusBadge = (status: PaymentStatus) => {
    const stylesByStatus: Record<PaymentStatus, string> = {
      planned: 'bg-gray-100 text-gray-700',
      paid: 'bg-green-100 text-green-700',
      overdue: 'bg-red-100 text-red-700',
    };

    return (
      <span className={`inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize ${stylesByStatus[status]}`}>
        {status}
      </span>
    );
  };

  const onGenerateSchedulePreview = () => {
    setScheduleError(null);

    const count = Number(scheduleCount);
    if (!Number.isInteger(count) || count <= 0) {
      setSchedulePreviewRows([]);
      setScheduleError('Number of payments must be a positive whole number.');
      return;
    }

    const prefix = scheduleNamePrefix.trim() || 'Payment';
    const totalValue = typeof contract?.total_value === 'number' ? contract.total_value : 0;
    const amounts = splitAmountByCount(totalValue, count);
    const firstDate = parseDateInput(scheduleFirstDueDate);
    if (scheduleFirstDueDate && !firstDate) {
      setSchedulePreviewRows([]);
      setScheduleError('First due date must be in YYYY-MM-DD format.');
      return;
    }

    const rows: SchedulePreviewRow[] = Array.from({ length: count }, (_, index) => ({
      name: `${prefix} #${index + 1}`,
      amount: amounts[index] ?? 0,
      due_date: firstDate ? formatDateInput(addFrequency(firstDate, scheduleFrequency, index)) : null,
      status: 'planned',
    }));

    setSchedulePreviewRows(rows);
  };

  const onApplySchedule = () => {
    if (schedulePreviewRows.length === 0 || applyScheduleMutation.isPending) {
      return;
    }

    setScheduleError(null);
    applyScheduleMutation.mutate(schedulePreviewRows);
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
      ? 'Not found or no access'
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
          <Link
            to={`/app/projects/${projectId}/contracts`}
            className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            aria-label="Back to contracts list"
          >
            Back to contracts list
          </Link>
        </div>
      </div>

      <div className="rounded-lg border border-gray-200 bg-white p-2">
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            onClick={openOverviewTab}
            className={`rounded-md px-3 py-2 text-sm font-medium ${
              activeTab === 'overview' ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'
            }`}
          >
            Overview
          </button>
          <button
            type="button"
            onClick={openEditTab}
            className={`rounded-md px-3 py-2 text-sm font-medium ${
              activeTab === 'edit' ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'
            }`}
          >
            Edit
          </button>
          <button
            type="button"
            onClick={openPaymentsTab}
            className={`rounded-md px-3 py-2 text-sm font-medium ${
              activeTab === 'payments' ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'
            }`}
          >
            Payments
          </button>
        </div>
      </div>

      {activeTab === 'overview' ? (
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
              onClick={onDeleteContract}
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
      ) : null}

      {activeTab === 'edit' ? (
        <div className="rounded-lg border border-gray-200 bg-white p-6">
          <form className="space-y-5" onSubmit={onSubmitContract}>
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
                onClick={openOverviewTab}
                className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                aria-label="Cancel contract editing"
              >
                Cancel
              </button>
            </div>
          </form>
        </div>
      ) : null}

      {activeTab === 'payments' ? (
        <div className="space-y-4 rounded-lg border border-gray-200 bg-white p-6">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h2 className="text-lg font-semibold text-gray-900">Payments</h2>
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={openCreatePaymentModal}
                className="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700"
              >
                Add payment
              </button>
            </div>
          </div>

          <div className="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <h3 className="text-sm font-semibold uppercase tracking-wide text-gray-700">Schedule generator</h3>
            <div className="grid gap-3 md:grid-cols-4">
              <div>
                <label htmlFor="schedule_count" className="block text-sm font-medium text-gray-700">
                  Number of payments
                </label>
                <input
                  id="schedule_count"
                  type="number"
                  min="1"
                  step="1"
                  value={scheduleCount}
                  onChange={(event) => setScheduleCount(event.target.value)}
                  className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label htmlFor="schedule_first_due_date" className="block text-sm font-medium text-gray-700">
                  First due date
                </label>
                <input
                  id="schedule_first_due_date"
                  type="date"
                  value={scheduleFirstDueDate}
                  onChange={(event) => setScheduleFirstDueDate(event.target.value)}
                  className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label htmlFor="schedule_frequency" className="block text-sm font-medium text-gray-700">
                  Frequency
                </label>
                <select
                  id="schedule_frequency"
                  value={scheduleFrequency}
                  onChange={(event) => setScheduleFrequency(event.target.value as PaymentFrequency)}
                  className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                >
                  {PAYMENT_FREQUENCY_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label htmlFor="schedule_name_prefix" className="block text-sm font-medium text-gray-700">
                  Name prefix
                </label>
                <input
                  id="schedule_name_prefix"
                  type="text"
                  value={scheduleNamePrefix}
                  onChange={(event) => setScheduleNamePrefix(event.target.value)}
                  className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                />
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <button
                type="button"
                onClick={onGenerateSchedulePreview}
                className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
              >
                Generate preview
              </button>
              <button
                type="button"
                onClick={onApplySchedule}
                disabled={schedulePreviewRows.length === 0 || applyScheduleMutation.isPending}
                className="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
              >
                {applyScheduleMutation.isPending ? 'Applying...' : 'Apply schedule'}
              </button>
              <p className="text-xs text-gray-600">
                Total source: {formatCurrency(contract.total_value, contract.currency)}. Last row adjusts rounding.
              </p>
            </div>

            {scheduleError ? (
              <div className="rounded-md border border-red-200 bg-red-50 p-3">
                <p className="text-sm text-red-700">{scheduleError}</p>
              </div>
            ) : null}

            {schedulePreviewRows.length > 0 ? (
              <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Name</th>
                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Amount</th>
                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Due Date</th>
                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Status</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200 bg-white">
                      {schedulePreviewRows.map((row, index) => (
                        <tr key={`${row.name}-${index}`}>
                          <td className="px-4 py-2 text-sm text-gray-900">{row.name}</td>
                          <td className="px-4 py-2 text-sm text-gray-700">{formatCurrency(row.amount, contract.currency)}</td>
                          <td className="px-4 py-2 text-sm text-gray-700">{formatDate(row.due_date)}</td>
                          <td className="px-4 py-2 text-sm text-gray-700">{renderStatusBadge(row.status)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            ) : null}
          </div>

          <div className="rounded-lg border border-gray-200 bg-white p-3">
            <div className="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">Totals (all payments)</div>
            <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
              <div className="grid gap-2 text-sm md:grid-cols-4">
                <p className="text-gray-700">
                  Paid: <span className="font-semibold text-gray-900">{formatCurrency(paymentsSummary.totalPaid, contract.currency)}</span>
                </p>
                <p className="text-gray-700">
                  Planned:{' '}
                  <span className="font-semibold text-gray-900">{formatCurrency(paymentsSummary.totalPlanned, contract.currency)}</span>
                </p>
                <p className="text-gray-700">
                  Overdue:{' '}
                  <span className="font-semibold text-gray-900">{formatCurrency(paymentsSummary.totalOverdue, contract.currency)}</span>
                </p>
                <p className="text-gray-700">
                  Remaining:{' '}
                  <span className="font-semibold text-gray-900">{formatCurrency(paymentsSummary.remaining, contract.currency)}</span>
                </p>
              </div>
              <div className="flex flex-wrap items-end gap-2">
                <label className="text-xs font-medium text-gray-700">
                  Status
                  <select
                    value={paymentStatusFilter}
                    onChange={(event) => setPaymentStatusFilter(event.target.value as PaymentFilterStatus)}
                    className="mt-1 block rounded-md border border-gray-300 px-2 py-1 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  >
                    <option value="all">All</option>
                    <option value="planned">planned</option>
                    <option value="paid">paid</option>
                    <option value="overdue">overdue</option>
                  </select>
                </label>
                <label className="text-xs font-medium text-gray-700">
                  Search name
                  <input
                    type="text"
                    value={paymentNameSearch}
                    onChange={(event) => setPaymentNameSearch(event.target.value)}
                    placeholder="Payment name"
                    className="mt-1 block rounded-md border border-gray-300 px-2 py-1 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  />
                </label>
                <label className="text-xs font-medium text-gray-700">
                  Sort by
                  <select
                    value={paymentSortKey}
                    onChange={(event) => setPaymentSortKey(event.target.value as PaymentSortKey)}
                    className="mt-1 block rounded-md border border-gray-300 px-2 py-1 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  >
                    <option value="due_date">due_date</option>
                    <option value="amount">amount</option>
                    <option value="status">status</option>
                    <option value="created_at">created_at</option>
                  </select>
                </label>
                <button
                  type="button"
                  onClick={() => setPaymentSortDirection((previous) => (previous === 'asc' ? 'desc' : 'asc'))}
                  className="rounded-md border border-gray-300 px-2 py-1 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                  {paymentSortDirection}
                </button>
              </div>
            </div>
          </div>

          {isPaymentsLoading || isPaymentsFetching ? (
            <div className="rounded-lg border border-gray-200 bg-white p-4">
              <p className="text-sm text-gray-600">Loading payments...</p>
            </div>
          ) : null}

          {isPaymentsError ? (
            <div className="rounded-lg border border-red-200 bg-red-50 p-4">
              <p className="text-sm text-red-700">
                {isForbiddenOrNotFound(paymentsError)
                  ? 'Not found or no access'
                  : `Failed to load payments.${paymentsError instanceof Error ? ` ${paymentsError.message}` : ''}`}
              </p>
              <button
                type="button"
                onClick={() => void refetchPayments()}
                className="mt-3 rounded-md border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100"
              >
                Retry
              </button>
            </div>
          ) : null}

          {!isPaymentsError ? (
            <div className="overflow-hidden rounded-lg border border-gray-200">
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Name</th>
                      <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Amount</th>
                      <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Due Date</th>
                      <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Status</th>
                      <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Paid At</th>
                      <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Note</th>
                      <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-200 bg-white">
                    {filteredAndSortedPayments.length === 0 ? (
                      <tr>
                        <td className="px-4 py-8 text-sm text-gray-500" colSpan={7}>
                          {payments.length === 0 ? 'No payments yet.' : 'No payments match current filters.'}
                        </td>
                      </tr>
                    ) : (
                      filteredAndSortedPayments.map((payment) => (
                        <tr key={payment.id}>
                          <td className="px-4 py-3 text-sm font-medium text-gray-900">{payment.name || '-'}</td>
                          <td className="px-4 py-3 text-sm text-gray-700">{formatCurrency(payment.amount, contract.currency)}</td>
                          <td className="px-4 py-3 text-sm text-gray-700">{formatDate(payment.due_date)}</td>
                          <td className="px-4 py-3 text-sm text-gray-700">{renderStatusBadge(payment.status || 'planned')}</td>
                          <td className="px-4 py-3 text-sm text-gray-700">{formatDate(payment.paid_at)}</td>
                          <td className="px-4 py-3 text-sm text-gray-700">{truncateText(payment.note)}</td>
                          <td className="px-4 py-3 text-sm">
                            <div className="flex items-center gap-2">
                              {payment.status !== 'paid' ? (
                                <button
                                  type="button"
                                  onClick={() => onMarkPaymentPaid(payment)}
                                  disabled={updatePaymentMutation.isPending}
                                  className="rounded-md border border-green-300 px-2 py-1 text-xs font-medium text-green-700 hover:bg-green-50 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                  Mark paid
                                </button>
                              ) : null}
                              <button
                                type="button"
                                onClick={() => openEditPaymentModal(payment)}
                                className="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                              >
                                Edit
                              </button>
                              <button
                                type="button"
                                onClick={() => onDeletePayment(payment)}
                                disabled={deletePaymentMutation.isPending}
                                className="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50"
                              >
                                Delete
                              </button>
                            </div>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          ) : null}

          {paymentsPagination ? (
            <div className="flex flex-wrap items-center justify-between gap-2 text-sm text-gray-600">
              <p>
                Showing page {paymentsCurrentPage} of {paymentsLastPage} ({paymentsTotalItems} total)
              </p>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setPaymentsPage((previous) => Math.max(1, previous - 1))}
                  disabled={paymentsCurrentPage <= 1}
                  className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  Previous
                </button>
                <button
                  type="button"
                  onClick={() => setPaymentsPage((previous) => previous + 1)}
                  disabled={paymentsCurrentPage >= paymentsLastPage}
                  className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  Next
                </button>
              </div>
            </div>
          ) : null}
        </div>
      ) : null}

      {paymentModalMode ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4">
          <div className="w-full max-w-xl rounded-lg bg-white p-6 shadow-xl">
            <div className="mb-4 flex items-center justify-between">
              <h2 className="text-lg font-semibold text-gray-900">
                {paymentModalMode === 'create' ? 'Add payment' : 'Edit payment'}
              </h2>
              <button
                type="button"
                onClick={closePaymentModal}
                className="rounded-md border border-gray-300 px-2 py-1 text-sm text-gray-700 hover:bg-gray-50"
              >
                Close
              </button>
            </div>

            <form className="space-y-4" onSubmit={onSubmitPayment}>
              <div>
                <label htmlFor="payment_name" className="block text-sm font-medium text-gray-700">
                  Name *
                </label>
                <input
                  id="payment_name"
                  type="text"
                  required
                  value={paymentForm.name}
                  onChange={(event) => setPaymentForm((previous) => ({ ...previous, name: event.target.value }))}
                  className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                />
                {paymentValidationErrors.name?.[0] ? (
                  <p className="mt-1 text-sm text-red-600">{paymentValidationErrors.name[0]}</p>
                ) : null}
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                <div>
                  <label htmlFor="payment_amount" className="block text-sm font-medium text-gray-700">
                    Amount *
                  </label>
                  <input
                    id="payment_amount"
                    type="number"
                    min="0"
                    step="0.01"
                    required
                    value={paymentForm.amount}
                    onChange={(event) => setPaymentForm((previous) => ({ ...previous, amount: event.target.value }))}
                    className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                  />
                  {paymentValidationErrors.amount?.[0] ? (
                    <p className="mt-1 text-sm text-red-600">{paymentValidationErrors.amount[0]}</p>
                  ) : null}
                </div>

                <div>
                  <label htmlFor="payment_status" className="block text-sm font-medium text-gray-700">
                    Status
                  </label>
                  <select
                    id="payment_status"
                    value={paymentForm.status}
                    onChange={(event) =>
                      setPaymentForm((previous) => ({ ...previous, status: event.target.value as PaymentStatus }))
                    }
                    className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                  >
                    {PAYMENT_STATUS_OPTIONS.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                  {paymentValidationErrors.status?.[0] ? (
                    <p className="mt-1 text-sm text-red-600">{paymentValidationErrors.status[0]}</p>
                  ) : null}
                </div>
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                <div>
                  <label htmlFor="payment_due_date" className="block text-sm font-medium text-gray-700">
                    Due Date
                  </label>
                  <input
                    id="payment_due_date"
                    type="date"
                    value={paymentForm.due_date}
                    onChange={(event) => setPaymentForm((previous) => ({ ...previous, due_date: event.target.value }))}
                    className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                  />
                  {paymentValidationErrors.due_date?.[0] ? (
                    <p className="mt-1 text-sm text-red-600">{paymentValidationErrors.due_date[0]}</p>
                  ) : null}
                </div>

                <div>
                  <label htmlFor="payment_paid_at" className="block text-sm font-medium text-gray-700">
                    Paid At
                  </label>
                  <input
                    id="payment_paid_at"
                    type="date"
                    value={paymentForm.paid_at}
                    onChange={(event) => setPaymentForm((previous) => ({ ...previous, paid_at: event.target.value }))}
                    className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                  />
                  {paymentValidationErrors.paid_at?.[0] ? (
                    <p className="mt-1 text-sm text-red-600">{paymentValidationErrors.paid_at[0]}</p>
                  ) : null}
                </div>
              </div>

              <div>
                <label htmlFor="payment_note" className="block text-sm font-medium text-gray-700">
                  Note
                </label>
                <textarea
                  id="payment_note"
                  value={paymentForm.note}
                  onChange={(event) => setPaymentForm((previous) => ({ ...previous, note: event.target.value }))}
                  className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                  rows={3}
                />
                {paymentValidationErrors.note?.[0] ? (
                  <p className="mt-1 text-sm text-red-600">{paymentValidationErrors.note[0]}</p>
                ) : null}
              </div>

              {paymentFormError ? (
                <div className="rounded-md border border-red-200 bg-red-50 p-3">
                  <p className="text-sm text-red-700">{paymentFormError}</p>
                </div>
              ) : null}

              <div className="flex items-center gap-2 border-t border-gray-200 pt-4">
                <button
                  type="submit"
                  disabled={createPaymentMutation.isPending || updatePaymentMutation.isPending}
                  className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {createPaymentMutation.isPending || updatePaymentMutation.isPending
                    ? 'Saving...'
                    : paymentModalMode === 'create'
                      ? 'Create payment'
                      : 'Save changes'}
                </button>
                <button
                  type="button"
                  onClick={closePaymentModal}
                  className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}

      {deleteConfirmState ? (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4"
          onClick={closeDeleteConfirm}
        >
          <div
            className="w-full max-w-md rounded-lg border border-gray-200 bg-white p-6 shadow-xl"
            onClick={(event) => event.stopPropagation()}
            role="dialog"
            aria-modal="true"
            aria-label="Confirm delete"
          >
            <h2 className="text-lg font-semibold text-gray-900">
              {deleteConfirmState.type === 'contract' ? 'Delete contract?' : 'Delete payment?'}
            </h2>

            {deleteConfirmState.type === 'contract' ? (
              <div className="mt-3 space-y-2 text-sm text-gray-700">
                <p>
                  Contract: <span className="font-semibold text-gray-900">{contract.code || '-'}</span> -{' '}
                  <span className="font-semibold text-gray-900">{contract.title || '-'}</span>
                </p>
                <p className="text-red-700">This action cannot be undone.</p>
              </div>
            ) : (
              <div className="mt-3 space-y-2 text-sm text-gray-700">
                <p>
                  Payment: <span className="font-semibold text-gray-900">{deleteConfirmState.payment.name || '-'}</span>
                </p>
                <p>
                  Amount:{' '}
                  <span className="font-semibold text-gray-900">
                    {formatCurrency(deleteConfirmState.payment.amount, contract.currency)}
                  </span>
                </p>
                <p>
                  Due date: <span className="font-semibold text-gray-900">{formatDate(deleteConfirmState.payment.due_date)}</span>
                </p>
                <p className="text-red-700">This action cannot be undone.</p>
              </div>
            )}

            <div className="mt-6 flex items-center justify-end gap-2">
              <button
                type="button"
                onClick={closeDeleteConfirm}
                disabled={isDeletePending}
                className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
              >
                Cancel
              </button>
              <button
                ref={confirmDeleteButtonRef}
                type="button"
                onClick={onConfirmDelete}
                disabled={isDeletePending}
                className="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
              >
                {isDeletePending ? (
                  <>
                    <span className="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                    Deleting...
                  </>
                ) : (
                  'Delete'
                )}
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
