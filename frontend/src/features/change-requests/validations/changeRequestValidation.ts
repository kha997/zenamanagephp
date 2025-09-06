import { z } from 'zod';
import { ChangeRequestStatus } from '../types/changeRequest';

// Schema cho việc tạo change request
export const createChangeRequestSchema = z.object({
  project_id: z.string().min(1, 'Vui lòng chọn dự án'),
  title: z.string().min(1, 'Tiêu đề không được để trống').max(255, 'Tiêu đề không được quá 255 ký tự'),
  description: z.string().min(1, 'Mô tả không được để trống'),
  impact_days: z.number().min(0, 'Số ngày tác động không được âm'),
  impact_cost: z.number().min(0, 'Chi phí tác động không được âm'),
  impact_kpi: z.record(z.string()).optional().default({})
});

// Schema cho việc cập nhật change request
export const updateChangeRequestSchema = z.object({
  title: z.string().min(1, 'Tiêu đề không được để trống').max(255, 'Tiêu đề không được quá 255 ký tự').optional(),
  description: z.string().min(1, 'Mô tả không được để trống').optional(),
  impact_days: z.number().min(0, 'Số ngày tác động không được âm').optional(),
  impact_cost: z.number().min(0, 'Chi phí tác động không được âm').optional(),
  impact_kpi: z.record(z.string()).optional()
});

// Schema cho bộ lọc change requests
export const changeRequestFiltersSchema = z.object({
  search: z.string().optional(),
  status: z.enum(['draft', 'awaiting_approval', 'approved', 'rejected', 'all']).optional(),
  project_id: z.string().optional(),
  created_by: z.string().optional(),
  date_from: z.string().optional(),
  date_to: z.string().optional(),
  sort_by: z.enum(['created_at', 'updated_at', 'impact_cost', 'impact_days']).optional(),
  sort_order: z.enum(['asc', 'desc']).optional(),
  page: z.number().min(1).optional(),
  limit: z.number().min(1).max(100).optional()
});

// Schema cho quyết định change request
export const changeRequestDecisionSchema = z.object({
  decision: z.enum(['approve', 'reject']),
  decision_note: z.string().optional()
});

// Export types từ schemas
export type CreateChangeRequestFormData = z.infer<typeof createChangeRequestSchema>;
export type UpdateChangeRequestFormData = z.infer<typeof updateChangeRequestSchema>;
export type ChangeRequestFiltersFormData = z.infer<typeof changeRequestFiltersSchema>;
export type ChangeRequestDecisionFormData = z.infer<typeof changeRequestDecisionSchema>;

// Validation functions
export const validateCreateChangeRequest = (data: unknown) => {
  return createChangeRequestSchema.safeParse(data);
};

export const validateUpdateChangeRequest = (data: unknown) => {
  return updateChangeRequestSchema.safeParse(data);
};

export const validateChangeRequestFilters = (data: unknown) => {
  return changeRequestFiltersSchema.safeParse(data);
};

export const validateChangeRequestDecision = (data: unknown) => {
  return changeRequestDecisionSchema.safeParse(data);
};