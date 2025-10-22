import { useNavigate } from 'react-router-dom';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import toast from 'react-hot-toast';
import { 
  useAdminDashboardSummary,
  useAdminDashboardExport
} from '@/entities/admin/dashboard/hooks';
import { 
  UsersIcon, 
  FolderIcon, 
  ChartBarIcon, 
  Cog6ToothIcon,
  BuildingOfficeIcon,
  DocumentArrowDownIcon
} from '@heroicons/react/24/outline';

export default function AdminDashboardPage() {
  const navigate = useNavigate();
  
  const { data: summaryResponse, isLoading: summaryLoading, error: summaryError } = useAdminDashboardSummary();
  const { data: exportResponse } = useAdminDashboardExport();

  const summary = summaryResponse?.data;
  const exportUrl = exportResponse?.data?.export_url;

  const handleExport = () => {
    if (exportUrl) {
      // TODO: Implement actual export download
      toast('Export functionality coming soon');
    } else {
      toast.error('Export URL not available');
    }
  };

  const handleQuickAction = (action: string) => {
    switch (action) {
      case 'users':
        navigate('/admin/users');
        break;
      case 'projects':
        navigate('/admin/projects');
        break;
      case 'tenants':
        navigate('/admin/tenants');
        break;
      case 'settings':
        navigate('/admin/settings');
        break;
      default:
        toast(`${action} functionality coming soon`);
    }
  };

  if (summaryLoading) {
    return (
      <div className="space-y-6">
        <div>
          <h2 className="text-3xl font-bold text-gray-900">Admin Dashboard</h2>
          <p className="text-gray-600">System overview and quick actions</p>
        </div>
        <div className="flex items-center justify-center h-64">
          <div className="text-gray-500">Loading dashboard...</div>
        </div>
      </div>
    );
  }

  if (summaryError) {
    return (
      <div className="space-y-6">
        <div>
          <h2 className="text-3xl font-bold text-gray-900">Admin Dashboard</h2>
          <p className="text-gray-600">System overview and quick actions</p>
        </div>
        <Card>
          <CardContent className="text-center py-12">
            <div className="text-red-500">
              <h3 className="text-lg font-medium mb-2">Failed to load dashboard</h3>
              <p className="text-gray-600">There was an error loading the dashboard data. Please try again.</p>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-3xl font-bold text-gray-900">Admin Dashboard</h2>
          <p className="text-gray-600">System overview and quick actions</p>
        </div>
        <Button onClick={handleExport}>
          <DocumentArrowDownIcon className="h-4 w-4 mr-2" />
          Export
        </Button>
      </div>

      {/* Quick Stats */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Users</CardTitle>
            <UsersIcon className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{summary?.total_users || 0}</div>
            <p className="text-xs text-muted-foreground">
              Across all tenants
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Projects</CardTitle>
            <FolderIcon className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{summary?.total_projects || 0}</div>
            <p className="text-xs text-muted-foreground">
              System-wide
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Active Tenants</CardTitle>
            <BuildingOfficeIcon className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{summary?.active_tenants || 0}</div>
            <p className="text-xs text-muted-foreground">
              of {summary?.total_tenants || 0} total
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Active Sessions</CardTitle>
            <ChartBarIcon className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{summary?.active_sessions || 0}</div>
            <p className="text-xs text-muted-foreground">
              Currently online
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Quick Actions */}
      <Card>
        <CardHeader>
          <CardTitle>Quick Actions</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <Button 
              variant="outline" 
              className="h-20 flex flex-col items-center justify-center"
              onClick={() => handleQuickAction('users')}
            >
              <UsersIcon className="h-6 w-6 mb-2" />
              Manage Users
            </Button>
            <Button 
              variant="outline" 
              className="h-20 flex flex-col items-center justify-center"
              onClick={() => handleQuickAction('tenants')}
            >
              <BuildingOfficeIcon className="h-6 w-6 mb-2" />
              Manage Tenants
            </Button>
            <Button 
              variant="outline" 
              className="h-20 flex flex-col items-center justify-center"
              onClick={() => handleQuickAction('projects')}
            >
              <FolderIcon className="h-6 w-6 mb-2" />
              View Projects
            </Button>
            <Button 
              variant="outline" 
              className="h-20 flex flex-col items-center justify-center"
              onClick={() => handleQuickAction('settings')}
            >
              <Cog6ToothIcon className="h-6 w-6 mb-2" />
              Settings
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
