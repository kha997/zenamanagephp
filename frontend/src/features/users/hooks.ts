import { useQuery } from '@tanstack/react-query';
import { usersApi } from './api';

export const useUsers = (filters?: { search?: string; role?: string }, pagination?: { page?: number; per_page?: number }) => {
  return useQuery({
    queryKey: ['users', filters, pagination],
    queryFn: () => usersApi.getUsers(filters, pagination),
  });
};

export const useUser = (id: string | number) => {
  return useQuery({
    queryKey: ['user', id],
    queryFn: () => usersApi.getUser(id),
    enabled: !!id,
  });
};

