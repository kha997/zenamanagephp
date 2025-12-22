/**
 * ULID Type Definitions for ZenaManage
 * Ensures proper handling of ULID format IDs throughout the application
 */

// ULID type definition
export type ULID = string;

// Base entity interface with ULID
export interface BaseEntity {
  id: ULID;
  created_at: string;
  updated_at: string;
}

// Task interface with ULID references
export interface Task extends BaseEntity {
  name: string;
  description?: string;
  status: 'backlog' | 'in_progress' | 'blocked' | 'done' | 'canceled';
  priority: 'low' | 'normal' | 'high' | 'urgent';
  start_date?: string;
  end_date?: string;
  estimated_hours?: number;
  actual_hours?: number;
  progress_percent?: number;
  project_id: ULID;
  assignee_id?: ULID;
  created_by: ULID;
  project?: Project;
  assignee?: User;
  creator?: User;
}

// Project interface with ULID references
export interface Project extends BaseEntity {
  name: string;
  description?: string;
  status: 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled';
  start_date?: string;
  end_date?: string;
  budget?: number;
  client_id?: ULID;
  manager_id: ULID;
  client?: Client;
  manager?: User;
}

// User interface with ULID
export interface User extends BaseEntity {
  name: string;
  email: string;
  avatar?: string;
  role: 'super_admin' | 'admin' | 'pm' | 'member' | 'client';
  tenant_id: ULID;
  tenant?: Tenant;
}

// Client interface with ULID
export interface Client extends BaseEntity {
  name: string;
  email: string;
  company?: string;
  phone?: string;
  tenant_id: ULID;
  tenant?: Tenant;
}

// Tenant interface with ULID
export interface Tenant extends BaseEntity {
  name: string;
  domain: string;
  status: 'active' | 'inactive' | 'suspended';
}

// Task Comment interface with ULID references
export interface TaskComment extends BaseEntity {
  task_id: ULID;
  user_id: ULID;
  content: string;
  type: 'general' | 'question' | 'suggestion' | 'issue';
  is_internal: boolean;
  parent_id?: ULID;
  task?: Task;
  user?: User;
  parent?: TaskComment;
  replies?: TaskComment[];
}

// Subtask interface with ULID references
export interface Subtask extends BaseEntity {
  task_id: ULID;
  name: string;
  description?: string;
  status: 'todo' | 'in_progress' | 'done';
  priority: 'low' | 'normal' | 'high' | 'urgent';
  assignee_id?: ULID;
  order: number;
  task?: Task;
  assignee?: User;
}

// API Response interfaces
export interface ApiResponse<T = any> {
  success: boolean;
  data: T;
  message?: string;
  errors?: Record<string, string[]>;
  meta?: {
    pagination?: {
      current_page: number;
      last_page: number;
      per_page: number;
      total: number;
    };
  };
}

// Paginated response
export interface PaginatedResponse<T = any> extends ApiResponse<T[]> {
  meta: {
    pagination: {
      current_page: number;
      last_page: number;
      per_page: number;
      total: number;
    };
  };
}

// Filter interfaces
export interface TaskFilters {
  project_id?: ULID;
  status?: Task['status'];
  priority?: Task['priority'];
  assignee_id?: ULID;
  search?: string;
  sort_by?: keyof Task;
  sort_direction?: 'asc' | 'desc';
  page?: number;
  per_page?: number;
}

export interface ProjectFilters {
  status?: Project['status'];
  manager_id?: ULID;
  client_id?: ULID;
  search?: string;
  sort_by?: keyof Project;
  sort_direction?: 'asc' | 'desc';
  page?: number;
  per_page?: number;
}

// Form interfaces
export interface TaskFormData {
  name: string;
  description?: string;
  status: Task['status'];
  priority: Task['priority'];
  start_date?: string;
  end_date?: string;
  estimated_hours?: number;
  project_id: ULID;
  assignee_id?: ULID;
}

export interface ProjectFormData {
  name: string;
  description?: string;
  status: Project['status'];
  start_date?: string;
  end_date?: string;
  budget?: number;
  client_id?: ULID;
  manager_id: ULID;
}

// Utility types for ULID validation
export type ULIDString = string & { readonly __brand: 'ULID' };

// Helper function to validate ULID format
export function isValidULID(id: string): id is ULID {
  return /^[0-9A-HJKMNP-TV-Z]{26}$/.test(id);
}

// Helper function to create ULID from string
export function createULID(id: string): ULID {
  if (!isValidULID(id)) {
    throw new Error(`Invalid ULID format: ${id}`);
  }
  return id as ULID;
}

// Helper function to safely convert string to ULID
export function toULID(id: string | ULID): ULID {
  if (typeof id === 'string' && isValidULID(id)) {
    return id as ULID;
  }
  throw new Error(`Cannot convert to ULID: ${id}`);
}

// Type guards
export function isTask(obj: any): obj is Task {
  return obj && typeof obj.id === 'string' && isValidULID(obj.id) && typeof obj.name === 'string';
}

export function isProject(obj: any): obj is Project {
  return obj && typeof obj.id === 'string' && isValidULID(obj.id) && typeof obj.name === 'string';
}

export function isUser(obj: any): obj is User {
  return obj && typeof obj.id === 'string' && isValidULID(obj.id) && typeof obj.name === 'string' && typeof obj.email === 'string';
}

// Constants
export const ULID_LENGTH = 26;
export const ULID_PATTERN = /^[0-9A-HJKMNP-TV-Z]{26}$/;

// Task status constants
export const TASK_STATUSES = ['backlog', 'in_progress', 'blocked', 'done', 'canceled'] as const;
export const TASK_PRIORITIES = ['low', 'normal', 'high', 'urgent'] as const;

// Project status constants
export const PROJECT_STATUSES = ['planning', 'active', 'on_hold', 'completed', 'cancelled'] as const;

// User role constants
export const USER_ROLES = ['super_admin', 'admin', 'pm', 'member', 'client'] as const;

// Comment type constants
export const COMMENT_TYPES = ['general', 'question', 'suggestion', 'issue'] as const;
