import { useQuery } from '@tanstack/react-query';
import { api } from '@/services/api';
import { LoadingSpinner } from '@/components/ui/loading-spinner';

interface ClientDashboardData {
  overview: any; // Replace with actual type
  projects: any[];
  reports: any[];
  communications: any;
}

export function ClientDashboard() {
  const { data, isLoading, error } = useQuery<ClientDashboardData>({
    queryKey: ['clientDashboard'],
    queryFn: async () => {
      const [overview, projects, reports, communications] = await Promise.all([
        api.get('/client/dashboard'),
        api.get('/client/projects'),
        api.get('/client/reports'),
        api.get('/client/communications'),
      ]);
      return {
        overview: overview.data.data,
        projects: projects.data.data,
        reports: reports.data.data,
        communications: communications.data.data,
      };
    },
  });

  if (isLoading) {
    return (
      <div className="flex justify-center items-center h-full">
        <LoadingSpinner size="lg" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-md p-4">
        <h3 className="text-red-800 font-medium">Error</h3>
        <p className="text-red-700">Failed to load Client Dashboard: {error.message}</p>
      </div>
    );
  }

  return (
    <>
      <h1 className="text-2xl font-bold mb-4">Client Dashboard</h1>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {/* Example Widgets */}
        <div className="bg-white p-4 rounded shadow">
          <h2 className="font-semibold text-lg">Project Overview</h2>
          <pre>{JSON.stringify(data?.overview, null, 2)}</pre>
        </div>
        <div className="bg-white p-4 rounded shadow">
          <h2 className="font-semibold text-lg">My Projects</h2>
          <pre>{JSON.stringify(data?.projects, null, 2)}</pre>
        </div>
        <div className="bg-white p-4 rounded shadow">
          <h2 className="font-semibold text-lg">Reports</h2>
          <pre>{JSON.stringify(data?.reports, null, 2)}</pre>
        </div>
        <div className="bg-white p-4 rounded shadow">
          <h2 className="font-semibold text-lg">Communications</h2>
          <pre>{JSON.stringify(data?.communications, null, 2)}</pre>
        </div>
      </div>
    </>
  );
}
