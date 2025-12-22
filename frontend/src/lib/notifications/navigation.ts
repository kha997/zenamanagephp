/**
 * Notification Navigation Resolver
 * 
 * Round 258: Deep-link navigation for notifications
 * 
 * Maps notification objects to internal routes based on module, type, and entity information.
 */

import type { Notification } from '../../features/app/api';

export type NotificationRoute = {
  path: string;
  params?: Record<string, string>;
};

/**
 * Resolves a notification to its target route
 * 
 * @param notification - The notification object
 * @returns The route to navigate to, or null if no route can be determined
 */
export function resolveNotificationRoute(notification: Notification): NotificationRoute | null {
  const { module, type, entity_type, entity_id, metadata } = notification;

  // Tasks module
  if (module === 'tasks') {
    if (entity_type === 'task' && entity_id) {
      // For now, navigate to tasks list since there's no task detail route
      // If task detail route exists in the future, use: `/app/tasks/${entity_id}`
      // Check if metadata has project_id to navigate to project tasks
      if (metadata?.project_id) {
        return {
          path: `/app/projects/${metadata.project_id}`,
        };
      }
      return {
        path: '/app/tasks',
      };
    }
    return {
      path: '/app/tasks',
    };
  }

  // Cost module
  if (module === 'cost') {
    // Change order notifications
    if (type === 'co.approved' || type === 'co.rejected' || type === 'co.proposed') {
      if (metadata?.project_id && metadata?.contract_id && metadata?.co_id) {
        return {
          path: `/app/projects/${metadata.project_id}/contracts/${metadata.contract_id}/change-orders/${metadata.co_id}`,
        };
      }
      // Fallback: try to use entity_id if it's the co_id
      if (entity_type === 'change_order' && entity_id && metadata?.project_id && metadata?.contract_id) {
        return {
          path: `/app/projects/${metadata.project_id}/contracts/${metadata.contract_id}/change-orders/${entity_id}`,
        };
      }
    }

    // Payment certificate notifications
    if (type === 'certificate.approved' || type === 'certificate.rejected' || type === 'certificate.submitted') {
      if (metadata?.project_id && metadata?.contract_id) {
        // Certificates are shown on the contract detail page
        return {
          path: `/app/projects/${metadata.project_id}/contracts/${metadata.contract_id}`,
        };
      }
      // Fallback: try to use entity_id if it's the contract_id
      if (entity_type === 'payment_certificate' && metadata?.project_id) {
        // We need contract_id, try to get it from metadata or navigate to project
        if (metadata?.contract_id) {
          return {
            path: `/app/projects/${metadata.project_id}/contracts/${metadata.contract_id}`,
          };
        }
        return {
          path: `/app/projects/${metadata.project_id}/contracts`,
        };
      }
    }

    // Payment notifications
    if (type === 'payment.marked_paid' || type === 'payment.created') {
      if (metadata?.project_id && metadata?.contract_id) {
        // Payments are shown on the contract detail page
        return {
          path: `/app/projects/${metadata.project_id}/contracts/${metadata.contract_id}`,
        };
      }
      // Fallback: try to use entity_id if it's the contract_id
      if (entity_type === 'payment' && metadata?.project_id) {
        if (metadata?.contract_id) {
          return {
            path: `/app/projects/${metadata.project_id}/contracts/${metadata.contract_id}`,
          };
        }
        return {
          path: `/app/projects/${metadata.project_id}/contracts`,
        };
      }
    }

    // Generic cost notifications - navigate to project if available
    if (metadata?.project_id) {
      return {
        path: `/app/projects/${metadata.project_id}`,
      };
    }
  }

  // Documents module
  if (module === 'documents') {
    if (entity_type === 'document' && entity_id) {
      return {
        path: `/app/documents/${entity_id}`,
      };
    }
    return {
      path: '/app/documents',
    };
  }

  // RBAC module
  if (module === 'rbac') {
    if (type === 'user.profile_assigned' || type === 'user.role_changed' || type === 'user.created') {
      // Navigate to admin users page if user_id is available
      if (metadata?.user_id) {
        // Admin users page - but we need to check if there's a user detail route
        // For now, navigate to admin users list
        return {
          path: '/admin/users',
        };
      }
      // Fallback to admin users page
      return {
        path: '/admin/users',
      };
    }
    // Generic RBAC notifications
    return {
      path: '/admin/users',
    };
  }

  // System notifications - no specific route
  if (module === 'system') {
    // System notifications typically don't have deep links
    return null;
  }

  // Unknown module or no route can be determined
  return null;
}
