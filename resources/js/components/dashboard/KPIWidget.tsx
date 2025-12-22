import React from 'react';

export interface KPIData {
  id: string;
  title: string;
  value: number | string;
  change?: number;
  changeType?: 'increase' | 'decrease' | 'neutral';
  icon: string;
  color: 'blue' | 'green' | 'purple' | 'yellow' | 'red';
  loading?: boolean;
  error?: boolean;
}

interface KPIWidgetProps {
  data: KPIData[];
  loading?: boolean;
  error?: boolean;
}

export const KPIWidget: React.FC<KPIWidgetProps> = ({ data, loading = false, error = false }) => {
  if (loading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        {Array.from({ length: 4 }).map((_, index) => (
          <div key={index} className="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
            <div className="p-6">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <div className="w-12 h-12 bg-gray-200 rounded-lg animate-pulse"></div>
                </div>
                <div className="ml-4 flex-1">
                  <div className="h-4 bg-gray-200 rounded animate-pulse mb-2"></div>
                  <div className="h-8 bg-gray-200 rounded animate-pulse"></div>
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>
    );
  }

  if (error) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div className="col-span-full bg-red-50 border border-red-200 rounded-lg p-6">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <i className="fas fa-exclamation-triangle text-red-400 text-xl"></i>
            </div>
            <div className="ml-3">
              <h3 className="text-sm font-medium text-red-800">Failed to load KPIs</h3>
              <p className="text-sm text-red-700 mt-1">Please try refreshing the page.</p>
            </div>
          </div>
        </div>
      </div>
    );
  }

  const getColorClasses = (color: KPIData['color']) => {
    const colorMap = {
      blue: 'bg-blue-100 text-blue-600',
      green: 'bg-green-100 text-green-600',
      purple: 'bg-purple-100 text-purple-600',
      yellow: 'bg-yellow-100 text-yellow-600',
      red: 'bg-red-100 text-red-600'
    };
    return colorMap[color] || colorMap.blue;
  };

  const getChangeIcon = (changeType: KPIData['changeType']) => {
    switch (changeType) {
      case 'increase':
        return 'fas fa-arrow-up text-green-600';
      case 'decrease':
        return 'fas fa-arrow-down text-red-600';
      default:
        return 'fas fa-minus text-gray-600';
    }
  };

  const formatValue = (value: number | string): string => {
    if (typeof value === 'number') {
      return value.toLocaleString();
    }
    return value;
  };

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      {data.map((kpi) => (
        <div 
          key={kpi.id} 
          className="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200 hover:shadow-md transition-shadow duration-200"
        >
          <div className="p-6">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <div className={`w-12 h-12 ${getColorClasses(kpi.color)} rounded-lg flex items-center justify-center`}>
                  <i className={`${kpi.icon} text-lg`}></i>
                </div>
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-gray-500">{kpi.title}</p>
                <p className="text-2xl font-bold text-gray-900">
                  {formatValue(kpi.value)}
                </p>
              </div>
            </div>
            {kpi.change !== undefined && (
              <div className="mt-4">
                <div className="flex items-center text-sm">
                  <i className={`${getChangeIcon(kpi.changeType)} mr-1 text-xs`}></i>
                  <span className={`${
                    kpi.changeType === 'increase' ? 'text-green-600' : 
                    kpi.changeType === 'decrease' ? 'text-red-600' : 
                    'text-gray-600'
                  }`}>
                    {Math.abs(kpi.change)}%
                  </span>
                  <span className="text-gray-500 ml-1">vs last month</span>
                </div>
              </div>
            )}
          </div>
        </div>
      ))}
    </div>
  );
};

export default KPIWidget;
