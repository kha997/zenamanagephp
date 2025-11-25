import { useParams, useNavigate } from 'react-router-dom';
import { Card, CardContent, CardHeader, CardTitle } from '../../shared/ui/card';
import { Button } from '../../shared/ui/button';
import { Badge } from '../../shared/ui/badge';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../shared/api/client';
import toast from 'react-hot-toast';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';

export default function ClientDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const { data: client, isLoading } = useQuery({
    queryKey: ['clients', id],
    queryFn: async () => {
      const response = await apiClient.get(`/app/clients/${id}`);
      return response.data;
    },
  });

  const updateLifecycleMutation = useMutation({
    mutationFn: async (stage: string) => {
      const response = await apiClient.patch(`/app/clients/${id}/lifecycle-stage`, { lifecycle_stage: stage });
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['clients', id] });
      toast.success('Lifecycle stage updated');
    },
  });

  if (isLoading) {
    return <div className="text-center py-8">Loading...</div>;
  }

  if (!client?.data) {
    return <div className="text-center py-8">Client not found</div>;
  }

  const clientData = client.data;

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
    <div className="space-y-6">
      <div className="flex items-center gap-4">
        <Button variant="ghost" onClick={() => navigate('/app/clients')}>
          <ArrowLeftIcon className="h-5 w-5 mr-2" />
          Back
        </Button>
        <div>
          <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">{clientData.name}</h1>
          <p className="text-[var(--color-text-muted)]">Client Details</p>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card className="md:col-span-2">
          <CardHeader>
            <CardTitle>Client Information</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div>
              <h3 className="font-semibold mb-2">Email</h3>
              <p>{clientData.email}</p>
            </div>
            {clientData.phone && (
              <div>
                <h3 className="font-semibold mb-2">Phone</h3>
                <p>{clientData.phone}</p>
              </div>
            )}
            {clientData.company && (
              <div>
                <h3 className="font-semibold mb-2">Company</h3>
                <p>{clientData.company}</p>
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Lifecycle Stage</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            <Badge className={getLifecycleColor(clientData.lifecycle_stage)}>{clientData.lifecycle_stage}</Badge>
            <div className="space-y-2">
              <Button
                variant={clientData.lifecycle_stage === 'lead' ? 'default' : 'outline'}
                size="sm"
                className="w-full"
                onClick={() => updateLifecycleMutation.mutate('lead')}
              >
                Lead
              </Button>
              <Button
                variant={clientData.lifecycle_stage === 'opportunity' ? 'default' : 'outline'}
                size="sm"
                className="w-full"
                onClick={() => updateLifecycleMutation.mutate('opportunity')}
              >
                Opportunity
              </Button>
              <Button
                variant={clientData.lifecycle_stage === 'customer' ? 'default' : 'outline'}
                size="sm"
                className="w-full"
                onClick={() => updateLifecycleMutation.mutate('customer')}
              >
                Customer
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

