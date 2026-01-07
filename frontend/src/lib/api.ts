const API_BASE = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

// Token management
let authToken: string | null = null;

export function setAuthToken(token: string | null) {
  authToken = token;
  if (token) {
    localStorage.setItem('auth_token', token);
  } else {
    localStorage.removeItem('auth_token');
  }
}

export function getAuthToken(): string | null {
  if (authToken) return authToken;
  if (typeof window !== 'undefined') {
    authToken = localStorage.getItem('auth_token');
  }
  return authToken;
}

function authHeaders(): HeadersInit {
  const token = getAuthToken();
  const headers: HeadersInit = { 'Content-Type': 'application/json' };
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }
  return headers;
}

async function handleResponse<T>(res: Response): Promise<T> {
  if (res.status === 401) {
    setAuthToken(null);
    if (typeof window !== 'undefined') {
      window.location.href = '/roundtrip/login';
    }
    throw new Error('Unauthorized');
  }
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || 'Request failed');
  }
  return res.json();
}

// Auth types and functions
export interface User {
  id: number;
  name: string;
  email: string;
}

export interface LoginResponse {
  user: User;
  token: string;
}

export async function login(email: string, password: string): Promise<LoginResponse> {
  const res = await fetch(`${API_BASE}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || 'Login failed');
  }
  const data = await res.json();
  setAuthToken(data.token);
  return data;
}

export async function logout(): Promise<void> {
  const token = getAuthToken();
  if (token) {
    await fetch(`${API_BASE}/auth/logout`, {
      method: 'POST',
      headers: authHeaders(),
    }).catch(() => {});
  }
  setAuthToken(null);
}

export async function fetchCurrentUser(): Promise<User> {
  const res = await fetch(`${API_BASE}/auth/user`, {
    headers: authHeaders(),
  });
  return handleResponse<User>(res);
}

export async function updatePassword(currentPassword: string, password: string, passwordConfirmation: string): Promise<void> {
  const res = await fetch(`${API_BASE}/auth/password`, {
    method: 'PUT',
    headers: authHeaders(),
    body: JSON.stringify({
      current_password: currentPassword,
      password,
      password_confirmation: passwordConfirmation,
    }),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || err.errors?.current_password?.[0] || 'Failed to update password');
  }
}

export async function updateProfile(name: string, email: string): Promise<User> {
  const res = await fetch(`${API_BASE}/auth/profile`, {
    method: 'PUT',
    headers: authHeaders(),
    body: JSON.stringify({ name, email }),
  });
  return handleResponse<User>(res);
}

export interface Group {
  id: number;
  name: string;
  description: string | null;
  sort_order: number;
  targets?: Target[];
}

export interface Target {
  id: number;
  name: string;
  host: string;
  interval_seconds: number;
  enabled: boolean;
  group_id: number | null;
}

export interface PingPoint {
  ts: string;
  // Individual RTT (short ranges)
  rtt_ms?: number | null;
  seq?: number;
  lost?: boolean;
  // Aggregated (long ranges)
  min_ms?: number | null;
  avg_ms?: number | null;
  max_ms?: number | null;
  loss_pct?: number | null;
}

export interface SeriesResponse {
  target: Target;
  from: string;
  to: string;
  points: PingPoint[];
}

export async function fetchTargets(options?: { q?: string; group_id?: number }): Promise<Target[]> {
  const params = new URLSearchParams();
  if (options?.q) params.set('q', options.q);
  if (options?.group_id) params.set('group_id', String(options.group_id));
  
  const url = `${API_BASE}/targets${params.toString() ? '?' + params.toString() : ''}`;
  const res = await fetch(url, { headers: authHeaders() });
  return handleResponse<Target[]>(res);
}

export async function fetchSeries(targetId: number, from?: string, to?: string): Promise<SeriesResponse> {
  const params = new URLSearchParams();
  if (from) params.set('from', from);
  if (to) params.set('to', to);
  
  const url = `${API_BASE}/targets/${targetId}/series${params.toString() ? '?' + params.toString() : ''}`;
  const res = await fetch(url, { headers: authHeaders() });
  return handleResponse<SeriesResponse>(res);
}

export interface CreateTargetData {
  name: string;
  host: string;
  interval_seconds?: number;
  enabled?: boolean;
  group_id?: number | null;
}

export interface UpdateTargetData {
  name?: string;
  host?: string;
  interval_seconds?: number;
  enabled?: boolean;
  group_id?: number | null;
}

export interface CreateGroupData {
  name: string;
  description?: string;
  sort_order?: number;
}

export interface UpdateGroupData {
  name?: string;
  description?: string;
  sort_order?: number;
}

export async function createTarget(data: CreateTargetData): Promise<Target> {
  const res = await fetch(`${API_BASE}/targets`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify(data),
  });
  return handleResponse<Target>(res);
}

export async function updateTarget(id: number, data: UpdateTargetData): Promise<Target> {
  const res = await fetch(`${API_BASE}/targets/${id}`, {
    method: 'PUT',
    headers: authHeaders(),
    body: JSON.stringify(data),
  });
  return handleResponse<Target>(res);
}

export async function deleteTarget(id: number): Promise<void> {
  const res = await fetch(`${API_BASE}/targets/${id}`, {
    method: 'DELETE',
    headers: authHeaders(),
  });
  if (!res.ok) {
    if (res.status === 401) {
      setAuthToken(null);
      window.location.href = '/roundtrip/login';
    }
    throw new Error('Failed to delete target');
  }
}

export async function fetchGroups(): Promise<Group[]> {
  const res = await fetch(`${API_BASE}/groups`, { headers: authHeaders() });
  return handleResponse<Group[]>(res);
}

export async function createGroup(data: CreateGroupData): Promise<Group> {
  const res = await fetch(`${API_BASE}/groups`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify(data),
  });
  return handleResponse<Group>(res);
}

export async function updateGroup(id: number, data: UpdateGroupData): Promise<Group> {
  const res = await fetch(`${API_BASE}/groups/${id}`, {
    method: 'PUT',
    headers: authHeaders(),
    body: JSON.stringify(data),
  });
  return handleResponse<Group>(res);
}

export async function deleteGroup(id: number): Promise<void> {
  const res = await fetch(`${API_BASE}/groups/${id}`, {
    method: 'DELETE',
    headers: authHeaders(),
  });
  if (!res.ok) {
    if (res.status === 401) {
      setAuthToken(null);
      window.location.href = '/roundtrip/login';
    }
    throw new Error('Failed to delete group');
  }
}
