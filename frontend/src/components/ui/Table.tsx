/**
 * Table Component
 * Responsive table với sorting, pagination và selection
 */
import React from 'react';
import { cn } from '../../lib/utils/format';

interface Column<T> {
  key: keyof T | string;
  title: string;
  width?: string;
  align?: 'left' | 'center' | 'right';
  sortable?: boolean;
  render?: (value: any, record: T, index: number) => React.ReactNode;
}

interface TableProps<T> {
  columns: Column<T>[];
  data: T[];
  loading?: boolean;
  pagination?: {
    current: number;
    pageSize: number;
    total: number;
    onChange: (page: number, pageSize: number) => void;
  };
  rowSelection?: {
    selectedRowKeys: (string | number)[];
    onChange: (selectedRowKeys: (string | number)[], selectedRows: T[]) => void;
    getCheckboxProps?: (record: T) => { disabled?: boolean };
  };
  onRow?: (record: T, index: number) => {
    onClick?: () => void;
    onDoubleClick?: () => void;
    className?: string;
  };
  className?: string;
  size?: 'sm' | 'md' | 'lg';
  bordered?: boolean;
  striped?: boolean;
}

const sizeVariants = {
  sm: 'text-xs',
  md: 'text-sm',
  lg: 'text-base'
};

const paddingVariants = {
  sm: 'px-2 py-1',
  md: 'px-4 py-2',
  lg: 'px-6 py-3'
};

export function Table<T extends Record<string, any>>({
  columns,
  data,
  loading = false,
  pagination,
  rowSelection,
  onRow,
  className,
  size = 'md',
  bordered = true,
  striped = true
}: TableProps<T>) {
  const handleSelectAll = (checked: boolean) => {
    if (!rowSelection) return;
    
    if (checked) {
      const allKeys = data.map((_, index) => index);
      rowSelection.onChange(allKeys, data);
    } else {
      rowSelection.onChange([], []);
    }
  };

  const handleSelectRow = (record: T, index: number, checked: boolean) => {
    if (!rowSelection) return;
    
    const { selectedRowKeys } = rowSelection;
    let newSelectedKeys: (string | number)[];
    let newSelectedRows: T[];
    
    if (checked) {
      newSelectedKeys = [...selectedRowKeys, index];
      newSelectedRows = data.filter((_, i) => newSelectedKeys.includes(i));
    } else {
      newSelectedKeys = selectedRowKeys.filter(key => key !== index);
      newSelectedRows = data.filter((_, i) => newSelectedKeys.includes(i));
    }
    
    rowSelection.onChange(newSelectedKeys, newSelectedRows);
  };

  const isAllSelected = rowSelection && rowSelection.selectedRowKeys.length === data.length && data.length > 0;
  const isIndeterminate = rowSelection && rowSelection.selectedRowKeys.length > 0 && rowSelection.selectedRowKeys.length < data.length;

  return (
    <div className={cn('overflow-hidden', className)}>
      <div className="overflow-x-auto">
        <table className={cn(
          'min-w-full divide-y divide-gray-200',
          sizeVariants[size],
          bordered && 'border border-gray-200'
        )}>
          <thead className="bg-gray-50">
            <tr>
              {rowSelection && (
                <th className={cn('relative', paddingVariants[size])}>
                  <input
                    type="checkbox"
                    className="absolute left-4 top-1/2 transform -translate-y-1/2 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                    checked={isAllSelected}
                    ref={(el) => {
                      if (el) el.indeterminate = isIndeterminate || false;
                    }}
                    onChange={(e) => handleSelectAll(e.target.checked)}
                  />
                </th>
              )}
              
              {columns.map((column, index) => (
                <th
                  key={String(column.key) + index}
                  className={cn(
                    'font-medium text-gray-900 tracking-wider uppercase',
                    paddingVariants[size],
                    column.align === 'center' && 'text-center',
                    column.align === 'right' && 'text-right',
                    column.sortable && 'cursor-pointer hover:bg-gray-100'
                  )}
                  style={{ width: column.width }}
                >
                  {column.title}
                </th>
              ))}
            </tr>
          </thead>
          
          <tbody className={cn(
            'bg-white divide-y divide-gray-200',
            striped && 'divide-y divide-gray-200'
          )}>
            {loading ? (
              <tr>
                <td
                  colSpan={columns.length + (rowSelection ? 1 : 0)}
                  className="px-6 py-12 text-center text-gray-500"
                >
                  <div className="flex items-center justify-center">
                    <svg className="animate-spin h-5 w-5 mr-2" viewBox="0 0 24 24">
                      <circle
                        className="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        strokeWidth="4"
                        fill="none"
                      />
                      <path
                        className="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                      />
                    </svg>
                    Đang tải...
                  </div>
                </td>
              </tr>
            ) : data.length === 0 ? (
              <tr>
                <td
                  colSpan={columns.length + (rowSelection ? 1 : 0)}
                  className="px-6 py-12 text-center text-gray-500"
                >
                  Không có dữ liệu
                </td>
              </tr>
            ) : (
              data.map((record, index) => {
                const rowProps = onRow?.(record, index) || {};
                const isSelected = rowSelection?.selectedRowKeys.includes(index) || false;
                const checkboxProps = rowSelection?.getCheckboxProps?.(record) || {};
                
                return (
                  <tr
                    key={index}
                    className={cn(
                      'hover:bg-gray-50 transition-colors duration-150',
                      striped && index % 2 === 0 && 'bg-gray-50',
                      isSelected && 'bg-blue-50',
                      rowProps.className
                    )}
                    onClick={rowProps.onClick}
                    onDoubleClick={rowProps.onDoubleClick}
                  >
                    {rowSelection && (
                      <td className={paddingVariants[size]}>
                        <input
                          type="checkbox"
                          className="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                          checked={isSelected}
                          disabled={checkboxProps.disabled}
                          onChange={(e) => handleSelectRow(record, index, e.target.checked)}
                        />
                      </td>
                    )}
                    
                    {columns.map((column, colIndex) => {
                      const value = record[column.key as keyof T];
                      const content = column.render
                        ? column.render(value, record, index)
                        : String(value || '');
                      
                      return (
                        <td
                          key={String(column.key) + colIndex}
                          className={cn(
                            'text-gray-900',
                            paddingVariants[size],
                            column.align === 'center' && 'text-center',
                            column.align === 'right' && 'text-right'
                          )}
                        >
                          {content}
                        </td>
                      );
                    })}
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>
      
      {pagination && (
        <div className="flex items-center justify-between px-4 py-3 bg-white border-t border-gray-200">
          <div className="flex items-center text-sm text-gray-700">
            Hiển thị {Math.min((pagination.current - 1) * pagination.pageSize + 1, pagination.total)} đến{' '}
            {Math.min(pagination.current * pagination.pageSize, pagination.total)} trong tổng số{' '}
            {pagination.total} kết quả
          </div>
          
          <div className="flex items-center space-x-2">
            <button
              onClick={() => pagination.onChange(pagination.current - 1, pagination.pageSize)}
              disabled={pagination.current <= 1}
              className="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Trước
            </button>
            
            <span className="px-3 py-1 text-sm">
              Trang {pagination.current} / {Math.ceil(pagination.total / pagination.pageSize)}
            </span>
            
            <button
              onClick={() => pagination.onChange(pagination.current + 1, pagination.pageSize)}
              disabled={pagination.current >= Math.ceil(pagination.total / pagination.pageSize)}
              className="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Sau
            </button>
          </div>
        </div>
      )}
    </div>
  );
}