import { useState } from 'react'
import { Search, Filter, X, Calendar, User, Tag, ChevronDown } from 'lucide-react'
import { cn } from '../lib/utils'

interface FilterOption {
  value: string
  label: string
  count?: number
}

interface AdvancedFilterProps {
  searchValue: string
  onSearchChange: (value: string) => void
  filters: Record<string, any>
  onFilterChange: (key: string, value: any) => void
  onClearFilters: () => void
  filterOptions?: {
    status?: FilterOption[]
    priority?: FilterOption[]
    assignee?: FilterOption[]
    project?: FilterOption[]
    dateRange?: {
      start?: string
      end?: string
    }
  }
  className?: string
}

export default function AdvancedFilter({
  searchValue,
  onSearchChange,
  filters,
  onFilterChange,
  onClearFilters,
  filterOptions = {},
  className
}: AdvancedFilterProps) {
  const [isExpanded, setIsExpanded] = useState(false)
  const [activeFilters, setActiveFilters] = useState<string[]>([])

  const handleFilterChange = (key: string, value: any) => {
    onFilterChange(key, value)
    
    if (value && value !== '') {
      if (!activeFilters.includes(key)) {
        setActiveFilters(prev => [...prev, key])
      }
    } else {
      setActiveFilters(prev => prev.filter(f => f !== key))
    }
  }

  const clearAllFilters = () => {
    onClearFilters()
    setActiveFilters([])
  }

  const hasActiveFilters = activeFilters.length > 0 || searchValue

  return (
    <div className={cn('space-y-4', className)}>
      {/* Search and Toggle */}
      <div className="flex items-center gap-4">
        <div className="flex-1 relative">
          <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
          <input
            type="text"
            placeholder="Search..."
            value={searchValue}
            onChange={(e) => onSearchChange(e.target.value)}
            className="input pl-10"
          />
        </div>
        <button
          onClick={() => setIsExpanded(!isExpanded)}
          className={cn(
            'btn btn-outline flex items-center gap-2',
            isExpanded && 'bg-primary-50 border-primary-300 text-primary-700'
          )}
        >
          <Filter className="h-4 w-4" />
          Filters
          {hasActiveFilters && (
            <span className="bg-primary-600 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
              {activeFilters.length + (searchValue ? 1 : 0)}
            </span>
          )}
          <ChevronDown className={cn('h-4 w-4 transition-transform', isExpanded && 'rotate-180')} />
        </button>
        {hasActiveFilters && (
          <button
            onClick={clearAllFilters}
            className="btn btn-outline text-red-600 hover:text-red-700 hover:border-red-300"
          >
            <X className="h-4 w-4 mr-1" />
            Clear
          </button>
        )}
      </div>

      {/* Advanced Filters */}
      {isExpanded && (
        <div className="card animate-slide-down">
          <div className="card-content">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              {/* Status Filter */}
              {filterOptions.status && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Status
                  </label>
                  <select
                    value={filters.status || ''}
                    onChange={(e) => handleFilterChange('status', e.target.value)}
                    className="input"
                  >
                    <option value="">All Status</option>
                    {filterOptions.status.map(option => (
                      <option key={option.value} value={option.value}>
                        {option.label} {option.count && `(${option.count})`}
                      </option>
                    ))}
                  </select>
                </div>
              )}

              {/* Priority Filter */}
              {filterOptions.priority && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Priority
                  </label>
                  <select
                    value={filters.priority || ''}
                    onChange={(e) => handleFilterChange('priority', e.target.value)}
                    className="input"
                  >
                    <option value="">All Priority</option>
                    {filterOptions.priority.map(option => (
                      <option key={option.value} value={option.value}>
                        {option.label} {option.count && `(${option.count})`}
                      </option>
                    ))}
                  </select>
                </div>
              )}

              {/* Assignee Filter */}
              {filterOptions.assignee && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Assignee
                  </label>
                  <select
                    value={filters.assignee || ''}
                    onChange={(e) => handleFilterChange('assignee', e.target.value)}
                    className="input"
                  >
                    <option value="">All Assignees</option>
                    {filterOptions.assignee.map(option => (
                      <option key={option.value} value={option.value}>
                        {option.label} {option.count && `(${option.count})`}
                      </option>
                    ))}
                  </select>
                </div>
              )}

              {/* Project Filter */}
              {filterOptions.project && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Project
                  </label>
                  <select
                    value={filters.project || ''}
                    onChange={(e) => handleFilterChange('project', e.target.value)}
                    className="input"
                  >
                    <option value="">All Projects</option>
                    {filterOptions.project.map(option => (
                      <option key={option.value} value={option.value}>
                        {option.label} {option.count && `(${option.count})`}
                      </option>
                    ))}
                  </select>
                </div>
              )}

              {/* Date Range Filter */}
              {filterOptions.dateRange && (
                <div className="md:col-span-2">
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Date Range
                  </label>
                  <div className="flex gap-2">
                    <div className="flex-1">
                      <input
                        type="date"
                        value={filters.start_date || ''}
                        onChange={(e) => handleFilterChange('start_date', e.target.value)}
                        className="input"
                        placeholder="Start Date"
                      />
                    </div>
                    <div className="flex-1">
                      <input
                        type="date"
                        value={filters.end_date || ''}
                        onChange={(e) => handleFilterChange('end_date', e.target.value)}
                        className="input"
                        placeholder="End Date"
                      />
                    </div>
                  </div>
                </div>
              )}
            </div>

            {/* Active Filters Display */}
            {hasActiveFilters && (
              <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div className="flex flex-wrap gap-2">
                  {searchValue && (
                    <span className="inline-flex items-center px-3 py-1 rounded-full text-sm bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                      Search: "{searchValue}"
                      <button
                        onClick={() => onSearchChange('')}
                        className="ml-2 hover:text-primary-600"
                      >
                        <X className="h-3 w-3" />
                      </button>
                    </span>
                  )}
                  {activeFilters.map(filterKey => {
                    const value = filters[filterKey]
                    if (!value) return null
                    
                    return (
                      <span
                        key={filterKey}
                        className="inline-flex items-center px-3 py-1 rounded-full text-sm bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200"
                      >
                        {filterKey}: {value}
                        <button
                          onClick={() => handleFilterChange(filterKey, '')}
                          className="ml-2 hover:text-gray-600"
                        >
                          <X className="h-3 w-3" />
                        </button>
                      </span>
                    )
                  })}
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
