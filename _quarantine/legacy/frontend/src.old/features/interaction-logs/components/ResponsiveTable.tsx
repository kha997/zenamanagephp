import React, { useState } from 'react';
import { ChevronDown, ChevronRight } from 'lucide-react';

interface Column {
  key: string;
  label: string;
  width?: string;
  hideOnMobile?: boolean;
  render?: (value: any, row: any) => React.ReactNode;
}

interface ResponsiveTableProps {
  columns: Column[];
  data: any[];
  keyField?: string;
  emptyMessage?: string;
  mobileCardRender?: (row: any, index: number) => React.ReactNode;
  onRowClick?: (row: any) => void;
}

/**
 * ResponsiveTable component tự động chuyển đổi giữa table và card layout
 * Trên desktop hiển thị table, trên mobile hiển thị cards
 */
export const ResponsiveTable: React.FC<ResponsiveTableProps> = ({
  columns,
  data,
  keyField = 'id',
  emptyMessage = 'Không có dữ liệu',
  mobileCardRender,
  onRowClick
}) => {
  const [expandedRows, setExpandedRows] = useState<Set<string>>(new Set());

  /**
   * Toggle expanded row
   */
  const toggleExpanded = (rowKey: string) => {
    const newExpanded = new Set(expandedRows);
    if (newExpanded.has(rowKey)) {
      newExpanded.delete(rowKey);
    } else {
      newExpanded.add(rowKey);
    }
    setExpandedRows(newExpanded);
  };

  /**
   * Render table for desktop
   */
  const renderTable = () => (
    <div className="hidden md:block overflow-x-auto">
      <table className="min-w-full divide-y divide-gray-200">
        <thead className="bg-gray-50">
          <tr>
            {columns.map((column) => (
              <th
                key={column.key}
                className={`px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider ${
                  column.hideOnMobile ? 'hidden lg:table-cell' : ''
                }`}
                style={{ width: column.width }}
              >
                {column.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="bg-white divide-y divide-gray-200">
          {data.map((row, index) => (
            <tr
              key={row[keyField] || index}
              className={`hover:bg-gray-50 ${
                onRowClick ? 'cursor-pointer' : ''
              }`}
              onClick={() => onRowClick?.(row)}
            >
              {columns.map((column) => (
                <td
                  key={column.key}
                  className={`px-6 py-4 whitespace-nowrap text-sm text-gray-900 ${
                    column.hideOnMobile ? 'hidden lg:table-cell' : ''
                  }`}
                >
                  {column.render ? column.render(row[column.key], row) : row[column.key]}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );

  /**
   * Render cards for mobile
   */
  const renderCards = () => (
    <div className="md:hidden space-y-4">
      {data.map((row, index) => {
        const rowKey = row[keyField] || index.toString();
        const isExpanded = expandedRows.has(rowKey);
        
        if (mobileCardRender) {
          return (
            <div key={rowKey} className="bg-white rounded-lg shadow-sm border border-gray-200">
              {mobileCardRender(row, index)}
            </div>
          );
        }

        // Default card layout
        const visibleColumns = columns.filter(col => !col.hideOnMobile);
        const hiddenColumns = columns.filter(col => col.hideOnMobile);
        
        return (
          <div key={rowKey} className="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            {/* Main visible fields */}
            <div className="space-y-2">
              {visibleColumns.map((column) => (
                <div key={column.key} className="flex justify-between items-start">
                  <span className="text-sm font-medium text-gray-500">
                    {column.label}:
                  </span>
                  <span className="text-sm text-gray-900 text-right flex-1 ml-2">
                    {column.render ? column.render(row[column.key], row) : row[column.key]}
                  </span>
                </div>
              ))}
            </div>
            
            {/* Expandable section for hidden fields */}
            {hiddenColumns.length > 0 && (
              <>
                <button
                  onClick={() => toggleExpanded(rowKey)}
                  className="mt-3 flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800"
                >
                  {isExpanded ? (
                    <ChevronDown className="h-4 w-4" />
                  ) : (
                    <ChevronRight className="h-4 w-4" />
                  )}
                  {isExpanded ? 'Ẩn bớt' : 'Xem thêm'}
                </button>
                
                {isExpanded && (
                  <div className="mt-3 pt-3 border-t border-gray-200 space-y-2">
                    {hiddenColumns.map((column) => (
                      <div key={column.key} className="flex justify-between items-start">
                        <span className="text-sm font-medium text-gray-500">
                          {column.label}:
                        </span>
                        <span className="text-sm text-gray-900 text-right flex-1 ml-2">
                          {column.render ? column.render(row[column.key], row) : row[column.key]}
                        </span>
                      </div>
                    ))}
                  </div>
                )}
              </>
            )}
            
            {/* Row click action */}
            {onRowClick && (
              <button
                onClick={() => onRowClick(row)}
                className="mt-3 w-full text-center text-sm text-blue-600 hover:text-blue-800 py-2 border-t border-gray-200"
              >
                Xem chi tiết
              </button>
            )}
          </div>
        );
      })}
    </div>
  );

  if (data.length === 0) {
    return (
      <div className="text-center py-8 text-gray-500">
        {emptyMessage}
      </div>
    );
  }

  return (
    <div>
      {renderTable()}
      {renderCards()}
    </div>
  );
};