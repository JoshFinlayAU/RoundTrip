const API_BASE = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

export interface Target {
  id: number;
  name: string;
  host: string;
  interval_seconds: number;
  enabled: boolean;
}

export interface PingPoint {
  ts: string;
  min_ms: number | null;
  avg_ms: number | null;
  max_ms: number | null;
  loss_pct: number | null;
}

export interface SeriesResponse {
  target: Target;
  from: string;
  to: string;
  points: PingPoint[];
}

export async function fetchTargets(): Promise<Target[]> {
  const res = await fetch(`${API_BASE}/targets`);
  if (!res.ok) throw new Error('Failed to fetch targets');
  return res.json();
}

export async function fetchSeries(targetId: number, from?: string, to?: string): Promise<SeriesResponse> {
  const params = new URLSearchParams();
  if (from) params.set('from', from);
  if (to) params.set('to', to);
  
  const url = `${API_BASE}/targets/${targetId}/series${params.toString() ? '?' + params.toString() : ''}`;
  const res = await fetch(url);
  if (!res.ok) throw new Error('Failed to fetch series');
  return res.json();
}
