<?php

/**
 * Notification Types Configuration - Round 255
 * 
 * List of all in-app notification types that users can configure preferences for.
 * This is the source of truth for available notification types.
 * 
 * Format: 'module.type' (e.g., 'task.assigned', 'co.approved')
 */

return [
    // Task-related notifications
    'task.assigned',
    'task.assignee_changed',
    'task.due_soon',
    'task.overdue',
    
    // Cost-related notifications
    'co.approved',
    'certificate.approved',
    'payment.marked_paid',
    
    // RBAC-related notifications
    'user.profile_assigned',
];
