import React, { useState, useEffect } from 'react';
import { Input } from '../../../components/ui/primitives/Input';
import { Button } from '../../../components/ui/primitives/Button';
import type { ContractPayment, CreatePaymentData, UpdatePaymentData } from '../types';
import type { ApiError } from '../../../shared/api/client';

interface ContractPaymentFormProps {
  contractId: string | number;
  payment?: ContractPayment | null; // null for create, ContractPayment for edit
  contractCurrency?: string; // Default currency from contract
  onSubmit: (data: CreatePaymentData | UpdatePaymentData) => Promise<void>;
  onCancel: () => void;
  isLoading?: boolean;
  error?: ApiError | null;
}

/**
 * ContractPaymentForm - Form component for creating/updating contract payments
 * 
 * Round 39: React UI cho Contracts & Payment Schedule
 * 
 * Features:
 * - Create/Update payment
 * - Handle PAYMENT_TOTAL_EXCEEDED error with field-level validation
 * - Form validation
 * - Currency defaulting from contract
 */
export const ContractPaymentForm: React.FC<ContractPaymentFormProps> = ({
  contractId,
  payment,
  contractCurrency = 'USD',
  onSubmit,
  onCancel,
  isLoading = false,
  error,
}) => {
  const isEditMode = !!payment;
  
  const [formData, setFormData] = useState<CreatePaymentData>({
    name: payment?.name || '',
    code: payment?.code || '',
    type: payment?.type || 'milestone',
    due_date: payment?.due_date ? payment.due_date.split('T')[0] : '',
    amount: payment?.amount || 0,
    currency: payment?.currency || contractCurrency,
    status: payment?.status || 'planned',
    notes: payment?.notes || '',
    sort_order: payment?.sort_order || 0,
  });
  
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  
  // Parse error and set field errors
  useEffect(() => {
    if (error) {
      const newFieldErrors: Record<string, string> = {};
      
      // Get error details - support multiple envelope formats
      const details = error.details as any;
      const raw = details ?? {};
      
      // Try to extract validation errors from different formats:
      // 1. details.validation (preferred format)
      // 2. details.errors (fallback format)
      // 3. details itself (if it's a flat map)
      const validation =
        raw.validation ??
        raw.errors ??
        (typeof raw === 'object' && !Array.isArray(raw) && !raw.context ? raw : null);
      
      // Handle PAYMENT_TOTAL_EXCEEDED error
      if (error.code === 'PAYMENT_TOTAL_EXCEEDED') {
        // Error details should be in details.validation.amount (or details.errors.amount)
        if (validation?.amount) {
          const amountError = Array.isArray(validation.amount)
            ? validation.amount[0]
            : validation.amount;
          newFieldErrors.amount = amountError || 'Total payments would exceed contract total value';
        } else {
          // Fallback: use error message
          newFieldErrors.amount = error.message || 'Total payments would exceed contract total value';
        }
        
        // Log context if available for debugging
        if (raw?.context) {
          console.warn('[ContractPaymentForm] PAYMENT_TOTAL_EXCEEDED context:', raw.context);
        }
      } else if (validation) {
        // Handle other validation errors from any supported format
        Object.entries(validation).forEach(([field, messages]) => {
          // Skip context and other non-field properties
          if (field === 'context' || field === 'traceId' || field === 'id') {
            return;
          }
          
          const messageArray = Array.isArray(messages) ? messages : [messages];
          if (messageArray.length > 0 && messageArray[0]) {
            newFieldErrors[field] = String(messageArray[0]);
          }
        });
      }
      
      setFieldErrors(newFieldErrors);
    } else {
      setFieldErrors({});
    }
  }, [error]);
  
  const handleChange = (field: keyof CreatePaymentData, value: string | number) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    // Clear field error when user types
    if (fieldErrors[field]) {
      setFieldErrors((prev) => {
        const newErrors = { ...prev };
        delete newErrors[field];
        return newErrors;
      });
    }
  };
  
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    // Basic validation
    if (!formData.name.trim()) {
      setFieldErrors({ name: 'Tên đợt thanh toán là bắt buộc' });
      return;
    }
    
    if (!formData.due_date) {
      setFieldErrors({ due_date: 'Ngày đến hạn là bắt buộc' });
      return;
    }
    
    if (!formData.amount || formData.amount <= 0) {
      setFieldErrors({ amount: 'Số tiền phải lớn hơn 0' });
      return;
    }
    
    try {
      await onSubmit(formData);
    } catch (err) {
      // Error handling is done in useEffect via error prop
      console.error('Form submission error:', err);
    }
  };
  
  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div>
        <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
          Tên / Label <span className="text-red-500">*</span>
        </label>
        <Input
          value={formData.name}
          onChange={(e) => handleChange('name', e.target.value)}
          placeholder="Nhập tên đợt thanh toán"
          error={fieldErrors.name}
          required
        />
      </div>
      
      <div>
        <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
          Mã đợt thanh toán
        </label>
        <Input
          value={formData.code || ''}
          onChange={(e) => handleChange('code', e.target.value)}
          placeholder="Nhập mã (tùy chọn)"
        />
      </div>
      
      <div>
        <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
          Loại
        </label>
        <select
          value={formData.type || 'milestone'}
          onChange={(e) => handleChange('type', e.target.value)}
          className="w-full h-10 px-3 rounded-lg border border-[var(--color-border-default)] bg-[var(--color-surface-card)] text-[var(--color-text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)]"
        >
          <option value="deposit">Deposit (Đặt cọc)</option>
          <option value="milestone">Milestone (Mốc)</option>
          <option value="progress">Progress (Tiến độ)</option>
          <option value="retention">Retention (Bảo lưu)</option>
          <option value="final">Final (Cuối cùng)</option>
        </select>
      </div>
      
      <div>
        <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
          Ngày đến hạn <span className="text-red-500">*</span>
        </label>
        <Input
          type="date"
          value={formData.due_date}
          onChange={(e) => handleChange('due_date', e.target.value)}
          error={fieldErrors.due_date}
          required
        />
      </div>
      
      <div>
        <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
          Số tiền <span className="text-red-500">*</span>
        </label>
        <Input
          type="number"
          step="0.01"
          min="0.01"
          value={formData.amount}
          onChange={(e) => handleChange('amount', parseFloat(e.target.value) || 0)}
          error={fieldErrors.amount}
          required
        />
        {fieldErrors.amount && (
          <p className="mt-1 text-sm text-red-600">{fieldErrors.amount}</p>
        )}
      </div>
      
      <div>
        <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
          Tiền tệ
        </label>
        <Input
          value={formData.currency || contractCurrency}
          onChange={(e) => handleChange('currency', e.target.value)}
          placeholder="USD, VND, etc."
          maxLength={3}
        />
        <p className="mt-1 text-xs text-[var(--color-text-muted)]">
          Để trống sẽ dùng tiền tệ của hợp đồng ({contractCurrency})
        </p>
      </div>
      
      <div>
        <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
          Trạng thái
        </label>
        <select
          value={formData.status || 'planned'}
          onChange={(e) => handleChange('status', e.target.value)}
          className="w-full h-10 px-3 rounded-lg border border-[var(--color-border-default)] bg-[var(--color-surface-card)] text-[var(--color-text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)]"
        >
          <option value="planned">Planned (Kế hoạch)</option>
          <option value="due">Due (Đến hạn)</option>
          <option value="paid">Paid (Đã thanh toán)</option>
          <option value="overdue">Overdue (Quá hạn)</option>
          <option value="cancelled">Cancelled (Hủy)</option>
        </select>
      </div>
      
      <div>
        <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
          Ghi chú
        </label>
        <textarea
          value={formData.notes || ''}
          onChange={(e) => handleChange('notes', e.target.value)}
          rows={3}
          className="w-full px-3 py-2 rounded-lg border border-[var(--color-border-default)] bg-[var(--color-surface-card)] text-[var(--color-text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)] resize-none"
          placeholder="Nhập ghi chú (tùy chọn)"
        />
      </div>
      
      <div className="flex justify-end gap-2 pt-4">
        <Button type="button" variant="secondary" onClick={onCancel} disabled={isLoading}>
          Hủy
        </Button>
        <Button type="submit" variant="primary" disabled={isLoading}>
          {isLoading ? 'Đang lưu...' : isEditMode ? 'Cập nhật' : 'Tạo'}
        </Button>
      </div>
    </form>
  );
};

