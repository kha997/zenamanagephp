import { useQuery } from '@tanstack/react-query';
import { api } from '@/services/api';
import { LoadingSpinner } from '@/components/ui/loading-spinner';

interface QcDashboardData {
  overview: any; // Replace with actual type
  inspections: any[];
  qualityMetrics: any[];
  reports: any;
}

export function QcDashboard() {
  const { data, isLoading, error } = useQuery<QcDashboardData>({
    queryKey: ['qcDashboard'],
    queryFn: async () => {
      const [overview, inspections, metrics, reports] = await Promise.all([
        api.get('/qc/dashboard'),
        api.get('/qc/inspections'),
        api.get('/qc/metrics'),
        api.get('/qc/reports'),
      ]);
      return {
        overview: overview.data.data,
        inspections: inspections.data.data,
        qualityMetrics: metrics.data.data,
        reports: reports.data.data,
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
        <p className="text-red-700">Failed to load QC Dashboard: {error.message}</p>
      </div>
    );
  }

  return (
    <>
      <h1 className="text-2xl font-bold mb-4">Quality Control Dashboard</h1>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {/* Example Widgets */}
        <div className="bg-white p-4 rounded shadow">
          <h2 className="font-semibold text-lg">QC Overview</h2>
          <pre>{JSON.stringify(data?.overview, null, 2)}</pre>
        </div>
        <div className="bg-white p-4 rounded shadow">
          <h2 className="font-semibold text-lg">Inspections</h2>
          <pre>{JSON.stringify(data?.inspections, null, 2)}</pre>
        </div>
        <div className="bg-white p-4 rounded shadow">
          <h2 className="font-semibold text-lg">Quality Metrics</h2>
          <pre>{JSON.stringify(data?.qualityMetrics, null, 2)}</pre>
        </div>
        <div className="bg-white p-4 rounded shadow">
          <h2 className="font-semibold text-lg">Reports</h2>
          <pre>{JSON.stringify(data?.reports, null, 2)}</pre>
        </div>
      </div>
    </>
  );
}
