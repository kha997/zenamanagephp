import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../shared/ui/button';
import { LoadingSpinner } from '../../../components/shared/LoadingSpinner';
import { usePermissionInspector, useUsers } from '../hooks';
import { adminRolesPermissionsApi } from '../api';
import { useNavigate } from 'react-router-dom';

/**
 * PermissionInspectorPage - Admin UI for inspecting user permissions
 * Round 236: Permission Inspector
 */
export const PermissionInspectorPage: React.FC = () => {
  const [selectedUserId, setSelectedUserId] = useState<string | null>(null);
  const [filter, setFilter] = useState<'cost' | 'document' | 'task' | 'project' | 'user' | 'system' | ''>('');
  const navigate = useNavigate();

  // Fetch users for the picker
  const { data: users, isLoading: usersLoading } = useUsers();

  // Fetch permission inspection data
  const { data, isLoading, error } = usePermissionInspector(
    selectedUserId,
    filter ? { filter } : undefined
  );

  const handleUserSelect = (userId: string) => {
    setSelectedUserId(userId);
  };

  const handleFilterChange = (newFilter: 'cost' | 'document' | 'task' | 'project' | 'user' | 'system' | '') => {
    setFilter(newFilter);
  };

  const formatPermissionKey = (key: string): string => {
    return key.split('.').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
  };

  const getPermissionModule = (key: string): string => {
    return key.split('.')[0] || 'general';
  };

  const groupPermissionsByModule = (permissions: Array<{ key: string; granted: boolean; sources: string[] }>) => {
    const grouped: Record<string, Array<{ key: string; granted: boolean; sources: string[] }>> = {};
    
    permissions.forEach((perm) => {
      const module = getPermissionModule(perm.key);
      if (!grouped[module]) {
        grouped[module] = [];
      }
      grouped[module].push(perm);
    });
    
    return grouped;
  };

  const handleViewAuditLogs = () => {
    if (selectedUserId) {
      navigate(`/admin/audit-logs?user_id=${selectedUserId}`);
    }
  };

  return (
    <div className="container mx-auto p-6">
      <Card>
        <CardHeader>
          <CardTitle>Permission Inspector</CardTitle>
          <p className="text-sm text-gray-600 mt-2">
            Inspect user permissions and their sources (roles)
          </p>
        </CardHeader>
        <CardContent>
          {/* User Picker */}
          <div className="mb-6">
            <label className="block text-sm font-medium mb-2">Select User</label>
            {usersLoading ? (
              <LoadingSpinner />
            ) : (
              <select
                className="w-full px-3 py-2 border rounded"
                value={selectedUserId || ''}
                onChange={(e) => handleUserSelect(e.target.value)}
              >
                <option value="">-- Select a user --</option>
                {users?.map((user) => (
                  <option key={user.id} value={String(user.id)}>
                    {user.name} ({user.email})
                  </option>
                ))}
              </select>
            )}
          </div>

          {/* Filter */}
          {selectedUserId && (
            <div className="mb-6">
              <label className="block text-sm font-medium mb-2">Filter by Module</label>
              <div className="flex flex-wrap gap-2">
                <Button
                  variant={filter === '' ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => handleFilterChange('')}
                >
                  All
                </Button>
                <Button
                  variant={filter === 'cost' ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => handleFilterChange('cost')}
                >
                  Cost
                </Button>
                <Button
                  variant={filter === 'document' ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => handleFilterChange('document')}
                >
                  Document
                </Button>
                <Button
                  variant={filter === 'task' ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => handleFilterChange('task')}
                >
                  Task
                </Button>
                <Button
                  variant={filter === 'project' ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => handleFilterChange('project')}
                >
                  Project
                </Button>
                <Button
                  variant={filter === 'user' ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => handleFilterChange('user')}
                >
                  User
                </Button>
                <Button
                  variant={filter === 'system' ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => handleFilterChange('system')}
                >
                  System
                </Button>
              </div>
            </div>
          )}

          {/* Loading State */}
          {isLoading && (
            <div className="flex items-center justify-center py-12">
              <LoadingSpinner />
            </div>
          )}

          {/* Error State */}
          {error && (
            <div className="p-4 bg-red-50 border border-red-200 rounded">
              <p className="text-red-600">Error loading permission data: {(error as Error).message}</p>
            </div>
          )}

          {/* Results */}
          {data && !isLoading && (
            <div className="space-y-6">
              {/* User Info */}
              <div className="p-4 bg-gray-50 rounded">
                <h3 className="font-semibold mb-2">User Information</h3>
                <p><strong>Name:</strong> {data.user.name}</p>
                <p><strong>Email:</strong> {data.user.email}</p>
                <div className="mt-2">
                  <Button size="sm" onClick={handleViewAuditLogs}>
                    View Audit Logs
                  </Button>
                </div>
              </div>

              {/* Roles */}
              <div>
                <h3 className="font-semibold mb-3">Roles</h3>
                {data.roles.length === 0 ? (
                  <p className="text-gray-500">No roles assigned</p>
                ) : (
                  <div className="space-y-2">
                    {data.roles.map((role, idx) => (
                      <div key={idx} className="p-3 border rounded">
                        <div className="font-medium">{role.name}</div>
                        <div className="text-sm text-gray-600 mt-1">
                          Permissions: {role.permissions.length}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Permissions by Module */}
              <div>
                <h3 className="font-semibold mb-3">Permissions</h3>
                {data.permissions.length === 0 ? (
                  <p className="text-gray-500">No permissions found</p>
                ) : (
                  <div className="space-y-4">
                    {Object.entries(groupPermissionsByModule(data.permissions)).map(([module, perms]) => (
                      <div key={module} className="border rounded p-4">
                        <h4 className="font-medium mb-2 capitalize">{module}</h4>
                        <div className="space-y-2">
                          {perms.map((perm) => (
                            <div
                              key={perm.key}
                              className={`flex items-center justify-between p-2 rounded ${
                                perm.granted ? 'bg-green-50' : 'bg-gray-50'
                              }`}
                            >
                              <div className="flex items-center gap-2">
                                {perm.granted ? (
                                  <span className="text-green-600">✓</span>
                                ) : (
                                  <span className="text-gray-400">✗</span>
                                )}
                                <span className="text-sm">{formatPermissionKey(perm.key)}</span>
                              </div>
                              {perm.granted && perm.sources.length > 0 && (
                                <div className="flex gap-1">
                                  {perm.sources.map((source, idx) => (
                                    <span
                                      key={idx}
                                      className="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded"
                                    >
                                      {source}
                                    </span>
                                  ))}
                                </div>
                              )}
                            </div>
                          ))}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Missing Permissions */}
              {data.missing_permissions.length > 0 && (
                <div>
                  <h3 className="font-semibold mb-3 text-orange-600">Missing Permissions</h3>
                  <div className="p-4 bg-orange-50 border border-orange-200 rounded">
                    <ul className="list-disc list-inside space-y-1">
                      {data.missing_permissions.map((perm) => (
                        <li key={perm} className="text-sm">
                          {formatPermissionKey(perm)}
                        </li>
                      ))}
                    </ul>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Empty State */}
          {!selectedUserId && !isLoading && (
            <div className="text-center py-12 text-gray-500">
              Please select a user to inspect permissions
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
};
