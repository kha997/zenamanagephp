/**
 * Notification Navigation Resolver Tests
 * 
 * Round 258: Tests for deep-link navigation resolver
 */

import { describe, it, expect } from 'vitest';
import { resolveNotificationRoute } from '../lib/notifications/navigation';
import type { Notification } from '../features/app/api';

describe('resolveNotificationRoute', () => {
  describe('Tasks module', () => {
    it('should resolve task notification to tasks list', () => {
      const notification: Notification = {
        id: '1',
        tenant_id: 'tenant1',
        user_id: 'user1',
        module: 'tasks',
        type: 'task.assigned',
        title: 'Task assigned',
        message: 'You have been assigned a task',
        entity_type: 'task',
        entity_id: 'task123',
        is_read: false,
        metadata: null,
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
      };

      const route = resolveNotificationRoute(notification);
      expect(route).toEqual({
        path: '/app/tasks',
      });
    });

    it('should resolve task notification with project_id to project detail', () => {
      const notification: Notification = {
        id: '1',
        tenant_id: 'tenant1',
        user_id: 'user1',
        module: 'tasks',
        type: 'task.assigned',
        title: 'Task assigned',
        message: 'You have been assigned a task',
        entity_type: 'task',
        entity_id: 'task123',
        is_read: false,
        metadata: {
          project_id: 'project456',
        },
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
      };

      const route = resolveNotificationRoute(notification);
      expect(route).toEqual({
        path: '/app/projects/project456',
      });
    });
  });

  describe('Cost module', () => {
    it('should resolve change order notification to change order detail', () => {
      const notification: Notification = {
        id: '1',
        tenant_id: 'tenant1',
        user_id: 'user1',
        module: 'cost',
        type: 'co.approved',
        title: 'Change order approved',
        message: 'Change order has been approved',
        entity_type: 'change_order',
        entity_id: 'co123',
        is_read: false,
        metadata: {
          project_id: 'project456',
          contract_id: 'contract789',
          co_id: 'co123',
        },
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
      };

      const route = resolveNotificationRoute(notification);
      expect(route).toEqual({
        path: '/app/projects/project456/contracts/contract789/change-orders/co123',
      });
    });

    it('should resolve payment certificate notification to contract detail', () => {
      const notification: Notification = {
        id: '1',
        tenant_id: 'tenant1',
        user_id: 'user1',
        module: 'cost',
        type: 'certificate.approved',
        title: 'Payment certificate approved',
        message: 'Payment certificate has been approved',
        entity_type: 'payment_certificate',
        entity_id: 'cert123',
        is_read: false,
        metadata: {
          project_id: 'project456',
          contract_id: 'contract789',
        },
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
      };

      const route = resolveNotificationRoute(notification);
      expect(route).toEqual({
        path: '/app/projects/project456/contracts/contract789',
      });
    });

    it('should resolve payment notification to contract detail', () => {
      const notification: Notification = {
        id: '1',
        tenant_id: 'tenant1',
        user_id: 'user1',
        module: 'cost',
        type: 'payment.marked_paid',
        title: 'Payment marked as paid',
        message: 'Payment has been marked as paid',
        entity_type: 'payment',
        entity_id: 'payment123',
        is_read: false,
        metadata: {
          project_id: 'project456',
          contract_id: 'contract789',
        },
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
      };

      const route = resolveNotificationRoute(notification);
      expect(route).toEqual({
        path: '/app/projects/project456/contracts/contract789',
      });
    });

    it('should resolve generic cost notification to project detail', () => {
      const notification: Notification = {
        id: '1',
        tenant_id: 'tenant1',
        user_id: 'user1',
        module: 'cost',
        type: 'cost.alert',
        title: 'Cost alert',
        message: 'Cost threshold exceeded',
        entity_type: null,
        entity_id: null,
        is_read: false,
        metadata: {
          project_id: 'project456',
        },
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
      };

      const route = resolveNotificationRoute(notification);
      expect(route).toEqual({
        path: '/app/projects/project456',
      });
    });
  });

  describe('Documents module', () => {
    it('should resolve document notification to document detail', () => {
      const notification: Notification = {
        id: '1',
        tenant_id: 'tenant1',
        user_id: 'user1',
        module: 'documents',
        type: 'document.uploaded',
        title: 'Document uploaded',
        message: 'A new document has been uploaded',
        entity_type: 'document',
        entity_id: 'doc123',
        is_read: false,
        metadata: null,
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
      };

      const route = resolveNotificationRoute(notification);
      expect(route).toEqual({
        path: '/app/documents/doc123',
      });
    });

    it('should resolve generic document notification to documents list', () => {
      const notification: Notification = {
        id: '1',
        tenant_id: 'tenant1',
        user_id: 'user1',
        module: 'documents',
        type: 'document.alert',
        title: 'Document alert',
        message: 'Document alert message',
        entity_type: null,
        entity_id: null,
        is_read: false,
        metadata: null,
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
      };

      const route = resolveNotificationRoute(notification);
      expect(route).toEqual({
        path: '/app/documents',
      });
    });
  });

  describe('RBAC module', () => {
    it('should resolve user profile assigned notification to admin users page', () => {
      const notification: Notification = {
        id: '1',
        tenant_id: 'tenant1',
        user_id: 'user1',
        module: 'rbac',
        type: 'user.profile_assigned',
        title: 'Profile assigned',
        message: 'A profile has been assigned to you',
        entity_type: 'user',
        entity_id: 'user123',
        is_read: false,
        metadata: {
          user_id: 'user123',
        },
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
      };

      const route = resolveNotificationRoute(notification);
      expect(route).toEqual({
        path: '/admin/users',
      });
    });

    it('should resolve generic RBAC notification to admin users page', () => {
      const notification: Notification = {
        id: '1',
        tenant_id: 'tenant1',
        user_id: 'user1',
        module: 'rbac',
        type: 'role.changed',
        title: 'Role changed',
        message: 'Your role has been changed',
        entity_type: null,
        entity_id: null,
        is_read: false,
        metadata: null,
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
      };

      const route = resolveNotificationRoute(notification);
      expect(route).toEqual({
        path: '/admin/users',
      });
    });
  });

  describe('System module', () => {
    it('should return null for system notifications', () => {
      const notification: Notification = {
        id: '1',
        tenant_id: 'tenant1',
        user_id: 'user1',
        module: 'system',
        type: 'system.alert',
        title: 'System alert',
        message: 'System maintenance scheduled',
        entity_type: null,
        entity_id: null,
        is_read: false,
        metadata: null,
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
      };

      const route = resolveNotificationRoute(notification);
      expect(route).toBeNull();
    });
  });

  describe('Unknown or missing data', () => {
    it('should return null for notification with null module', () => {
      const notification: Notification = {
        id: '1',
        tenant_id: 'tenant1',
        user_id: 'user1',
        module: null,
        type: 'unknown.type',
        title: 'Unknown notification',
        message: 'Unknown notification message',
        entity_type: null,
        entity_id: null,
        is_read: false,
        metadata: null,
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
      };

      const route = resolveNotificationRoute(notification);
      expect(route).toBeNull();
    });

    it('should handle change order notification with missing metadata gracefully', () => {
      const notification: Notification = {
        id: '1',
        tenant_id: 'tenant1',
        user_id: 'user1',
        module: 'cost',
        type: 'co.approved',
        title: 'Change order approved',
        message: 'Change order has been approved',
        entity_type: 'change_order',
        entity_id: 'co123',
        is_read: false,
        metadata: null,
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
      };

      const route = resolveNotificationRoute(notification);
      // Should return null when metadata is missing
      expect(route).toBeNull();
    });
  });
});
