import React, { useState } from 'react';

export interface ProjectFilters {
  search: string;
  status: string;
  priority: string;
  sortBy: string;
  sortDirection: 'asc' | 'desc';
}

interface ProjectFiltersProps {
  filters: ProjectFilters;
  onFiltersChange: (filters: ProjectFilters) => void;
  onReset: () => void;
}

export const ProjectFilters: React.FC<ProjectFiltersProps> = ({
  filters,
  onFiltersChange,
  onReset
}) => {
  const [showAdvanced, setShowAdvanced] = useState(false);

  const handleFilterChange = (key: keyof ProjectFilters, value: string) => {
    onFiltersChange({
      ...filters,
      [key]: value
    });
  };

  const handleSortChange = (sortBy: string) => {
    const newDirection = filters.sortBy === sortBy && filters.sortDirection === 'asc' ? 'desc' : 'asc';
    onFiltersChange({
      ...filters,
      sortBy,
      sortDirection: newDirection
    });
  };

  const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'planning', label: 'Planning' },
    { value: 'active', label: 'Active' },
    { value: 'on_hold', label: 'On Hold' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' }
  ];

  const priorityOptions = [
    { value: '', label: 'All Priorities' },
    { value: 'low', label: 'Low' },
    { value: 'medium', label: 'Medium' },
    { value: 'high', label: 'High' },
    { value: 'critical', label: 'Critical' }
  ];

  const sortOptions = [
    { value: 'name', label: 'Name' },
    { value: 'status', label: 'Status' },
    { value: 'progress', label: 'Progress' },
    { value: 'due_date', label: 'Due Date' },
    { value: 'budget', label: 'Budget' },
    { value: 'created_at', label: 'Created Date' },
    { value: 'updated_at', label: 'Updated Date' }
  ];

  return (
    <div className="bg-white shadow-sm rounded-lg border border-gray-200 mb-6">
      <div className="px-6 py-4 border-b border-gray-200">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-medium text-gray-900">Filters</h3>
          <div className="flex items-center space-x-2">
            <button
              onClick={() => setShowAdvanced(!showAdvanced)}
              className="text-sm text-blue-600 hover:text-blue-500 font-medium"
            >
              {showAdvanced ? 'Hide' : 'Show'} Advanced
            </button>
            <button
              onClick={onReset}
              className="text-sm text-gray-600 hover:text-gray-500 font-medium"
            >
              Reset
            </button>
          </div>
        </div>
      </div>
      
      <div className="p-6">
        {/* Basic Filters */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {/* Search */}
          <div>
            <label htmlFor="search" className="block text-sm font-medium text-gray-700 mb-1">
              Search
            </label>
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i className="fas fa-search text-gray-400"></i>
              </div>
              <input
                type="text"
                id="search"
                value={filters.search}
                onChange={(e) => handleFilterChange('search', e.target.value)}
                placeholder="Search projects..."
                className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
              />
            </div>
          </div>

          {/* Status Filter */}
          <div>
            <label htmlFor="status" className="block text-sm font-medium text-gray-700 mb-1">
              Status
            </label>
            <select
              id="status"
              value={filters.status}
              onChange={(e) => handleFilterChange('status', e.target.value)}
              className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
              {statusOptions.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>

          {/* Priority Filter */}
          <div>
            <label htmlFor="priority" className="block text-sm font-medium text-gray-700 mb-1">
              Priority
            </label>
            <select
              id="priority"
              value={filters.priority}
              onChange={(e) => handleFilterChange('priority', e.target.value)}
              className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
              {priorityOptions.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
        </div>

        {/* Advanced Filters */}
        {showAdvanced && (
          <div className="mt-6 pt-6 border-t border-gray-200">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {/* Sort By */}
              <div>
                <label htmlFor="sortBy" className="block text-sm font-medium text-gray-700 mb-1">
                  Sort By
                </label>
                <div className="flex items-center space-x-2">
                  <select
                    id="sortBy"
                    value={filters.sortBy}
                    onChange={(e) => handleSortChange(e.target.value)}
                    className="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  >
                    {sortOptions.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                  <button
                    onClick={() => handleSortChange(filters.sortBy)}
                    className="px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  >
                    <i className={`fas fa-sort-${filters.sortDirection === 'asc' ? 'up' : 'down'}`}></i>
                  </button>
                </div>
              </div>

              {/* Quick Actions */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Quick Actions
                </label>
                <div className="flex items-center space-x-2">
                  <button
                    onClick={() => handleFilterChange('status', 'active')}
                    className="px-3 py-2 text-sm bg-green-100 text-green-800 rounded-md hover:bg-green-200"
                  >
                    Active Only
                  </button>
                  <button
                    onClick={() => handleFilterChange('status', 'completed')}
                    className="px-3 py-2 text-sm bg-blue-100 text-blue-800 rounded-md hover:bg-blue-200"
                  >
                    Completed Only
                  </button>
                  <button
                    onClick={() => handleFilterChange('status', 'on_hold')}
                    className="px-3 py-2 text-sm bg-red-100 text-red-800 rounded-md hover:bg-red-200"
                  >
                    On Hold Only
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Active Filters Display */}
        {(filters.search || filters.status || filters.priority) && (
          <div className="mt-4 pt-4 border-t border-gray-200">
            <div className="flex items-center space-x-2">
              <span className="text-sm text-gray-500">Active filters:</span>
              {filters.search && (
                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                  Search: {filters.search}
                  <button
                    onClick={() => handleFilterChange('search', '')}
                    className="ml-1 text-blue-600 hover:text-blue-800"
                  >
                    <i className="fas fa-times"></i>
                  </button>
                </span>
              )}
              {filters.status && (
                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                  Status: {filters.status}
                  <button
                    onClick={() => handleFilterChange('status', '')}
                    className="ml-1 text-green-600 hover:text-green-800"
                  >
                    <i className="fas fa-times"></i>
                  </button>
                </span>
              )}
              {filters.priority && (
                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                  Priority: {filters.priority}
                  <button
                    onClick={() => handleFilterChange('priority', '')}
                    className="ml-1 text-purple-600 hover:text-purple-800"
                  >
                    <i className="fas fa-times"></i>
                  </button>
                </span>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default ProjectFilters;
