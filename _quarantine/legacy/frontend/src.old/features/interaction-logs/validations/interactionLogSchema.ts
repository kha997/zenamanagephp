import { z } from 'zod';

/**
 * Schema validation cho việc tạo mới Interaction Log
 */
export const createInteractionLogSchema = z.object({
  project_id: z.number({
    required_error: 'Project ID là bắt buộc',
    invalid_type_error: 'Project ID phải là số'
  }).positive('Project ID phải là số dương'),

  linked_task_id: z.number({
    invalid_type_error: 'Task ID phải là số'
  }).positive('Task ID phải là số dương').optional().nullable(),

  type: z.enum(['call', 'email', 'meeting', 'note', 'feedback'], {
    required_error: 'Loại tương tác là bắt buộc',
    invalid_type_error: 'Loại tương tác không hợp lệ'
  }),

  description: z.string({
    required_error: 'Mô tả là bắt buộc',
    invalid_type_error: 'Mô tả phải là chuỗi ký tự'
  })
    .min(10, 'Mô tả phải có ít nhất 10 ký tự')
    .max(5000, 'Mô tả không được vượt quá 5000 ký tự')
    .trim(),

  tag_path: z.string({
    invalid_type_error: 'Tag path phải là chuỗi ký tự'
  })
    .max(255, 'Tag path không được vượt quá 255 ký tự')
    .regex(/^[a-zA-Z0-9\/\-_\s]*$/, 'Tag path chỉ được chứa chữ cái, số, dấu gạch ngang, gạch dưới và dấu /')
    .optional()
    .nullable(),

  visibility: z.enum(['internal', 'client'], {
    required_error: 'Mức độ hiển thị là bắt buộc',
    invalid_type_error: 'Mức độ hiển thị không hợp lệ'
  }),

  client_approved: z.boolean({
    invalid_type_error: 'Trạng thái duyệt phải là boolean'
  }).optional().default(false)
});

/**
 * Schema validation cho việc cập nhật Interaction Log
 */
export const updateInteractionLogSchema = createInteractionLogSchema.partial().extend({
  id: z.number({
    required_error: 'ID là bắt buộc',
    invalid_type_error: 'ID phải là số'
  }).positive('ID phải là số dương')
});

/**
 * Schema validation cho filters
 */
export const interactionLogFiltersSchema = z.object({
  project_id: z.number().positive().optional(),
  type: z.enum(['call', 'email', 'meeting', 'note', 'feedback']).optional(),
  visibility: z.enum(['internal', 'client']).optional(),
  client_approved: z.boolean().optional(),
  tag_path: z.string().max(255).optional(),
  created_by: z.number().positive().optional(),
  date_from: z.string().datetime().optional(),
  date_to: z.string().datetime().optional(),
  search: z.string().max(255).optional()
});

/**
 * Schema validation cho client approval
 */
export const clientApprovalSchema = z.object({
  id: z.number({
    required_error: 'ID là bắt buộc',
    invalid_type_error: 'ID phải là số'
  }).positive('ID phải là số dương'),
  
  approved: z.boolean({
    required_error: 'Trạng thái duyệt là bắt buộc',
    invalid_type_error: 'Trạng thái duyệt phải là boolean'
  }),
  
  note: z.string({
    invalid_type_error: 'Ghi chú phải là chuỗi ký tự'
  })
    .max(1000, 'Ghi chú không được vượt quá 1000 ký tự')
    .optional()
});

/**
 * Schema validation cho bulk operations
 */
export const bulkOperationSchema = z.object({
  ids: z.array(z.number().positive(), {
    required_error: 'Danh sách ID là bắt buộc',
    invalid_type_error: 'Danh sách ID phải là mảng số'
  }).min(1, 'Phải chọn ít nhất 1 item'),
  
  action: z.enum(['delete', 'approve', 'reject', 'change_visibility'], {
    required_error: 'Hành động là bắt buộc',
    invalid_type_error: 'Hành động không hợp lệ'
  }),
  
  params: z.record(z.any()).optional()
});

// Export các type được infer từ schema
export type CreateInteractionLogForm = z.infer<typeof createInteractionLogSchema>;
export type UpdateInteractionLogForm = z.infer<typeof updateInteractionLogSchema>;
export type InteractionLogFilters = z.infer<typeof interactionLogFiltersSchema>;
export type ClientApprovalForm = z.infer<typeof clientApprovalSchema>;
export type BulkOperationForm = z.infer<typeof bulkOperationSchema>;

// Utility functions để validate dữ liệu
export const validateCreateInteractionLog = (data: unknown) => {
  return createInteractionLogSchema.safeParse(data);
};

export const validateUpdateInteractionLog = (data: unknown) => {
  return updateInteractionLogSchema.safeParse(data);
};

export const validateInteractionLogFilters = (data: unknown) => {
  return interactionLogFiltersSchema.safeParse(data);
};

export const validateClientApproval = (data: unknown) => {
  return clientApprovalSchema.safeParse(data);
};

export const validateBulkOperation = (data: unknown) => {
  return bulkOperationSchema.safeParse(data);
};