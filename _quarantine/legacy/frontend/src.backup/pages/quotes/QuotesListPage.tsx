import { useState, Suspense, useTransition } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/Card';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import { Badge } from '../../components/ui/Badge';
import { Label } from '../../components/ui/label';
import { Textarea } from '../../components/ui/Textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../../components/ui/Select';
import toast from 'react-hot-toast';
import { apiClient } from '../../shared/api/client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { QuotesKpiStrip } from '../../components/quotes/QuotesKpiStrip';
import { AlertBar } from '../../components/shared/AlertBar';
import { ActivityFeed } from '../../components/shared/ActivityFeed';
import { VisibilitySection } from '../../components/perf/VisibilitySection';
import { useQuotesKpis, useQuotesAlerts, useQuotesActivity, quotesKeys } from '../../entities/app/quotes/hooks';
import { useI18n } from '../../app/i18n-context';
import { 
  MagnifyingGlassIcon,
  PlusIcon,
  PencilIcon,
  TrashIcon,
  EyeIcon,
  CurrencyDollarIcon,
  DocumentTextIcon,
} from '@heroicons/react/24/outline';

interface Quote {
  id: string;
  code: string;
  title: string;
  description: string;
  client_id: string;
  client_name: string;
  project_id?: string;
  project_name?: string;
  status: 'draft' | 'sent' | 'accepted' | 'rejected' | 'expired';
  amount: number;
  currency: string;
  valid_until: string;
  created_at: string;
  updated_at: string;
}

export default function QuotesListPage() {
  const { t } = useI18n();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [isPending, startTransition] = useTransition();
  
  const [filters, setFilters] = useState({
    page: 1,
    per_page: 12,
    search: '',
    status: '',
  });
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [createForm, setCreateForm] = useState({
    title: '',
    description: '',
    client_id: '',
    project_id: '',
    amount: '',
    currency: 'VND',
    valid_until: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  });

  // Fetch Quotes KPIs, Alerts, and Activity
  const { data: kpisData, isLoading: kpisLoading, error: kpisError } = useQuotesKpis();
  const { data: alertsData, isLoading: alertsLoading, error: alertsError } = useQuotesAlerts();
  const { data: activityData, isLoading: activityLoading, error: activityError } = useQuotesActivity(10);

  const { data: quotes, isLoading } = useQuery({
    queryKey: ['quotes', filters],
    queryFn: async () => {
      const response = await apiClient.get('/app/quotes', { params: filters });
      return response.data;
    },
  });

  // Handle refresh
  const handleRefresh = () => {
    startTransition(() => {
      Promise.resolve().then(() => {
        queryClient.invalidateQueries({ queryKey: quotesKeys.all });
        queryClient.invalidateQueries({ queryKey: ['quotes'] });
      });
    });
  };

  const createMutation = useMutation({
    mutationFn: async (data: any) => {
      const response = await apiClient.post('/app/quotes', data);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['quotes'] });
      setIsCreateModalOpen(false);
      toast.success('Quote created successfully');
      setCreateForm({
        title: '',
        description: '',
        client_id: '',
        project_id: '',
        amount: '',
        currency: 'VND',
        valid_until: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      });
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message || 'Failed to create quote');
    },
  });

  const handleCreate = () => {
    createMutation.mutate({
      ...createForm,
      amount: parseFloat(createForm.amount),
    });
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'accepted':
        return 'bg-green-100 text-green-800';
      case 'rejected':
        return 'bg-red-100 text-red-800';
      case 'sent':
        return 'bg-blue-100 text-blue-800';
      case 'expired':
        return 'bg-gray-100 text-gray-800';
      default:
        return 'bg-yellow-100 text-yellow-800';
    }
  };

  return (
    <div className="space-y-6" style={{ contain: 'layout style' }}>
      {/* Universal Page Frame Structure */}
      {/* 1. Page Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
            {t('quotes.title', { defaultValue: 'Quotes' })}
          </h1>
          <p className="text-[var(--color-text-muted)]">
            {t('quotes.description', { defaultValue: 'Manage project quotes and proposals' })}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={handleRefresh} aria-label="Refresh quotes" disabled={isPending}>
            {t('common.refresh', { defaultValue: 'Refresh' })}
          </Button>
          <Button onClick={() => setIsCreateModalOpen(true)}>
            <PlusIcon className="h-5 w-5 mr-2" />
            New Quote
          </Button>
        </div>
      </div>

      {/* 2. KPI Strip */}
      <QuotesKpiStrip
        metrics={kpisData}
        loading={kpisLoading}
        error={kpisError}
        onRefresh={handleRefresh}
        onViewAllQuotes={() => {
          setFilters(prev => ({ ...prev, status: '' }));
          navigate('/app/quotes');
        }}
        onViewPendingQuotes={() => {
          setFilters(prev => ({ ...prev, status: 'sent' }));
          navigate('/app/quotes?status=sent');
        }}
        onViewAcceptedQuotes={() => {
          setFilters(prev => ({ ...prev, status: 'accepted' }));
          navigate('/app/quotes?status=accepted');
        }}
        onViewRejectedQuotes={() => {
          setFilters(prev => ({ ...prev, status: 'rejected' }));
          navigate('/app/quotes?status=rejected');
        }}
      />

      {/* 3. Alert Bar */}
      <VisibilitySection intrinsicHeight={220}>
        <AlertBar
          alerts={alertsData ? {
            ...alertsData,
            data: alertsData.data?.map(alert => ({
              id: alert.id,
              title: alert.title,
              message: alert.message,
              severity: alert.severity,
              status: alert.status,
              type: alert.type,
              source: alert.source,
              createdAt: alert.createdAt,
              readAt: alert.readAt,
              metadata: alert.metadata,
            })) || []
          } : null}
          loading={alertsLoading}
          error={alertsError}
          showDismissAll={true}
        />
      </VisibilitySection>

      {/* 4. Main Content */}
      <Card>
        <CardHeader>
          <CardTitle>All Quotes</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="text-center py-8">Loading...</div>
          ) : (
            <div className="space-y-4">
              {quotes?.data?.map((quote: Quote) => (
                <div
                  key={quote.id}
                  className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 cursor-pointer"
                  onClick={() => navigate(`/app/quotes/${quote.id}`)}
                >
                  <div className="flex-1">
                    <div className="flex items-center gap-2">
                      <h3 className="font-semibold">{quote.title}</h3>
                      <Badge className={getStatusColor(quote.status)}>{quote.status}</Badge>
                    </div>
                    <p className="text-sm text-[var(--color-text-muted)]">{quote.description}</p>
                    <div className="flex items-center gap-4 mt-2 text-sm text-[var(--color-text-muted)]">
                      <span>{quote.client_name}</span>
                      <span>{quote.amount.toLocaleString()} {quote.currency}</span>
                    </div>
                  </div>
                  <Button variant="ghost" size="sm">
                    <EyeIcon className="h-5 w-5" />
                  </Button>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      {/* 5. Activity Feed */}
      <VisibilitySection intrinsicHeight={300}>
        <Suspense fallback={<div>Loading activity...</div>}>
          <ActivityFeed
            activities={activityData ? {
              ...activityData,
              data: activityData.data?.map(activity => ({
                id: activity.id,
                type: activity.type,
                action: activity.action,
                description: activity.description,
                timestamp: activity.timestamp,
                user: activity.user,
              })) || []
            } : null}
            loading={activityLoading}
            error={activityError}
            limit={10}
            showHeader={true}
          />
        </Suspense>
      </VisibilitySection>

      {/* Create Quote Modal */}
      {isCreateModalOpen && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <Card className="w-full max-w-md">
            <CardHeader>
              <CardTitle>Create New Quote</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div>
                <Label>Title</Label>
                <Input
                  value={createForm.title}
                  onChange={(e) => setCreateForm({ ...createForm, title: e.target.value })}
                  placeholder="Quote title"
                />
              </div>
              <div>
                <Label>Description</Label>
                <Textarea
                  value={createForm.description}
                  onChange={(e) => setCreateForm({ ...createForm, description: e.target.value })}
                  placeholder="Quote description"
                />
              </div>
              <div>
                <Label>Amount</Label>
                <Input
                  type="number"
                  value={createForm.amount}
                  onChange={(e) => setCreateForm({ ...createForm, amount: e.target.value })}
                  placeholder="0.00"
                />
              </div>
              <div>
                <Label>Currency</Label>
                <Select value={createForm.currency} onValueChange={(value) => setCreateForm({ ...createForm, currency: value })}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="VND">VND</SelectItem>
                    <SelectItem value="USD">USD</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label>Valid Until</Label>
                <Input
                  type="date"
                  value={createForm.valid_until}
                  onChange={(e) => setCreateForm({ ...createForm, valid_until: e.target.value })}
                />
              </div>
              <div className="flex gap-2">
                <Button onClick={handleCreate} disabled={createMutation.isPending} className="flex-1">
                  {createMutation.isPending ? 'Creating...' : 'Create Quote'}
                </Button>
                <Button variant="outline" onClick={() => setIsCreateModalOpen(false)}>
                  Cancel
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
}

