import React from 'react';
import type { ChangeRequestStatus } from '../types/changeRequest';

interface StatusBadgeProps {
  status: ChangeRequestStatus;
  className?: string;
}

const statusConfig = {
  draft: {
    label: 'Nháp',
    className: 'bg-gray-100 text-gray-800 border-gray-200'
  },
  awaiting_approval: {
    label: 'Chờ duyệt',
    className: 'bg-yellow-100 text-yellow-800 border-yellow-200'
  },
  approved: {
    label: 'Đã duyệt',
    className: 'bg-green-100 text-green-800 border-green-200'
  },
  rejected: {
    label: 'Từ chối',
    className: 'bg-red-100 text-red-800 border-red-200'
  }
};

export const StatusBadge: React.FC<StatusBadgeProps> = ({ status, className = '' }) => {
  const config = statusConfig[status];
  
  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${config.className} ${className}`}>
      {config.label}
    </span>
  );
};
