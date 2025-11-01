import { useQuery } from '@tanstack/react-query';
import { api } from '@/services/api';
import { LoadingSpinner } from '@/components/ui/loading-spinner';

interface FinanceDashboardData {
  overview: any; // Replace with actual type
  budgets: any[];
  expenses: any[];
  reports: any;
}

export function FinanceDashboard() {
  const { data, isLoading, error } = useQuery<FinanceDashboardData>({
    queryKey: ['financeDashboard'],
    queryFn: async () => {
      const [overview, budgets, expenses, reports] = await Promise.all([
        api.get('/finance/dashboard'),
        api.get('/finance/budgets'),
        api.get('/finance/expenses'),
        api.get('/finance/reports'),
      ]);
      return {
        overview: overview.data.data,
        budgets: budgets.data.data,
        expenses: expenses.data.data,
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
        <p className="text-red-700">Failed to load Finance Dashboard: {error.message}</p>
      </div>
    );
  }

  return (
    <>
      <h1 className="text-2xl font-bold mb-4">Finance Dashboard</h1>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {/* Example Widgets */}
        <div className="bg-white p-4 rounded shadow">
          <h2 className="font-semibold text-lg">Finance Overview</h2>
          <pre>{JSON.stringify(data?.overview, null, 2)}</pre>
        </div>
        <div className="bg-white p-4 rounded shadow">
          <h2 className="font-semibold text-lg">Budgets</h2>
          <pre>{JSON.stringify(data?.budgets, null, 2)}</pre>
        </div>
        <div className="bg-white p-4 rounded shadow">
          <h2 className="font-semibold text-lg">Expenses</h2>
          <pre>{JSON.stringify(data?.expenses, null, 2)}</pre>
        </div>
        <div className="bg-white p-4 rounded shadow">
          <h2 className="font-semibold text-lg">Financial Reports</h2>
          <pre>{JSON.stringify(data?.reports, null, 2)}</pre>
        </div>
      </div>
    </>
  );
}
