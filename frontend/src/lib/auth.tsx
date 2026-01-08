'use client';

import { createContext, useContext, useEffect, useState, ReactNode } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import { User, fetchCurrentUser, logout as apiLogout, getAuthToken } from './api';

interface AuthContextType {
  user: User | null;
  loading: boolean;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const router = useRouter();
  const pathname = usePathname();

  const refreshUser = async () => {
    try {
      const userData = await fetchCurrentUser();
      setUser(userData);
    } catch {
      setUser(null);
    }
  };

  useEffect(() => {
    const checkAuth = async () => {
      const token = getAuthToken();
      const isLoginPage = pathname === '/login' || pathname === '/login/';
      
      if (!token) {
        setLoading(false);
        if (!isLoginPage) {
          window.location.href = '/roundtrip/login/';
        }
        return;
      }

      try {
        const userData = await fetchCurrentUser();
        setUser(userData);
        if (isLoginPage) {
          window.location.href = '/roundtrip/';
        }
      } catch {
        setUser(null);
        if (!isLoginPage) {
          window.location.href = '/roundtrip/login/';
        }
      } finally {
        setLoading(false);
      }
    };

    checkAuth();
  }, [pathname, router]);

  const logout = async () => {
    await apiLogout();
    setUser(null);
    window.location.href = '/roundtrip/login';
  };

  return (
    <AuthContext.Provider value={{ user, loading, logout, refreshUser }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
