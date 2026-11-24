import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { apiRequest } from '../lib/api';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  role: string | null;
  theme_preferences?: Record<string, unknown> | null;
}

const TOKEN_KEY = 'unisell_admin_token';

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(
    typeof window !== 'undefined' ? window.localStorage.getItem(TOKEN_KEY) : null
  );
  const user = ref<AuthUser | null>(null);

  const isAuthenticated = computed(() => Boolean(token.value));

  async function login(email: string, password: string): Promise<void> {
    const res = await apiRequest<{
      data: { token: string; user: AuthUser };
    }>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({
        email,
        password,
        device_name: 'unisell-admin-web',
      }),
    });

    token.value = res.data.token;
    user.value = res.data.user;
    window.localStorage.setItem(TOKEN_KEY, res.data.token);
  }

  async function fetchProfile(): Promise<AuthUser | null> {
    if (!token.value) return null;
    try {
      const res = await apiRequest<{ data: AuthUser }>('/auth/profile', {}, token.value);
      user.value = res.data;
      return res.data;
    } catch {
      clearSession();
      return null;
    }
  }

  async function logout(): Promise<void> {
    if (token.value) {
      await apiRequest('/auth/logout', { method: 'POST' }, token.value).catch(() => {});
    }
    clearSession();
  }

  function clearSession(): void {
    token.value = null;
    user.value = null;
    if (typeof window !== 'undefined') {
      window.localStorage.removeItem(TOKEN_KEY);
    }
  }

  return {
    token,
    user,
    isAuthenticated,
    login,
    fetchProfile,
    logout,
    clearSession,
  };
});
