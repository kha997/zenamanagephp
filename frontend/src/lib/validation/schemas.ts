import { z } from 'zod';

/**
 * Common validation patterns
 */
const commonPatterns = {
  email: z.string().email('Invalid email format'),
  password: z.string()
    .min(8, 'Password must be at least 8 characters')
    .regex(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/, 
      'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character'),
  phone: z.string()
    .regex(/^[\+]?[1-9][\d]{0,15}$/, 'Invalid phone number format')
    .optional(),
  ulid: z.string().regex(/^[0-9A-HJKMNP-TV-Z]{26}$/, 'Invalid ULID format'),
  tenantId: z.string().regex(/^[0-9A-HJKMNP-TV-Z]{26}$/, 'Invalid tenant ID format'),
};

/**
 * User validation schemas
 */
export const userSchemas = {
  // User registration
  register: z.object({
    name: z.string().min(2, 'Name must be at least 2 characters').max(255, 'Name too long'),
    email: commonPatterns.email,
    password: commonPatterns.password,
    password_confirmation: z.string(),
    phone: commonPatterns.phone,
    tenant_id: commonPatterns.tenantId.optional(),
  }).refine((data) => data.password === data.password_confirmation, {
    message: "Passwords don't match",
    path: ["password_confirmation"],
  }),

  // User login
  login: z.object({
    email: commonPatterns.email,
    password: z.string().min(1, 'Password is required'),
    remember: z.boolean().optional(),
  }),

  // User update
  update: z.object({
    name: z.string().min(2, 'Name must be at least 2 characters').max(255, 'Name too long').optional(),
    email: commonPatterns.email.optional(),
    phone: commonPatterns.phone,
    avatar: z.string().url('Invalid avatar URL').optional(),
    preferences: z.record(z.any()).optional(),
    first_name: z.string().max(255, 'First name too long').optional(),
    last_name: z.string().max(255, 'Last name too long').optional(),
    department: z.string().max(255, 'Department name too long').optional(),
    job_title: z.string().max(255, 'Job title too long').optional(),
    manager: z.string().max(255, 'Manager name too long').optional(),
  }),

  // Password change
  changePassword: z.object({
    current_password: z.string().min(1, 'Current password is required'),
    new_password: commonPatterns.password,
    new_password_confirmation: z.string(),
  }).refine((data) => data.new_password === data.new_password_confirmation, {
    message: "New passwords don't match",
    path: ["new_password_confirmation"],
  }),

  // Bulk user operations
  bulkCreate: z.object({
    users: z.array(z.object({
      name: z.string().min(2, 'Name must be at least 2 characters').max(255, 'Name too long'),
      email: commonPatterns.email,
      password: z.string().min(8, 'Password must be at least 8 characters').optional(),
      phone: commonPatterns.phone,
      first_name: z.string().max(255, 'First name too long').optional(),
      last_name: z.string().max(255, 'Last name too long').optional(),
      department: z.string().max(255, 'Department name too long').optional(),
      job_title: z.string().max(255, 'Job title too long').optional(),
    })).min(1, 'At least one user is required').max(1000, 'Maximum 1000 users allowed'),
    tenant_id: commonPatterns.tenantId.optional(),
  }),

  bulkUpdate: z.object({
    updates: z.array(z.object({
      id: commonPatterns.ulid,
      data: z.record(z.any()),
    })).min(1, 'At least one update is required').max(1000, 'Maximum 1000 updates allowed'),
  }),

  bulkDelete: z.object({
    user_ids: z.array(commonPatterns.ulid).min(1, 'At least one user ID is required').max(1000, 'Maximum 1000 users allowed'),
  }),
};

/**
 * Project validation schemas
 */
export const projectSchemas = {
  // Project creation
  create: z.object({
    name: z.string().min(2, 'Project name must be at least 2 characters').max(255, 'Project name too long'),
    description: z.string().min(10, 'Description must be at least 10 characters'),
    status: z.enum(['planning', 'active', 'completed', 'cancelled']).optional(),
    start_date: z.string().datetime('Invalid start date format').optional(),
    end_date: z.string().datetime('Invalid end date format').optional(),
    budget: z.number().positive('Budget must be positive').optional(),
    priority: z.enum(['low', 'medium', 'high', 'urgent']).optional(),
    tenant_id: commonPatterns.tenantId.optional(),
  }).refine((data) => {
    if (data.start_date && data.end_date) {
      return new Date(data.start_date) < new Date(data.end_date);
    }
    return true;
  }, {
    message: "End date must be after start date",
    path: ["end_date"],
  }),

  // Project update
  update: z.object({
    name: z.string().min(2, 'Project name must be at least 2 characters').max(255, 'Project name too long').optional(),
    description: z.string().min(10, 'Description must be at least 10 characters').optional(),
    status: z.enum(['planning', 'active', 'completed', 'cancelled']).optional(),
    start_date: z.string().datetime('Invalid start date format').optional(),
    end_date: z.string().datetime('Invalid end date format').optional(),
    budget: z.number().positive('Budget must be positive').optional(),
    priority: z.enum(['low', 'medium', 'high', 'urgent']).optional(),
  }).refine((data) => {
    if (data.start_date && data.end_date) {
      return new Date(data.start_date) < new Date(data.end_date);
    }
    return true;
  }, {
    message: "End date must be after start date",
    path: ["end_date"],
  }),

  // Bulk project operations
  bulkCreate: z.object({
    projects: z.array(z.object({
      name: z.string().min(2, 'Project name must be at least 2 characters').max(255, 'Project name too long'),
      description: z.string().min(10, 'Description must be at least 10 characters'),
      status: z.enum(['planning', 'active', 'completed', 'cancelled']).optional(),
      start_date: z.string().datetime('Invalid start date format').optional(),
      end_date: z.string().datetime('Invalid end date format').optional(),
      budget: z.number().positive('Budget must be positive').optional(),
      priority: z.enum(['low', 'medium', 'high', 'urgent']).optional(),
    })).min(1, 'At least one project is required').max(1000, 'Maximum 1000 projects allowed'),
    tenant_id: commonPatterns.tenantId.optional(),
  }),

  bulkUpdate: z.object({
    updates: z.array(z.object({
      id: commonPatterns.ulid,
      data: z.record(z.any()),
    })).min(1, 'At least one update is required').max(1000, 'Maximum 1000 updates allowed'),
  }),
};

/**
 * Task validation schemas
 */
export const taskSchemas = {
  // Task creation
  create: z.object({
    title: z.string().min(2, 'Task title must be at least 2 characters').max(255, 'Task title too long'),
    description: z.string().min(10, 'Description must be at least 10 characters'),
    status: z.enum(['pending', 'in_progress', 'completed', 'cancelled']).optional(),
    priority: z.enum(['low', 'medium', 'high', 'urgent']).optional(),
    due_date: z.string().datetime('Invalid due date format').optional(),
    assignee_id: commonPatterns.ulid.optional(),
    project_id: commonPatterns.ulid,
    estimated_hours: z.number().positive('Estimated hours must be positive').optional(),
    actual_hours: z.number().min(0, 'Actual hours cannot be negative').optional(),
    dependencies: z.array(commonPatterns.ulid).optional(),
    tags: z.array(z.string()).optional(),
    tenant_id: commonPatterns.tenantId.optional(),
  }),

  // Task update
  update: z.object({
    title: z.string().min(2, 'Task title must be at least 2 characters').max(255, 'Task title too long').optional(),
    description: z.string().min(10, 'Description must be at least 10 characters').optional(),
    status: z.enum(['pending', 'in_progress', 'completed', 'cancelled']).optional(),
    priority: z.enum(['low', 'medium', 'high', 'urgent']).optional(),
    due_date: z.string().datetime('Invalid due date format').optional(),
    assignee_id: commonPatterns.ulid.optional(),
    estimated_hours: z.number().positive('Estimated hours must be positive').optional(),
    actual_hours: z.number().min(0, 'Actual hours cannot be negative').optional(),
    dependencies: z.array(commonPatterns.ulid).optional(),
    tags: z.array(z.string()).optional(),
  }),

  // Bulk task operations
  bulkCreate: z.object({
    tasks: z.array(z.object({
      title: z.string().min(2, 'Task title must be at least 2 characters').max(255, 'Task title too long'),
      description: z.string().min(10, 'Description must be at least 10 characters'),
      status: z.enum(['pending', 'in_progress', 'completed', 'cancelled']).optional(),
      priority: z.enum(['low', 'medium', 'high', 'urgent']).optional(),
      due_date: z.string().datetime('Invalid due date format').optional(),
      assignee_id: commonPatterns.ulid.optional(),
      estimated_hours: z.number().positive('Estimated hours must be positive').optional(),
      actual_hours: z.number().min(0, 'Actual hours cannot be negative').optional(),
      dependencies: z.array(commonPatterns.ulid).optional(),
      tags: z.array(z.string()).optional(),
    })).min(1, 'At least one task is required').max(1000, 'Maximum 1000 tasks allowed'),
    project_id: commonPatterns.ulid,
    tenant_id: commonPatterns.tenantId.optional(),
  }),

  bulkUpdateStatus: z.object({
    task_ids: z.array(commonPatterns.ulid).min(1, 'At least one task ID is required').max(1000, 'Maximum 1000 tasks allowed'),
    status: z.enum(['pending', 'in_progress', 'completed', 'cancelled']),
  }),
};

/**
 * Document validation schemas
 */
export const documentSchemas = {
  // Document upload
  upload: z.object({
    title: z.string().min(2, 'Document title must be at least 2 characters').max(255, 'Document title too long'),
    description: z.string().optional(),
    document_type: z.enum(['contract', 'proposal', 'report', 'invoice', 'other']),
    project_id: commonPatterns.ulid,
    file: z.instanceof(File, { message: 'File is required' }),
    tags: z.array(z.string()).optional(),
    is_public: z.boolean().optional(),
    tenant_id: commonPatterns.tenantId.optional(),
  }).refine((data) => {
    const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'];
    return allowedTypes.includes(data.file.type);
  }, {
    message: "File type not allowed. Allowed types: PDF, DOC, DOCX, TXT",
    path: ["file"],
  }).refine((data) => {
    return data.file.size <= 10 * 1024 * 1024; // 10MB
  }, {
    message: "File size must be less than 10MB",
    path: ["file"],
  }),

  // Document update
  update: z.object({
    title: z.string().min(2, 'Document title must be at least 2 characters').max(255, 'Document title too long').optional(),
    description: z.string().optional(),
    document_type: z.enum(['contract', 'proposal', 'report', 'invoice', 'other']).optional(),
    tags: z.array(z.string()).optional(),
    is_public: z.boolean().optional(),
  }),
};

/**
 * SSO validation schemas
 */
export const ssoSchemas = {
  // OIDC initiation
  oidcInitiate: z.object({
    provider: z.string().min(1, 'Provider is required'),
    state: z.string().optional(),
  }),

  // OIDC callback
  oidcCallback: z.object({
    code: z.string().min(1, 'Authorization code is required'),
    state: z.string().min(1, 'State parameter is required'),
  }),

  // SAML initiation
  samlInitiate: z.object({
    provider: z.string().min(1, 'Provider is required'),
  }),

  // SAML callback
  samlCallback: z.object({
    SAMLResponse: z.string().min(1, 'SAML response is required'),
    RelayState: z.string().optional(),
  }),

  // SAML logout
  samlLogout: z.object({
    name_id: z.string().min(1, 'Name ID is required'),
    session_index: z.string().optional(),
  }),
};

/**
 * Import/Export validation schemas
 */
export const importExportSchemas = {
  // Export filters
  exportFilters: z.object({
    tenant_id: commonPatterns.tenantId.optional(),
    status: z.string().optional(),
    date_from: z.string().datetime('Invalid date format').optional(),
    date_to: z.string().datetime('Invalid date format').optional(),
    project_id: commonPatterns.ulid.optional(),
    assignee_id: commonPatterns.ulid.optional(),
  }).refine((data) => {
    if (data.date_from && data.date_to) {
      return new Date(data.date_from) <= new Date(data.date_to);
    }
    return true;
  }, {
    message: "End date must be after or equal to start date",
    path: ["date_to"],
  }),

  // Import options
  importOptions: z.object({
    tenant_id: commonPatterns.tenantId.optional(),
    skip_duplicates: z.boolean().optional(),
    update_existing: z.boolean().optional(),
    validate_data: z.boolean().optional(),
  }),

  // File upload for import
  importFile: z.object({
    file: z.instanceof(File).refine((f) => !!f, { message: 'File is required' }),
    options: z.object({
      tenant_id: commonPatterns.tenantId.optional(),
      skip_duplicates: z.boolean().optional(),
      update_existing: z.boolean().optional(),
      validate_data: z.boolean().optional(),
    }).optional(),
  }).refine((data) => {
    const allowedTypes = ['application/vnd.openxmlmlsheet-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv'];
    return allowedTypes.includes(data.file.type);
  }, {
    message: "File type not allowed. Allowed types: XLSX, XLS, CSV",
    path: ["file"],
  }).refine((data) => {
    return data.file.size <= 10 * 1024 * 1024; // 10MB
  }, {
    message: "File size must be less than 10MB",
    path: ["file"],
  }),
};

/**
 * Search and filter schemas
 */
export const searchSchemas = {
  // General search
  search: z.object({
    query: z.string().min(1, 'Search query is required').max(255, 'Search query too long'),
    type: z.enum(['users', 'projects', 'tasks', 'documents']).optional(),
    limit: z.number().min(1).max(100).optional(),
    offset: z.number().min(0).optional(),
  }),

  // User filters
  userFilters: z.object({
    tenant_id: commonPatterns.tenantId.optional(),
    status: z.enum(['active', 'inactive']).optional(),
    role: z.string().optional(),
    department: z.string().optional(),
    date_from: z.string().datetime('Invalid date format').optional(),
    date_to: z.string().datetime('Invalid date format').optional(),
  }),

  // Project filters
  projectFilters: z.object({
    tenant_id: commonPatterns.tenantId.optional(),
    status: z.enum(['planning', 'active', 'completed', 'cancelled']).optional(),
    priority: z.enum(['low', 'medium', 'high', 'urgent']).optional(),
    date_from: z.string().datetime('Invalid date format').optional(),
    date_to: z.string().datetime('Invalid date format').optional(),
  }),

  // Task filters
  taskFilters: z.object({
    project_id: commonPatterns.ulid.optional(),
    status: z.enum(['pending', 'in_progress', 'completed', 'cancelled']).optional(),
    priority: z.enum(['low', 'medium', 'high', 'urgent']).optional(),
    assignee_id: commonPatterns.ulid.optional(),
    due_date_from: z.string().datetime('Invalid date format').optional(),
    due_date_to: z.string().datetime('Invalid date format').optional(),
  }),
};

/**
 * MFA validation schemas
 */
export const mfaSchemas = {
  // Enable MFA
  enable: z.object({
    secret: z.string().min(1, 'Secret is required'),
    code: z.string().length(6, 'Code must be 6 digits').regex(/^\d{6}$/, 'Code must contain only digits'),
  }),

  // Verify MFA code
  verify: z.object({
    code: z.string().length(6, 'Code must be 6 digits').regex(/^\d{6}$/, 'Code must contain only digits'),
  }),

  // Recovery code
  recovery: z.object({
    code: z.string().min(10, 'Recovery code is required'),
  }),
};

/**
 * Email verification schemas
 */
export const emailSchemas = {
  // Send verification
  sendVerification: z.object({
    email: commonPatterns.email.optional(),
  }),

  // Verify email
  verify: z.object({
    token: z.string().min(1, 'Verification token is required'),
  }),

  // Change email
  changeEmail: z.object({
    new_email: commonPatterns.email,
    password: z.string().min(1, 'Password is required'),
  }),

  // Confirm email change
  confirmChange: z.object({
    token: z.string().min(1, 'Confirmation token is required'),
  }),
};

/**
 * Session management schemas
 */
export const sessionSchemas = {
  // Revoke session
  revoke: z.object({
    session_id: z.string().min(1, 'Session ID is required'),
  }),

  // Mark device trusted
  markTrusted: z.object({
    session_id: z.string().min(1, 'Session ID is required'),
    trusted: z.boolean(),
  }),
};

/**
 * Type exports for TypeScript
 */
export type UserRegisterInput = z.infer<typeof userSchemas.register>;
export type UserLoginInput = z.infer<typeof userSchemas.login>;
export type UserUpdateInput = z.infer<typeof userSchemas.update>;
export type UserChangePasswordInput = z.infer<typeof userSchemas.changePassword>;
export type UserBulkCreateInput = z.infer<typeof userSchemas.bulkCreate>;
export type UserBulkUpdateInput = z.infer<typeof userSchemas.bulkUpdate>;
export type UserBulkDeleteInput = z.infer<typeof userSchemas.bulkDelete>;

export type ProjectCreateInput = z.infer<typeof projectSchemas.create>;
export type ProjectUpdateInput = z.infer<typeof projectSchemas.update>;
export type ProjectBulkCreateInput = z.infer<typeof projectSchemas.bulkCreate>;
export type ProjectBulkUpdateInput = z.infer<typeof projectSchemas.bulkUpdate>;

export type TaskCreateInput = z.infer<typeof taskSchemas.create>;
export type TaskUpdateInput = z.infer<typeof taskSchemas.update>;
export type TaskBulkCreateInput = z.infer<typeof taskSchemas.bulkCreate>;
export type TaskBulkUpdateStatusInput = z.infer<typeof taskSchemas.bulkUpdateStatus>;

export type DocumentUploadInput = z.infer<typeof documentSchemas.upload>;
export type DocumentUpdateInput = z.infer<typeof documentSchemas.update>;

export type SSOOIDCInitiateInput = z.infer<typeof ssoSchemas.oidcInitiate>;
export type SSOOIDCCallbackInput = z.infer<typeof ssoSchemas.oidcCallback>;
export type SSOSAMLInitiateInput = z.infer<typeof ssoSchemas.samlInitiate>;
export type SSOSAMLCallbackInput = z.infer<typeof ssoSchemas.samlCallback>;
export type SSOSAMLLogoutInput = z.infer<typeof ssoSchemas.samlLogout>;

export type ImportExportFiltersInput = z.infer<typeof importExportSchemas.exportFilters>;
export type ImportOptionsInput = z.infer<typeof importExportSchemas.importOptions>;
export type ImportFileInput = z.infer<typeof importExportSchemas.importFile>;

export type SearchInput = z.infer<typeof searchSchemas.search>;
export type UserFiltersInput = z.infer<typeof searchSchemas.userFilters>;
export type ProjectFiltersInput = z.infer<typeof searchSchemas.projectFilters>;
export type TaskFiltersInput = z.infer<typeof searchSchemas.taskFilters>;

export type MFAEnableInput = z.infer<typeof mfaSchemas.enable>;
export type MFAVerifyInput = z.infer<typeof mfaSchemas.verify>;
export type MFARecoveryInput = z.infer<typeof mfaSchemas.recovery>;

export type EmailSendVerificationInput = z.infer<typeof emailSchemas.sendVerification>;
export type EmailVerifyInput = z.infer<typeof emailSchemas.verify>;
export type EmailChangeInput = z.infer<typeof emailSchemas.changeEmail>;
export type EmailConfirmChangeInput = z.infer<typeof emailSchemas.confirmChange>;

export type SessionRevokeInput = z.infer<typeof sessionSchemas.revoke>;
export type SessionMarkTrustedInput = z.infer<typeof sessionSchemas.markTrusted>;
