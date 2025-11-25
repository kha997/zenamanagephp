/**
 * Component Validation Schemas
 * Sử dụng Zod để validate form data
 */
import { z } from 'zod';

export const createComponentSchema = z.object({
  name: z
    .string()
    .min(1, 'Tên component là bắt buộc')
    .max(255, 'Tên component không được vượt quá 255 ký tự')
    .trim(),
  
  parent_component_id: z
    .string()
    .optional()
    .nullable(),
  
  planned_cost: z
    .number()
    .min(0, 'Chi phí kế hoạch phải lớn hơn hoặc bằng 0')
    .max(999999999999, 'Chi phí kế hoạch quá lớn'),
  
  progress_percent: z
    .number()
    .min(0, 'Tiến độ phải từ 0%')
    .max(100, 'Tiến độ không được vượt quá 100%')
    .optional()
    .default(0)
});

export const updateComponentSchema = createComponentSchema.partial().extend({
  actual_cost: z
    .number()
    .min(0, 'Chi phí thực tế phải lớn hơn hoặc bằng 0')
    .max(999999999999, 'Chi phí thực tế quá lớn')
    .optional()
});

export const componentFiltersSchema = z.object({
  search: z.string().optional(),
  parent_component_id: z.string().optional(),
  min_cost: z.number().min(0).optional(),
  max_cost: z.number().min(0).optional(),
  min_progress: z.number().min(0).max(100).optional(),
  max_progress: z.number().min(0).max(100).optional(),
  sort_by: z.enum(['name', 'progress_percent', 'planned_cost', 'actual_cost', 'created_at']).optional(),
  sort_order: z.enum(['asc', 'desc']).optional()
});

export const bulkProgressUpdateSchema = z.object({
  updates: z.array(
    z.object({
      component_id: z.string().min(1, 'ID component là bắt buộc'),
      progress_percent: z.number().min(0).max(100),
      actual_cost: z.number().min(0).optional()
    })
  ).min(1, 'Phải có ít nhất một cập nhật')
});

// Export types từ schemas
export type CreateComponentFormData = z.infer<typeof createComponentSchema>;
export type UpdateComponentFormData = z.infer<typeof updateComponentSchema>;
export type ComponentFiltersFormData = z.infer<typeof componentFiltersSchema>;
export type BulkProgressUpdateFormData = z.infer<typeof bulkProgressUpdateSchema>;

// Validation functions
export const validateCreateComponent = (data: unknown) => {
  return createComponentSchema.safeParse(data);
};

export const validateUpdateComponent = (data: unknown) => {
  return updateComponentSchema.safeParse(data);
};

export const validateComponentFilters = (data: unknown) => {
  return componentFiltersSchema.safeParse(data);
};

export const validateBulkProgressUpdate = (data: unknown) => {
  return bulkProgressUpdateSchema.safeParse(data);
};