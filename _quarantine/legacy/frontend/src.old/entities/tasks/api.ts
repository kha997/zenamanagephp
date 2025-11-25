// frontend/src/entities/tasks/api.ts

import { fetcher } from '@/shared/api/fetcher';

export const getTasks = async (params: any) => {
  const tenantId = params.tenantId;
  delete params.tenantId;
  const url = `/api/tasks?tenantId=${tenantId}`;
  return fetcher(url, { params });
};
