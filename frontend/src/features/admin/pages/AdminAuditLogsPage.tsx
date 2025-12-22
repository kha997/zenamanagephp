import React, { useState, useMemo } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../shared/ui/button';
import { LoadingSpinner } from '../../../components/shared/LoadingSpinner';
import { useAuditLogs } from '../hooks';
import type { AdminAuditLog } from '../api';

/**
 * AdminAuditLogsPage - Admin UI for viewing audit logs
 * Round 235: Audit Log Framework
 * Round 238: Enhanced with diff view, module filters, search, and entity links
 */

interface DiffItem {
  key: string;
  beforeValue: any;
  afterValue: any;
  status: 'added' | 'removed' | 'changed' | 'unchanged';
}

/**
 * Compute diff between before and after payloads
 */
function computePayloadDiff(
  before?: Record<string, any> | null,
  after?: Record<string, any> | null
): DiffItem[] {
  if (!before && !after) {
    return [];
  }

  const allKeys = new Set<string>();
  if (before) Object.keys(before).forEach((k) => allKeys.add(k));
  if (after) Object.keys(after).forEach((k) => allKeys.add(k));

  const diff: DiffItem[] = [];

  for (const key of allKeys) {
    const beforeVal = before?.[key];
    const afterVal = after?.[key];

    if (beforeVal === undefined && afterVal !== undefined) {
      diff.push({ key, beforeValue: null, afterValue: afterVal, status: 'added' });
    } else if (beforeVal !== undefined && afterVal === undefined) {
      diff.push({ key, beforeValue: beforeVal, afterValue: null, status: 'removed' });
    } else if (JSON.stringify(beforeVal) !== JSON.stringify(afterVal)) {
      diff.push({ key, beforeValue: beforeVal, afterValue: afterVal, status: 'changed' });
    } else {
      diff.push({ key, beforeValue: beforeVal, afterValue: afterVal, status: 'unchanged' });
    }
  }

  return diff;
}

/**
 * Format value for display
 */
function formatValue(value: any): string {
  if (value === null || value === undefined) {
    return 'null';
  }
  if (typeof value === 'object') {
    return JSON.stringify(value, null, 2);
  }
  return String(value);
}

/**
 * Get module from action
 */
function getModuleFromAction(action: string): 'RBAC' | 'Cost' | 'Documents' | 'Tasks' | 'Other' {
  if (action.startsWith('role.') || action.startsWith('user.roles_')) {
    return 'RBAC';
  }
  if (action.startsWith('co.') || action.startsWith('certificate.') || action.startsWith('payment.') || action.startsWith('contract.')) {
    return 'Cost';
  }
  if (action.startsWith('document.')) {
    return 'Documents';
  }
  if (action.startsWith('task.')) {
    return 'Tasks';
  }
  return 'Other';
}

/**
 * Get entity link (placeholder for now - routes can be added later)
 */
function getEntityLink(log: AdminAuditLog): string | null {
  if (!log.entity_type || !log.entity_id) {
    return null;
  }

  // TODO: Implement actual routes when available
  // For now, just return null to show entity info
  return null;
}

export const AdminAuditLogsPage: React.FC = () => {
  const [filters, setFilters] = useState<{
    user_id?: string;
    action?: string;
    entity_type?: string;
    date_from?: string;
    date_to?: string;
    module?: 'RBAC' | 'Cost' | 'Documents' | 'Tasks' | 'All';
    search?: string;
  }>({ module: 'All' });
  const [currentPage, setCurrentPage] = useState(1);
  const [expandedRows, setExpandedRows] = useState<Set<string>>(new Set());

  const { data, isLoading, error } = useAuditLogs({
    ...filters,
    per_page: 15,
    page: currentPage,
  });

  const handleFilterChange = (key: string, value: string) => {
    setFilters((prev) => ({
      ...prev,
      [key]: value || undefined,
    }));
    setCurrentPage(1);
  };

  const toggleRowExpansion = (logId: string) => {
    setExpandedRows((prev) => {
      const next = new Set(prev);
      if (next.has(logId)) {
        next.delete(logId);
      } else {
        next.add(logId);
      }
      return next;
    });
  };

  const formatAction = (action: string): string => {
    return action.split('.').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
  };

  const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleString();
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <LoadingSpinner />
      </div>
    );
  }

  if (error) {
    return (
      <div className="container mx-auto p-6">
        <Card>
          <CardContent className="p-6">
            <p className="text-red-600">Error loading audit logs: {(error as Error).message}</p>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="container mx-auto p-6">
      <Card>
        <CardHeader>
          <CardTitle>Audit Logs</CardTitle>
        </CardHeader>
        <CardContent>
          {/* Filters */}
          <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            <div className="lg:col-span-2">
              <label className="block text-sm font-medium mb-1">Search</label>
              <input
                type="text"
                className="w-full px-3 py-2 border rounded"
                placeholder="Search by action or entity type"
                value={filters.search || ''}
                onChange={(e) => handleFilterChange('search', e.target.value)}
              />
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">Module</label>
              <select
                className="w-full px-3 py-2 border rounded"
                value={filters.module || 'All'}
                onChange={(e) => handleFilterChange('module', e.target.value)}
              >
                <option value="All">All</option>
                <option value="RBAC">RBAC</option>
                <option value="Cost">Cost</option>
                <option value="Documents">Documents</option>
                <option value="Tasks">Tasks</option>
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">Action</label>
              <input
                type="text"
                className="w-full px-3 py-2 border rounded"
                placeholder="Filter by action"
                value={filters.action || ''}
                onChange={(e) => handleFilterChange('action', e.target.value)}
              />
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">Date From</label>
              <input
                type="date"
                className="w-full px-3 py-2 border rounded"
                value={filters.date_from || ''}
                onChange={(e) => handleFilterChange('date_from', e.target.value)}
              />
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">Date To</label>
              <input
                type="date"
                className="w-full px-3 py-2 border rounded"
                value={filters.date_to || ''}
                onChange={(e) => handleFilterChange('date_to', e.target.value)}
              />
            </div>
          </div>

          {/* Table */}
          <div className="overflow-x-auto">
            <table className="w-full border-collapse">
              <thead>
                <tr className="border-b bg-gray-50">
                  <th className="text-left p-2">Time</th>
                  <th className="text-left p-2">User</th>
                  <th className="text-left p-2">Module</th>
                  <th className="text-left p-2">Action</th>
                  <th className="text-left p-2">Entity</th>
                  <th className="text-left p-2">Project</th>
                  <th className="text-left p-2">Details</th>
                </tr>
              </thead>
              <tbody>
                {data?.data.map((log: AdminAuditLog) => {
                  const isExpanded = expandedRows.has(log.id);
                  const diff = computePayloadDiff(log.payload_before, log.payload_after);
                  const hasChanges = diff.some((d) => d.status !== 'unchanged');
                  const module = getModuleFromAction(log.action);
                  const entityLink = getEntityLink(log);

                  return (
                    <React.Fragment key={log.id}>
                      <tr className="border-b hover:bg-gray-50">
                        <td className="p-2 text-sm">{formatDate(log.created_at)}</td>
                        <td className="p-2 text-sm">
                          {log.user ? `${log.user.name} (${log.user.email})` : 'System'}
                        </td>
                        <td className="p-2 text-sm">
                          <span className={`px-2 py-1 rounded text-xs ${
                            module === 'RBAC' ? 'bg-blue-100 text-blue-800' :
                            module === 'Cost' ? 'bg-green-100 text-green-800' :
                            module === 'Documents' ? 'bg-purple-100 text-purple-800' :
                            module === 'Tasks' ? 'bg-orange-100 text-orange-800' :
                            'bg-gray-100 text-gray-800'
                          }`}>
                            {module}
                          </span>
                        </td>
                        <td className="p-2 text-sm">{formatAction(log.action)}</td>
                        <td className="p-2 text-sm">
                          <div>
                            {log.entity_type || 'N/A'}
                            {log.entity_id && (
                              <span className="text-gray-500 text-xs ml-1">
                                ({log.entity_id.substring(0, 8)}...)
                              </span>
                            )}
                          </div>
                          {entityLink && (
                            <a
                              href={entityLink}
                              className="text-blue-600 text-xs hover:underline"
                              target="_blank"
                              rel="noopener noreferrer"
                            >
                              Open entity →
                            </a>
                          )}
                        </td>
                        <td className="p-2 text-sm">
                          {log.project_id ? log.project_id.substring(0, 8) + '...' : 'N/A'}
                        </td>
                        <td className="p-2 text-sm">
                          {hasChanges ? (
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => toggleRowExpansion(log.id)}
                            >
                              {isExpanded ? 'Hide' : 'View'} Details
                            </Button>
                          ) : (
                            'N/A'
                          )}
                        </td>
                      </tr>
                      {isExpanded && hasChanges && (
                        <tr>
                          <td colSpan={7} className="p-4 bg-gray-50">
                            <div className="space-y-4">
                              <h4 className="font-semibold text-sm mb-2">Change Details</h4>
                              <div className="grid grid-cols-2 gap-4">
                                <div>
                                  <h5 className="font-medium text-xs mb-2 text-gray-600">Before</h5>
                                  <div className="bg-white p-3 rounded border text-xs max-h-64 overflow-auto">
                                    {log.payload_before ? (
                                      <pre className="whitespace-pre-wrap">
                                        {JSON.stringify(log.payload_before, null, 2)}
                                      </pre>
                                    ) : (
                                      <span className="text-gray-400">No data</span>
                                    )}
                                  </div>
                                </div>
                                <div>
                                  <h5 className="font-medium text-xs mb-2 text-gray-600">After</h5>
                                  <div className="bg-white p-3 rounded border text-xs max-h-64 overflow-auto">
                                    {log.payload_after ? (
                                      <pre className="whitespace-pre-wrap">
                                        {JSON.stringify(log.payload_after, null, 2)}
                                      </pre>
                                    ) : (
                                      <span className="text-gray-400">No data</span>
                                    )}
                                  </div>
                                </div>
                              </div>
                              <div className="mt-4">
                                <h5 className="font-medium text-xs mb-2 text-gray-600">Diff View</h5>
                                <div className="bg-white p-3 rounded border text-xs max-h-64 overflow-auto">
                                  <table className="w-full text-xs">
                                    <thead>
                                      <tr className="border-b">
                                        <th className="text-left p-1">Key</th>
                                        <th className="text-left p-1">Before</th>
                                        <th className="text-left p-1">After</th>
                                        <th className="text-left p-1">Status</th>
                                      </tr>
                                    </thead>
                                    <tbody>
                                      {diff
                                        .filter((d) => d.status !== 'unchanged')
                                        .map((item, idx) => (
                                          <tr key={idx} className="border-b">
                                            <td className="p-1 font-medium">{item.key}</td>
                                            <td className="p-1">
                                              <span className={item.status === 'removed' || item.status === 'changed' ? 'text-red-600' : ''}>
                                                {formatValue(item.beforeValue)}
                                              </span>
                                            </td>
                                            <td className="p-1">
                                              <span className={item.status === 'added' || item.status === 'changed' ? 'text-green-600' : ''}>
                                                {formatValue(item.afterValue)}
                                              </span>
                                            </td>
                                            <td className="p-1">
                                              <span className={`px-1 py-0.5 rounded text-xs ${
                                                item.status === 'added' ? 'bg-green-100 text-green-800' :
                                                item.status === 'removed' ? 'bg-red-100 text-red-800' :
                                                'bg-yellow-100 text-yellow-800'
                                              }`}>
                                                {item.status}
                                              </span>
                                            </td>
                                          </tr>
                                        ))}
                                    </tbody>
                                  </table>
                                </div>
                              </div>
                            </div>
                          </td>
                        </tr>
                      )}
                    </React.Fragment>
                  );
                })}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {data?.pagination && (
            <div className="mt-4 flex items-center justify-between">
              <div className="text-sm text-gray-600">
                Showing {((data.pagination.current_page - 1) * data.pagination.per_page) + 1} to{' '}
                {Math.min(data.pagination.current_page * data.pagination.per_page, data.pagination.total)} of{' '}
                {data.pagination.total} entries
              </div>
              <div className="flex gap-2">
                <Button
                  onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                  disabled={data.pagination.current_page === 1}
                >
                  Previous
                </Button>
                <Button
                  onClick={() => setCurrentPage((p) => p + 1)}
                  disabled={data.pagination.current_page >= data.pagination.last_page}
                >
                  Next
                </Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
};
