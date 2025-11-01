import { useAuthStore } from './store';

export const useAuth = () => {
  const {
    user,
    token,
    isAuthenticated,
    isLoading,
    error,
    login,
    logout,
    setUser,
    setToken,
    setError,
    setLoading,
    clearAuth,
    refreshUser,
  } = useAuthStore();

  return {
    user,
    token,
    isAuthenticated,
    isLoading,
    error,
    login,
    logout,
    setUser,
    setToken,
    setError,
    setLoading,
    clearAuth,
    refreshUser,
  };
};
