// frontend/src/entities/tasks/hooks.ts

import { useQuery } from '@tanstack/react-query';
import { getTasks } from '@/entities/tasks/api';
import { useTenantId } from '@/shared/hooks/useTenantId';

export const useTasks = (params: any) => {
  const tenantId = useTenantId();
  const queryParams = { ...params, tenantId };
  
  return useQuery({
    queryKey: ['tasks', queryParams],
    queryFn: () => getTasks(queryParams),
  });
};
