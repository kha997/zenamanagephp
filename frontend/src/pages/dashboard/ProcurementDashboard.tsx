import { useQuery } from '@tanstack/react-query';
import { api } from '@/services/api';
import { LoadingSpinner } from '@/components/ui/loading-spinner';

interface ProcurementDashboardData {
  overview: any; // Replace with actual type
  purchaseOrders: any[];
  suppliers: any[];
  inventory: any;
}

export function ProcurementDashboard() {
  const { data, isLoading, error } = useQuery<ProcurementDashboardData>({
    queryKey: ['procurementDashboard'],
    queryFn: async () => {
      const [overview, orders, suppliers, inventory] = await Promise.all([
        api.get('/procurement/dashboard'),
        api.get('/procurement/orders'),
        api.get('/procurement/suppliers'),
        api.get('/procurement/inventory'),
      ]);
      return {
        overview: overview.data.data,
        purchaseOrders: orders.data.data,
        suppliers: suppliers.data.data,
        inventory: inventory.data.data,
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
        <p className="text-red-700">Failed to load Procurement Dashboard: {error.message}</p>
      </div>
    );
  }

  return (
    <>
      <h1 className="text-2xl font-bold mb-4">Procurement Dashboard</h1>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {/* Example Widgets */}
        <div className="bg-white p-4 rounded shadow">
          <h2 className="font-semibold text-lg">Procurement Overview</h2>
          <pre>{JSON.stringify(data?.overview, null, 2)}</pre>
        </div>
        <div className="bg-white p-4 rounded shadow">
          <h2 className="font-semibold text-lg">Purchase Orders</h2>
          <pre>{JSON.stringify(data?.purchaseOrders, null, 2)}</pre>
        </div>
        <div className="bg-white p-4 rounded shadow">
          <h2 className="font-semibold text-lg">Suppliers</h2>
          <pre>{JSON.stringify(data?.suppliers, null, 2)}</pre>
        </div>
        <div className="bg-white p-4 rounded shadow">
          <h2 className="font-semibold text-lg">Inventory</h2>
          <pre>{JSON.stringify(data?.inventory, null, 2)}</pre>
        </div>
      </div>
    </>
  );
}
