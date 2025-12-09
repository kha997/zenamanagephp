import React, { useState, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Select, type SelectOption } from '../../../components/ui/primitives/Select';
import { useActivityFeed } from '../hooks';
import type { ActivityItem } from '../api';
import { formatDistanceToNow } from 'date-fns';

/**
 * ActivityFeedPage Component
 * 
 * Round 248: Global Activity / My Work Feed
 * 
 * Displays activity feed for current user with filters
 */
export const ActivityFeedPage: React.FC = () => {
  const [moduleFilter, setModuleFilter] = useState<'all' | 'tasks' | 'documents' | 'cost' | 'rbac'>('all');
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [page, setPage] = useState<number>(1);
  const perPage = 20;

  const { data, isLoading, error } = useActivityFeed({
    page,
    per_page: perPage,
    module: moduleFilter,
    search: searchQuery || undefined,
  });

  // Module filter options
  const moduleOptions: SelectOption[] = [
    { value: 'all', label: 'All Modules' },
    { value: 'tasks', label: 'Tasks' },
    { value: 'documents', label: 'Documents' },
    { value: 'cost', label: 'Cost' },
    { value: 'rbac', label: 'RBAC' },
  ];

  // Get module badge color
  const getModuleBadgeColor = (module: string): string => {
    switch (module) {
      case 'tasks':
        return 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300';
      case 'documents':
        return 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300';
      case 'cost':
        return 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300';
      case 'rbac':
        return 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300';
      default:
        return 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
    }
  };

  // Get entity link
  const getEntityLink = (item: ActivityItem): string | null => {
    if (!item.entity_id || !item.project_id) {
      return null;
    }

    switch (item.entity_type) {
      case 'task':
      case 'projecttask':
        return `/app/projects/${item.project_id}`; // TODO: Add anchor to task
      case 'document':
        return `/app/projects/${item.project_id}`; // TODO: Add anchor to document
      case 'changeorder':
        return `/app/projects/${item.project_id}/contracts`; // TODO: Add anchor to CO
      case 'contractpaymentcertificate':
        return `/app/projects/${item.project_id}/contracts`; // TODO: Add anchor to certificate
      case 'contractactualpayment':
        return `/app/projects/${item.project_id}/contracts`; // TODO: Add anchor to payment
      default:
        return item.project_id ? `/app/projects/${item.project_id}` : null;
    }
  };

  // Format relative time
  const formatRelativeTime = (timestamp: string): string => {
    try {
      return formatDistanceToNow(new Date(timestamp), { addSuffix: true });
    } catch {
      return timestamp;
    }
  };

  // Handle search
  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setSearchQuery(e.target.value);
    setPage(1); // Reset to first page on search
  };

  // Handle module filter change
  const handleModuleChange = (value: string) => {
    setModuleFilter(value as typeof moduleFilter);
    setPage(1); // Reset to first page on filter change
  };

  if (error) {
    return (
      <div className="container mx-auto p-6">
        <Card>
          <CardContent className="p-6">
            <div className="text-center text-red-600">
              Failed to load activity feed. Please try again.
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="container mx-auto p-6 space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Hoạt động của tôi</h1>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="p-4">
          <div className="flex flex-col md:flex-row gap-4">
            {/* Module Filter */}
            <div className="flex-1">
              <label className="block text-sm font-medium mb-2">Module</label>
              <Select
                value={moduleFilter}
                onChange={(value) => handleModuleChange(value)}
                options={moduleOptions}
              />
            </div>

            {/* Search */}
            <div className="flex-1">
              <label className="block text-sm font-medium mb-2">Search</label>
              <input
                type="text"
                value={searchQuery}
                onChange={handleSearchChange}
                placeholder="Search activities..."
                className="w-full px-3 py-2 border border-gray-300 rounded-md dark:bg-gray-800 dark:border-gray-600"
              />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Activity List */}
      <Card>
        <CardHeader>
          <CardTitle>Activity Feed</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="text-center py-8 text-gray-500">Loading activities...</div>
          ) : !data?.items || data.items.length === 0 ? (
            <div className="text-center py-8 text-gray-500">No activities found</div>
          ) : (
            <div className="space-y-4">
              {data.items.map((item) => {
                const entityLink = getEntityLink(item);
                const content = (
                  <div className="flex items-start gap-4 p-4 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                    {/* Module Badge */}
                    <div className={`px-2 py-1 rounded text-xs font-medium ${getModuleBadgeColor(item.module)}`}>
                      {item.module.toUpperCase()}
                    </div>

                    {/* Content */}
                    <div className="flex-1 min-w-0">
                      <div className="flex items-start justify-between gap-2">
                        <div className="flex-1">
                          <h3 className="font-medium text-gray-900 dark:text-gray-100">
                            {item.title}
                          </h3>
                          <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {item.summary}
                          </p>
                          <div className="flex items-center gap-4 mt-2 text-xs text-gray-500 dark:text-gray-500">
                            {item.project_name && (
                              <span>Project: {item.project_name}</span>
                            )}
                            {item.actor_name && (
                              <span>by {item.actor_name}</span>
                            )}
                            <span title={item.timestamp}>
                              {formatRelativeTime(item.timestamp)}
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                );

                return entityLink ? (
                  <Link key={item.id} to={entityLink} className="block">
                    {content}
                  </Link>
                ) : (
                  <div key={item.id}>{content}</div>
                );
              })}
            </div>
          )}

          {/* Pagination */}
          {data && data.meta && data.meta.total > perPage && (
            <div className="flex items-center justify-between mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
              <div className="text-sm text-gray-600 dark:text-gray-400">
                Showing {((page - 1) * perPage) + 1} to {Math.min(page * perPage, data.meta.total)} of {data.meta.total} activities
              </div>
              <div className="flex gap-2">
                <button
                  onClick={() => setPage(p => Math.max(1, p - 1))}
                  disabled={page === 1}
                  className="px-4 py-2 border border-gray-300 rounded-md disabled:opacity-50 disabled:cursor-not-allowed dark:border-gray-600"
                >
                  Previous
                </button>
                <button
                  onClick={() => setPage(p => p + 1)}
                  disabled={page >= (data.meta.last_page || 1)}
                  className="px-4 py-2 border border-gray-300 rounded-md disabled:opacity-50 disabled:cursor-not-allowed dark:border-gray-600"
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
};
