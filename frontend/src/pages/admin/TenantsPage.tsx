import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import toast from 'react-hot-toast';
import { 
  useAdminTenants, 
  useDeleteAdminTenant, 
  useBulkUpdateTenantStatus,
  useSuspendTenant,
  useActivateTenant
} from '@/entities/admin/tenants/hooks';
import type { AdminTenantsFilters } from '@/entities/admin/tenants/types';
import { 
  PlusIcon,
  PencilIcon,
  TrashIcon,
  BuildingOfficeIcon,
  CheckCircleIcon,
  XCircleIcon
} from '@heroicons/react/24/outline';

export default function AdminTenantsPage() {
  const [filters, setFilters] = useState<AdminTenantsFilters>({
    page: 1,
    per_page: 10
  });
  const [selectedTenants, setSelectedTenants] = useState<number[]>([]);

  const { data: tenantsResponse, isLoading, error } = useAdminTenants(filters);
  const deleteTenantMutation = useDeleteAdminTenant();
  const bulkUpdateStatusMutation = useBulkUpdateTenantStatus();
  const suspendTenantMutation = useSuspendTenant();
  const activateTenantMutation = useActivateTenant();

  const tenants = tenantsResponse?.data || [];
  const meta = tenantsResponse?.meta;

  const handleSearch = (search: string) => {
    setFilters(prev => ({ ...prev, search, page: 1 }));
  };

  const handleStatusFilter = (status: string) => {
    setFilters(prev => ({ 
      ...prev, 
      status: status === 'all' ? undefined : status,
      page: 1 
    }));
  };

  const handlePlanFilter = (plan: string) => {
    setFilters(prev => ({ 
      ...prev, 
      plan: plan === 'all' ? undefined : plan,
      page: 1 
    }));
  };

  const handleDeleteTenant = async (tenantId: number) => {
    if (window.confirm('Are you sure you want to delete this tenant? This action cannot be undone.')) {
      try {
        await deleteTenantMutation.mutateAsync(tenantId);
        toast.success('Tenant deleted successfully');
      } catch (error) {
        toast.error('Failed to delete tenant');
      }
    }
  };

  const handleToggleStatus = async (tenantId: number, currentStatus: string) => {
    try {
      if (currentStatus === 'active') {
        await suspendTenantMutation.mutateAsync({ id: tenantId });
        toast.success('Tenant suspended successfully');
      } else {
        await activateTenantMutation.mutateAsync(tenantId);
        toast.success('Tenant activated successfully');
      }
    } catch (error) {
      toast.error('Failed to update tenant status');
    }
  };

  const handleBulkStatusUpdate = async (status: 'active' | 'inactive' | 'suspended') => {
    if (selectedTenants.length === 0) return;
    
    try {
      await bulkUpdateStatusMutation.mutateAsync({ tenantIds: selectedTenants, status });
      toast.success(`${selectedTenants.length} tenants have been ${status}`);
      setSelectedTenants([]);
    } catch (error) {
      toast.error('Failed to update tenants');
    }
  };

  const handleSelectTenant = (tenantId: number) => {
    setSelectedTenants(prev => 
      prev.includes(tenantId) 
        ? prev.filter(id => id !== tenantId)
        : [...prev, tenantId]
    );
  };

  const handleSelectAll = () => {
    if (selectedTenants.length === tenants.length) {
      setSelectedTenants([]);
    } else {
      setSelectedTenants(tenants.map(tenant => tenant.id));
    }
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h2 className="text-3xl font-bold text-gray-900">Tenant Management</h2>
            <p className="text-gray-600">Manage multi-tenant organizations</p>
          </div>
        </div>
        <div className="flex items-center justify-center h-64">
          <div className="text-gray-500">Loading tenants...</div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h2 className="text-3xl font-bold text-gray-900">Tenant Management</h2>
            <p className="text-gray-600">Manage multi-tenant organizations</p>
          </div>
        </div>
        <Card>
          <CardContent className="text-center py-12">
            <div className="text-red-500">
              <h3 className="text-lg font-medium mb-2">Failed to load tenants</h3>
              <p className="text-gray-600">There was an error loading the tenants. Please try again.</p>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'active':
        return 'default';
      case 'inactive':
        return 'secondary';
      case 'suspended':
        return 'destructive';
      default:
        return 'secondary';
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'active':
        return <CheckCircleIcon className="h-4 w-4 text-green-600" />;
      case 'inactive':
        return <XCircleIcon className="h-4 w-4 text-gray-400" />;
      case 'suspended':
        return <XCircleIcon className="h-4 w-4 text-red-600" />;
      default:
        return <XCircleIcon className="h-4 w-4 text-gray-400" />;
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-3xl font-bold text-gray-900">Tenant Management</h2>
          <p className="text-gray-600">Manage multi-tenant organizations</p>
        </div>
        <Button>
          <PlusIcon className="h-4 w-4 mr-2" />
          Add Tenant
        </Button>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Tenants</CardTitle>
            <BuildingOfficeIcon className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{meta?.total || 0}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Active</CardTitle>
            <CheckCircleIcon className="h-4 w-4 text-green-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {tenants.filter(t => t.status === 'active').length}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Suspended</CardTitle>
            <XCircleIcon className="h-4 w-4 text-red-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {tenants.filter(t => t.status === 'suspended').length}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Users</CardTitle>
            <BuildingOfficeIcon className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {tenants.reduce((sum, t) => sum + t.user_count, 0)}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Tenants Table */}
      <Card>
        <CardHeader>
          <CardTitle>Tenants</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b">
                  <th className="text-left py-3 px-4">Name</th>
                  <th className="text-left py-3 px-4">Domain</th>
                  <th className="text-left py-3 px-4">Status</th>
                  <th className="text-left py-3 px-4">Plan</th>
                  <th className="text-left py-3 px-4">Users</th>
                  <th className="text-left py-3 px-4">Created</th>
                  <th className="text-left py-3 px-4">Actions</th>
                </tr>
              </thead>
              <tbody>
                {tenants.map(tenant => (
                  <tr key={tenant.id} className="border-b hover:bg-gray-50">
                    <td className="py-3 px-4 font-medium">{tenant.name}</td>
                    <td className="py-3 px-4 text-gray-600">{tenant.domain}</td>
                    <td className="py-3 px-4">
                      <div className="flex items-center space-x-2">
                        {getStatusIcon(tenant.status)}
                        <Badge variant={getStatusColor(tenant.status)}>
                          {tenant.status}
                        </Badge>
                      </div>
                    </td>
                    <td className="py-3 px-4">
                      <Badge variant="outline">{tenant.plan}</Badge>
                    </td>
                    <td className="py-3 px-4">{tenant.user_count}</td>
                    <td className="py-3 px-4 text-gray-600">
                      {new Date(tenant.created_at).toLocaleDateString()}
                    </td>
                    <td className="py-3 px-4">
                      <div className="flex space-x-2">
                        <Button 
                          variant="ghost" 
                          size="sm"
                          onClick={() => handleToggleStatus(tenant.id, tenant.status)}
                          disabled={suspendTenantMutation.isPending || activateTenantMutation.isPending}
                        >
                          {tenant.status === 'active' ? 'Suspend' : 'Activate'}
                        </Button>
                        <Button variant="ghost" size="sm">
                          <PencilIcon className="h-4 w-4" />
                        </Button>
                        <Button 
                          variant="ghost" 
                          size="sm" 
                          className="text-red-600"
                          onClick={() => handleDeleteTenant(tenant.id)}
                          disabled={deleteTenantMutation.isPending}
                        >
                          <TrashIcon className="h-4 w-4" />
                        </Button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          
          {/* Pagination */}
          {meta && meta.last_page > 1 && (
            <div className="flex justify-between items-center mt-4">
              <div className="text-sm text-gray-600">
                Showing {((meta.current_page - 1) * meta.per_page) + 1} to {Math.min(meta.current_page * meta.per_page, meta.total)} of {meta.total} results
              </div>
              <div className="flex space-x-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setFilters(prev => ({ ...prev, page: prev.page! - 1 }))}
                  disabled={meta.current_page <= 1}
                >
                  Previous
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setFilters(prev => ({ ...prev, page: prev.page! + 1 }))}
                  disabled={meta.current_page >= meta.last_page}
                >
                  Next
                </Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      {tenants.length === 0 && !isLoading && (
        <Card>
          <CardContent className="text-center py-12">
            <div className="text-gray-500">
              <BuildingOfficeIcon className="h-12 w-12 mx-auto mb-4 text-gray-300" />
              <h3 className="text-lg font-medium mb-2">No tenants found</h3>
              <p className="text-gray-600">Try adjusting your filters or add a new tenant.</p>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
