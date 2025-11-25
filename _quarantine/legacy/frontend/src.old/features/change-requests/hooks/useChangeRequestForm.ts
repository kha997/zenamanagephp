import { useState, useCallback } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import {
  createChangeRequestSchema,
  updateChangeRequestSchema,
  CreateChangeRequestFormData,
  UpdateChangeRequestFormData
} from '../validations/changeRequestValidation';
import { ChangeRequest, CreateChangeRequestData, UpdateChangeRequestData } from '../types/changeRequest';

/**
 * Hook để quản lý form change request với validation và state management
 */
export const useChangeRequestForm = (
  mode: 'create' | 'edit',
  initialData?: ChangeRequest,
  onSubmit?: (data: CreateChangeRequestData | UpdateChangeRequestData) => void
) => {
  const [kpiEntries, setKpiEntries] = useState<Array<{ metric: string; impact: string }>>(
    initialData?.impact_kpi || [{ metric: '', impact: '' }]
  );

  const schema = mode === 'create' ? createChangeRequestSchema : updateChangeRequestSchema;
  
  const form = useForm<CreateChangeRequestFormData | UpdateChangeRequestFormData>({
    resolver: zodResolver(schema),
    defaultValues: mode === 'edit' && initialData ? {
      title: initialData.title,
      description: initialData.description,
      impact_days: initialData.impact_days,
      impact_cost: initialData.impact_cost,
      impact_kpi: initialData.impact_kpi,
    } : {
      title: '',
      description: '',
      impact_days: 0,
      impact_cost: 0,
      impact_kpi: [{ metric: '', impact: '' }],
    },
    mode: 'onChange',
  });

  // Quản lý KPI entries
  const addKpiEntry = useCallback(() => {
    const newEntries = [...kpiEntries, { metric: '', impact: '' }];
    setKpiEntries(newEntries);
    form.setValue('impact_kpi', newEntries);
  }, [kpiEntries, form]);

  const removeKpiEntry = useCallback((index: number) => {
    if (kpiEntries.length > 1) {
      const newEntries = kpiEntries.filter((_, i) => i !== index);
      setKpiEntries(newEntries);
      form.setValue('impact_kpi', newEntries);
    }
  }, [kpiEntries, form]);

  const updateKpiEntry = useCallback((index: number, field: 'metric' | 'impact', value: string) => {
    const newEntries = kpiEntries.map((entry, i) => 
      i === index ? { ...entry, [field]: value } : entry
    );
    setKpiEntries(newEntries);
    form.setValue('impact_kpi', newEntries);
  }, [kpiEntries, form]);

  // Reset form
  const resetForm = useCallback(() => {
    form.reset();
    setKpiEntries([{ metric: '', impact: '' }]);
  }, [form]);

  // Handle submit
  const handleSubmit = form.handleSubmit((data) => {
    if (onSubmit) {
      onSubmit(data as CreateChangeRequestData | UpdateChangeRequestData);
    }
  });

  return {
    // Form instance
    form,
    
    // Form state
    formState: form.formState,
    errors: form.formState.errors,
    isValid: form.formState.isValid,
    isDirty: form.formState.isDirty,
    isSubmitting: form.formState.isSubmitting,
    
    // KPI management
    kpiEntries,
    addKpiEntry,
    removeKpiEntry,
    updateKpiEntry,
    
    // Actions
    handleSubmit,
    resetForm,
    setValue: form.setValue,
    getValues: form.getValues,
    watch: form.watch,
    
    // Validation helpers
    validateField: (fieldName: string) => form.trigger(fieldName),
    clearErrors: form.clearErrors,
  };
};