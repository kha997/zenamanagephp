import React, { createContext, useContext } from 'react';
import { useAuthStore, type User } from '../features/auth/store';

interface AuthContextType {
  user: User | null;
  setUser: (user: User | null) => void;
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
  isAuthenticated: boolean;
  isLoading: boolean;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

/**
 * AuthProvider - Thin adapter that wraps feature auth store
 * This maintains backward compatibility for components using useAuthContext()
 * while delegating all auth logic to the single source of truth: features/auth/store
 */
export function AuthProvider({ children }: { children: React.ReactNode }) {
  const {
    user,
    isAuthenticated,
    isLoading,
    login: storeLogin,
    logout: storeLogout,
    setUser: storeSetUser,
  } = useAuthStore();

  // Provide login function that uses the store
  const login = async (email: string, password: string) => {
    await storeLogin({ email, password });
  };

  // Provide logout function that uses the store
  const logout = async () => {
    await storeLogout();
  };

  // Provide setUser function that uses the store
  const setUser = (user: User | null) => {
    storeSetUser(user);
  };

  const value: AuthContextType = {
    user,
    setUser,
    login,
    logout,
    isAuthenticated,
    isLoading,
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuthContext() {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuthContext must be used within an AuthProvider');
  }
  return context;
}

// Export the context for direct access if needed
export { AuthContext };

