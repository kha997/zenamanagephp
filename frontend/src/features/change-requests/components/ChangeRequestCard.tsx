import React from 'react';
import { Link } from 'react-router-dom';
import { ChangeRequest } from '../types/changeRequest';
import { StatusBadge } from './StatusBadge';
import { formatCurrency, formatDate } from '@/lib/utils';
import { 
  CalendarIcon, 
  CurrencyDollarIcon, 
  ClockIcon,
  UserIcon,
  DocumentTextIcon,
  ChartBarIcon
} from '@/lib/heroicons';

interface ChangeRequestCardProps {
  changeRequest: ChangeRequest;
  projectId: string;
  onEdit?: (id: string) => void;
  onDelete?: (id: string) => void;
  onDecision?: (id: string, decision: 'approve' | 'reject') => void;
  showActions?: boolean;
  compact?: boolean;
}

export const ChangeRequestCard: React.FC<ChangeRequestCardProps> = ({ 
  changeRequest, 
  projectId,
  onEdit,
  onDelete,
  onDecision,
  showActions = false,
  compact = false
}) => {
  const canDecide = changeRequest.status === 'awaiting_approval';
  const canEdit = changeRequest.status === 'draft';

  return (
    <div className="bg-white rounded-lg border border-gray-200 hover:border-gray-300 transition-all duration-200 hover:shadow-lg group">
      {/* Header */}
      <div className="p-4 sm:p-6">
        <div className="flex items-start justify-between mb-4">
          <div className="flex-1 min-w-0">
            <Link 
              to={`/projects/${projectId}/change-requests/${changeRequest.id}`}
              className="block group-hover:text-blue-600 transition-colors"
              aria-label={`Xem chi tiết change request ${changeRequest.code}`}
            >
              <h3 className="text-lg font-semibold text-gray-900 truncate">
                {changeRequest.code}
              </h3>
              <p className="text-base font-medium text-gray-700 mt-1 line-clamp-1">
                {changeRequest.title}
              </p>
            </Link>
            {!compact && (
              <p className="text-gray-600 mt-2 line-clamp-2 text-sm leading-relaxed">
                {changeRequest.description}
              </p>
            )}
          </div>
          <div className="ml-4 flex-shrink-0">
            <StatusBadge status={changeRequest.status} />
          </div>
        </div>

        {/* Metrics Grid */}
        <div className={`grid gap-4 text-sm ${
          compact ? 'grid-cols-2 lg:grid-cols-4' : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4'
        }`}>
          {/* Impact Days */}
          <div className="flex items-center text-gray-600 bg-gray-50 rounded-lg p-3">
            <ClockIcon className="h-5 w-5 mr-3 text-orange-500 flex-shrink-0" />
            <div>
              <p className="font-medium text-gray-900">{changeRequest.impact_days}</p>
              <p className="text-xs text-gray-500">ngày tác động</p>
            </div>
          </div>

          {/* Impact Cost */}
          <div className="flex items-center text-gray-600 bg-gray-50 rounded-lg p-3">
            <CurrencyDollarIcon className="h-5 w-5 mr-3 text-green-500 flex-shrink-0" />
            <div>
              <p className="font-medium text-gray-900">{formatCurrency(changeRequest.impact_cost)}</p>
              <p className="text-xs text-gray-500">chi phí tác động</p>
            </div>
          </div>

          {/* Created Date */}
          <div className="flex items-center text-gray-600 bg-gray-50 rounded-lg p-3">
            <CalendarIcon className="h-5 w-5 mr-3 text-blue-500 flex-shrink-0" />
            <div>
              <p className="font-medium text-gray-900">{formatDate(changeRequest.created_at)}</p>
              <p className="text-xs text-gray-500">ngày tạo</p>
            </div>
          </div>

          {/* Created By */}
          <div className="flex items-center text-gray-600 bg-gray-50 rounded-lg p-3">
            <UserIcon className="h-5 w-5 mr-3 text-purple-500 flex-shrink-0" />
            <div>
              <p className="font-medium text-gray-900 truncate">
                {changeRequest.created_by || 'N/A'}
              </p>
              <p className="text-xs text-gray-500">người tạo</p>
            </div>
          </div>
        </div>

        {/* Impact KPI */}
        {!compact && changeRequest.impact_kpi && Object.keys(changeRequest.impact_kpi).length > 0 && (
          <div className="mt-6 pt-4 border-t border-gray-100">
            <div className="flex items-center mb-3">
              <ChartBarIcon className="h-4 w-4 mr-2 text-indigo-500" />
              <h4 className="text-sm font-medium text-gray-900">Tác động KPI</h4>
            </div>
            <div className="flex flex-wrap gap-2">
              {Object.entries(changeRequest.impact_kpi).map(([key, value]) => (
                <span 
                  key={key}
                  className="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-200"
                >
                  <span className="font-semibold">{key}:</span>
                  <span className="ml-1">{value}</span>
                </span>
              ))}
            </div>
          </div>
        )}

        {/* Decision Info */}
        {changeRequest.decided_at && changeRequest.decided_by && (
          <div className="mt-4 pt-4 border-t border-gray-100">
            <div className="flex items-start space-x-3">
              <DocumentTextIcon className="h-4 w-4 mt-0.5 text-gray-400" />
              <div className="text-xs text-gray-600">
                <p>
                  <span className="font-medium">Quyết định bởi:</span> {changeRequest.decided_by}
                </p>
                <p>
                  <span className="font-medium">Thời gian:</span> {formatDate(changeRequest.decided_at)}
                </p>
                {changeRequest.decision_note && (
                  <p className="mt-1">
                    <span className="font-medium">Ghi chú:</span> {changeRequest.decision_note}
                  </p>
                )}
              </div>
            </div>
          </div>
        )}

        {/* Actions */}
        {showActions && (
          <div className="mt-6 pt-4 border-t border-gray-100">
            <div className="flex flex-wrap gap-2">
              {canEdit && onEdit && (
                <button
                  onClick={() => onEdit(changeRequest.id)}
                  className="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                  Chỉnh sửa
                </button>
              )}
              
              {canDecide && onDecision && (
                <>
                  <button
                    onClick={() => onDecision(changeRequest.id, 'approve')}
                    className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                  >
                    Phê duyệt
                  </button>
                  <button
                    onClick={() => onDecision(changeRequest.id, 'reject')}
                    className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                  >
                    Từ chối
                  </button>
                </>
              )}
              
              {canEdit && onDelete && (
                <button
                  onClick={() => onDelete(changeRequest.id)}
                  className="inline-flex items-center px-3 py-1.5 border border-red-300 text-xs font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                >
                  Xóa
                </button>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};