import React from 'react';

export interface ActivityItem {
  id: string;
  type: 'project' | 'task' | 'user' | 'system';
  action: string;
  description: string;
  timestamp: string;
  user: {
    id: string;
    name: string;
    avatar?: string;
  };
  url?: string;
}

interface ActivityListProps {
  data: ActivityItem[];
  loading?: boolean;
  error?: boolean;
  maxItems?: number;
  showLoadMore?: boolean;
  onLoadMore?: () => void;
}

export const ActivityList: React.FC<ActivityListProps> = ({ 
  data, 
  loading = false, 
  error = false,
  maxItems = 10,
  showLoadMore = false,
  onLoadMore
}) => {
  const getActivityIcon = (type: ActivityItem['type']) => {
    const iconMap = {
      project: 'fas fa-project-diagram',
      task: 'fas fa-tasks',
      user: 'fas fa-user',
      system: 'fas fa-cog'
    };
    return iconMap[type] || 'fas fa-circle';
  };

  const getActivityColor = (type: ActivityItem['type']) => {
    const colorMap = {
      project: 'bg-blue-100 text-blue-600',
      task: 'bg-green-100 text-green-600',
      user: 'bg-purple-100 text-purple-600',
      system: 'bg-gray-100 text-gray-600'
    };
    return colorMap[type] || 'bg-gray-100 text-gray-600';
  };

  const formatTimeAgo = (timestamp: string): string => {
    const now = new Date();
    const time = new Date(timestamp);
    const diff = now.getTime() - time.getTime();
    
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);
    
    if (minutes < 60) return `${minutes}m ago`;
    if (hours < 24) return `${hours}h ago`;
    return `${days}d ago`;
  };

  const handleItemClick = (item: ActivityItem) => {
    if (item.url) {
      window.location.href = item.url;
    }
  };

  if (loading) {
    return (
      <div className="bg-white shadow-sm rounded-lg border border-gray-200">
        <div className="px-6 py-4 border-b border-gray-200">
          <div className="h-6 bg-gray-200 rounded animate-pulse"></div>
        </div>
        <div className="p-6">
          <div className="space-y-4">
            {Array.from({ length: 5 }).map((_, index) => (
              <div key={index} className="flex items-start space-x-3">
                <div className="flex-shrink-0">
                  <div className="w-8 h-8 bg-gray-200 rounded-full animate-pulse"></div>
                </div>
                <div className="flex-1 min-w-0">
                  <div className="h-4 bg-gray-200 rounded animate-pulse mb-2"></div>
                  <div className="h-3 bg-gray-200 rounded animate-pulse w-1/2"></div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-white shadow-sm rounded-lg border border-gray-200">
        <div className="px-6 py-4 border-b border-gray-200">
          <h2 className="text-lg font-semibold text-gray-900">Recent Activity</h2>
        </div>
        <div className="p-6">
          <div className="text-center py-8">
            <div className="w-16 h-16 mx-auto mb-4 bg-red-100 rounded-full flex items-center justify-center">
              <i className="fas fa-exclamation-triangle text-2xl text-red-400"></i>
            </div>
            <h3 className="text-lg font-medium text-gray-900 mb-2">Failed to load activity</h3>
            <p className="text-gray-500">Please try refreshing the page.</p>
          </div>
        </div>
      </div>
    );
  }

  const displayData = data.slice(0, maxItems);

  return (
    <div className="bg-white shadow-sm rounded-lg border border-gray-200">
      <div className="px-6 py-4 border-b border-gray-200">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold text-gray-900">Recent Activity</h2>
          {showLoadMore && onLoadMore && (
            <button
              onClick={onLoadMore}
              className="text-sm text-blue-600 hover:text-blue-500 font-medium"
            >
              Load more
            </button>
          )}
        </div>
      </div>
      <div className="p-6">
        {displayData.length === 0 ? (
          <div className="text-center py-8">
            <div className="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
              <i className="fas fa-history text-2xl text-gray-400"></i>
            </div>
            <h3 className="text-lg font-medium text-gray-900 mb-2">No recent activity</h3>
            <p className="text-gray-500">Activity will appear here as you work on projects.</p>
          </div>
        ) : (
          <div className="space-y-4">
            {displayData.map((item) => (
              <div
                key={item.id}
                className={`flex items-start space-x-3 p-3 rounded-lg transition-colors ${
                  item.url ? 'hover:bg-gray-50 cursor-pointer' : ''
                }`}
                onClick={() => handleItemClick(item)}
              >
                <div className="flex-shrink-0">
                  <div className={`w-8 h-8 ${getActivityColor(item.type)} rounded-full flex items-center justify-center`}>
                    <i className={`${getActivityIcon(item.type)} text-sm`}></i>
                  </div>
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm text-gray-900">{item.description}</p>
                  <div className="flex items-center mt-1 space-x-2">
                    <p className="text-xs text-gray-500">{formatTimeAgo(item.timestamp)}</p>
                    <span className="text-xs text-gray-400">•</span>
                    <p className="text-xs text-gray-500">{item.user.name}</p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};

export default ActivityList;
