<?php
declare(strict_types=1);

namespace Src\Foundation;

/**
 * Lớp chứa các hằng số dùng chung trong hệ thống
 */
class Constants {
    // Visibility types
    public const VISIBILITY_INTERNAL = 'internal';
    public const VISIBILITY_CLIENT = 'client';
    
    public const VISIBILITY_TYPES = [
        self::VISIBILITY_INTERNAL,
        self::VISIBILITY_CLIENT
    ];
    
    // Event domains
    public const EVENT_DOMAIN_PROJECT = 'project';
    public const EVENT_DOMAIN_TASK = 'task';
    public const EVENT_DOMAIN_COMPONENT = 'component';
    public const EVENT_DOMAIN_DOCUMENT = 'document';
    public const EVENT_DOMAIN_USER = 'user';
    
    // Common event actions
    public const EVENT_ACTION_CREATED = 'created';
    public const EVENT_ACTION_UPDATED = 'updated';
    public const EVENT_ACTION_DELETED = 'deleted';
    public const EVENT_ACTION_PROGRESS_UPDATED = 'progress_updated';
    public const EVENT_ACTION_STATUS_CHANGED = 'status_changed';
    
    // Permission scopes
    public const PERMISSION_SCOPE_SYSTEM = 'system';
    public const PERMISSION_SCOPE_PROJECT = 'project';
    public const PERMISSION_SCOPE_CUSTOM = 'custom';
    
    // Tag level limits
    public const MAX_TAG_LEVELS = 3;
    
    // Soft delete field
    public const DELETED_AT_FIELD = 'deleted_at';
}