import { useQuery } from '@tanstack/react-query';
import { templatesApi } from './api';
import type { Template } from './api';

export const useTemplates = (filters?: any, pagination?: { page?: number; per_page?: number }) => {
  return useQuery({
    queryKey: ['templates', filters, pagination],
    queryFn: () => templatesApi.getTemplates(filters, pagination),
  });
};

export const useTemplate = (id: string | number) => {
  return useQuery({
    queryKey: ['template', id],
    queryFn: () => templatesApi.getTemplate(id),
    enabled: !!id,
  });
};

export const useTemplatesKpis = (period?: string) => {
  return useQuery({
    queryKey: ['templates', 'kpis', period],
    queryFn: () => templatesApi.getKpis(period),
  });
};

