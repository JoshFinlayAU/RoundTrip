'use client';

import { useEffect, useState, useCallback } from 'react';
import SmokeChart from '@/components/SmokeChart';
import TargetSelector from '@/components/TargetSelector';
import { fetchTargets, fetchSeries, type Target, type PingPoint } from '@/lib/api';

export default function Home() {
  const [targets, setTargets] = useState<Target[]>([]);
  const [selectedTargetId, setSelectedTargetId] = useState<number | null>(null);
  const [points, setPoints] = useState<PingPoint[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadTargets = useCallback(async () => {
    try {
      const data = await fetchTargets();
      setTargets(data);
      if (data.length > 0 && !selectedTargetId) {
        setSelectedTargetId(data[0].id);
      }
    } catch (err) {
      setError('Failed to load targets');
      console.error(err);
    }
  }, [selectedTargetId]);

  const loadSeries = useCallback(async (targetId: number) => {
    try {
      setLoading(true);
      const data = await fetchSeries(targetId);
      setPoints(data.points);
    } catch (err) {
      setError('Failed to load series data');
      console.error(err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadTargets();
  }, [loadTargets]);

  useEffect(() => {
    if (selectedTargetId) {
      loadSeries(selectedTargetId);
    }
  }, [selectedTargetId, loadSeries]);

  // Poll for new data every 5 seconds
  useEffect(() => {
    if (!selectedTargetId) return;

    const interval = setInterval(() => {
      loadSeries(selectedTargetId);
    }, 5000);

    return () => clearInterval(interval);
  }, [selectedTargetId, loadSeries]);

  const selectedTarget = targets.find(t => t.id === selectedTargetId);

  return (
    <div className="min-h-screen bg-zinc-950 text-white p-8">
      <header className="mb-8">
        <h1 className="text-3xl font-bold mb-2">RoundTrip</h1>
        <p className="text-zinc-400">Network latency monitoring</p>
      </header>

      {error && (
        <div className="bg-red-900/50 border border-red-500 text-red-200 px-4 py-3 rounded mb-6">
          {error}
        </div>
      )}

      <section className="mb-6">
        <h2 className="text-lg font-semibold mb-3 text-zinc-300">Targets</h2>
        <TargetSelector
          targets={targets}
          selectedId={selectedTargetId}
          onSelect={setSelectedTargetId}
        />
      </section>

      <section>
        {selectedTarget && (
          <div className="mb-4">
            <h2 className="text-xl font-semibold">{selectedTarget.name}</h2>
            <p className="text-zinc-400 text-sm">{selectedTarget.host}</p>
          </div>
        )}

        {loading && points.length === 0 ? (
          <div className="flex items-center justify-center h-[300px] bg-zinc-900 rounded-lg">
            <p className="text-zinc-500">Loading...</p>
          </div>
        ) : points.length === 0 ? (
          <div className="flex items-center justify-center h-[300px] bg-zinc-900 rounded-lg">
            <p className="text-zinc-500">No data available</p>
          </div>
        ) : (
          <SmokeChart points={points} width={900} height={350} />
        )}

        {points.length > 0 && (
          <div className="mt-4 grid grid-cols-4 gap-4">
            <StatCard
              label="Latest"
              value={points[points.length - 1]?.avg_ms?.toFixed(2) || '-'}
              unit="ms"
            />
            <StatCard
              label="Min"
              value={Math.min(...points.filter(p => p.min_ms !== null).map(p => p.min_ms!)).toFixed(2)}
              unit="ms"
            />
            <StatCard
              label="Max"
              value={Math.max(...points.filter(p => p.max_ms !== null).map(p => p.max_ms!)).toFixed(2)}
              unit="ms"
            />
            <StatCard
              label="Loss"
              value={(points.filter(p => (p.loss_pct || 0) > 0).length / points.length * 100).toFixed(1)}
              unit="%"
            />
          </div>
        )}
      </section>
    </div>
  );
}

function StatCard({ label, value, unit }: { label: string; value: string; unit: string }) {
  return (
    <div className="bg-zinc-900 rounded-lg p-4">
      <p className="text-zinc-400 text-sm mb-1">{label}</p>
      <p className="text-2xl font-semibold">
        {value}
        <span className="text-sm text-zinc-500 ml-1">{unit}</span>
      </p>
    </div>
  );
}
