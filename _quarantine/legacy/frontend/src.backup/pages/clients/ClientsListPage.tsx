import { useState, Suspense, useTransition } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardContent, CardHeader, CardTitle } from '../../shared/ui/card';
import { Button } from '../../shared/ui/button';
import { Input } from '../../components/ui/Input';
import { Badge } from '../../shared/ui/badge';
import { Label } from '../../components/ui/label';
import { Textarea } from '../../components/ui/Textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../../components/ui/Select';
import toast from 'react-hot-toast';
import { apiClient } from '../../shared/api/client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ClientsKpiStrip } from '../../components/clients/ClientsKpiStrip';
import { AlertBar } from '../../components/shared/AlertBar';
import { ActivityFeed } from '../../components/shared/ActivityFeed';
import { VisibilitySection } from '../../components/perf/VisibilitySection';
import { useClientsKpis, useClientsAlerts, useClientsActivity, clientsKeys } from '../../entities/app/clients/hooks';
import { useI18n } from '../../app/i18n-context';
import { 
  MagnifyingGlassIcon,
  PlusIcon,
  PencilIcon,
  TrashIcon,
  EyeIcon,
  BuildingOfficeIcon,
} from '@heroicons/react/24/outline';

interface Client {
  id: string;
  name: string;
  email: string;
  phone?: string;
  company?: string;
  lifecycle_stage: string;
  created_at: string;
  updated_at: string;
}

export default function ClientsListPage() {
  const { t } = useI18n();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [isPending, startTransition] = useTransition();
  
  const [filters, setFilters] = useState({
    page: 1,
    per_page: 12,
    search: '',
    lifecycle_stage: '',
  });
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [createForm, setCreateForm] = useState({
    name: '',
    email: '',
    phone: '',
    company: '',
    lifecycle_stage: 'lead',
  });

  // Fetch Clients KPIs, Alerts, and Activity
  const { data: kpisData, isLoading: kpisLoading, error: kpisError } = useClientsKpis();
  const { data: alertsData, isLoading: alertsLoading, error: alertsError } = useClientsAlerts();
  const { data: activityData, isLoading: activityLoading, error: activityError } = useClientsActivity(10);

  const { data: clients, isLoading } = useQuery({
    queryKey: ['clients', filters],
    queryFn: async () => {
      const response = await apiClient.get('/app/clients', { params: filters });
      return response.data;
    },
  });

  // Handle refresh
  const handleRefresh = () => {
    startTransition(() => {
      Promise.resolve().then(() => {
        queryClient.invalidateQueries({ queryKey: clientsKeys.all });
        queryClient.invalidateQueries({ queryKey: ['clients'] });
      });
    });
  };

  const createMutation = useMutation({
    mutationFn: async (data: any) => {
      const response = await apiClient.post('/app/clients', data);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['clients'] });
      setIsCreateModalOpen(false);
      toast.success('Client created successfully');
      setCreateForm({
        name: '',
        email: '',
        phone: '',
        company: '',
        lifecycle_stage: 'lead',
      });
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message || 'Failed to create client');
    },
  });

  const handleCreate = () => {
    createMutation.mutate(createForm);
  };

  const getLifecycleColor = (stage: string) => {
    switch (stage) {
      case 'customer':
        return 'bg-green-100 text-green-800';
      case 'opportunity':
        return 'bg-blue-100 text-blue-800';
      case 'lead':
        return 'bg-yellow-100 text-yellow-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  return (
    <div className="space-y-6" style={{ contain: 'layout style' }}>
      {/* Universal Page Frame Structure */}
      {/* 1. Page Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
            {t('clients.title', { defaultValue: 'Clients' })}
          </h1>
          <p className="text-[var(--color-text-muted)]">
            {t('clients.description', { defaultValue: 'Manage your clients and relationships' })}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={handleRefresh} aria-label="Refresh clients" disabled={isPending}>
            {t('common.refresh', { defaultValue: 'Refresh' })}
          </Button>
          <Button onClick={() => setIsCreateModalOpen(true)}>
            <PlusIcon className="h-4 w-4 mr-2" />
            {t('clients.create', { defaultValue: 'New Client' })}
          </Button>
        </div>
      </div>

      {/* 2. KPI Strip */}
      <ClientsKpiStrip
        metrics={kpisData}
        loading={kpisLoading}
        error={kpisError}
        onRefresh={handleRefresh}
        onViewAllClients={() => {
          setFilters(prev => ({ ...prev, lifecycle_stage: '' }));
          navigate('/app/clients');
        }}
        onViewActiveClients={() => {
          setFilters(prev => ({ ...prev, lifecycle_stage: 'customer' }));
          navigate('/app/clients?lifecycle_stage=customer');
        }}
        onViewNewClients={() => {
          navigate('/app/clients');
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
          <CardTitle>All Clients</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="text-center py-8">Loading...</div>
          ) : (
            <div className="space-y-4">
              {clients?.data?.map((client: Client) => (
                <div
                  key={client.id}
                  className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 cursor-pointer"
                  onClick={() => navigate(`/app/clients/${client.id}`)}
                >
                  <div className="flex-1">
                    <div className="flex items-center gap-2">
                      <h3 className="font-semibold">{client.name}</h3>
                      <Badge className={getLifecycleColor(client.lifecycle_stage)}>{client.lifecycle_stage}</Badge>
                    </div>
                    <p className="text-sm text-[var(--color-text-muted)]">{client.email}</p>
                    {client.company && (
                      <p className="text-sm text-[var(--color-text-muted)]">{client.company}</p>
                    )}
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

      {isCreateModalOpen && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <Card className="w-full max-w-md">
            <CardHeader>
              <CardTitle>Create New Client</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div>
                <Label>Name</Label>
                <Input
                  value={createForm.name}
                  onChange={(e) => setCreateForm({ ...createForm, name: e.target.value })}
                  placeholder="Client name"
                />
              </div>
              <div>
                <Label>Email</Label>
                <Input
                  type="email"
                  value={createForm.email}
                  onChange={(e) => setCreateForm({ ...createForm, email: e.target.value })}
                  placeholder="client@example.com"
                />
              </div>
              <div>
                <Label>Phone</Label>
                <Input
                  value={createForm.phone}
                  onChange={(e) => setCreateForm({ ...createForm, phone: e.target.value })}
                  placeholder="Phone number"
                />
              </div>
              <div>
                <Label>Company</Label>
                <Input
                  value={createForm.company}
                  onChange={(e) => setCreateForm({ ...createForm, company: e.target.value })}
                  placeholder="Company name"
                />
              </div>
              <div>
                <Label>Lifecycle Stage</Label>
                <Select value={createForm.lifecycle_stage} onValueChange={(value) => setCreateForm({ ...createForm, lifecycle_stage: value })}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="lead">Lead</SelectItem>
                    <SelectItem value="opportunity">Opportunity</SelectItem>
                    <SelectItem value="customer">Customer</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="flex gap-2">
                <Button onClick={handleCreate} disabled={createMutation.isPending} className="flex-1">
                  {createMutation.isPending ? 'Creating...' : 'Create Client'}
                </Button>
                <Button variant="outline" onClick={() => setIsCreateModalOpen(false)}>
                  Cancel
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      )}
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
    </div>
  );
}

