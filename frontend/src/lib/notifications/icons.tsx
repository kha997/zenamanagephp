/**
 * Notification Icon Helper
 * 
 * Round 258: Icons for notification toasts based on module
 */

import React from 'react';
import { 
  CheckSquare, 
  DollarSign, 
  FileText, 
  Users, 
  Bell,
  AlertCircle 
} from 'lucide-react';
import type { Notification } from '../../features/app/api';

/**
 * Gets an icon component for a notification based on its module
 * 
 * @param module - The notification module
 * @returns A React node with the appropriate icon
 */
export function getNotificationIcon(module: Notification['module']): React.ReactNode {
  switch (module) {
    case 'tasks':
      return <CheckSquare className="h-5 w-5" />;
    case 'cost':
      return <DollarSign className="h-5 w-5" />;
    case 'documents':
      return <FileText className="h-5 w-5" />;
    case 'rbac':
      return <Users className="h-5 w-5" />;
    case 'system':
      return <Bell className="h-5 w-5" />;
    default:
      return <AlertCircle className="h-5 w-5" />;
  }
}
