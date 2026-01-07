'use client';

import { useEffect, useState, useCallback } from 'react';
import { Activity, Clock, TrendingDown, TrendingUp, AlertTriangle, ZoomIn, ZoomOut } from 'lucide-react';
import SmokeChart from '@/components/SmokeChart';
import Sidebar from '@/components/Sidebar';
import TargetModal from '@/components/TargetModal';
import GroupModal from '@/components/GroupModal';
import { useAuth } from '@/lib/auth';
import { 
  fetchTargets, 
  fetchSeries, 
  fetchGroups,
  createTarget, 
  updateTarget, 
  deleteTarget,
  createGroup,
  updateGroup,
  deleteGroup,
  type Target, 
  type Group,
  type PingPoint,
  type CreateTargetData,
  type UpdateTargetData,
  type CreateGroupData,
  type UpdateGroupData,
} from '@/lib/api';

export default function Home() {
  const { user, loading: authLoading, logout } = useAuth();
  const [targets, setTargets] = useState<Target[]>([]);
  const [groups, setGroups] = useState<Group[]>([]);
  const [selectedTargetId, setSelectedTargetId] = useState<number | null>(null);
  const [points, setPoints] = useState<PingPoint[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showModal, setShowModal] = useState(false);
  const [editingTarget, setEditingTarget] = useState<Target | null>(null);
  const [showGroupModal, setShowGroupModal] = useState(false);
  const [editingGroup, setEditingGroup] = useState<Group | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [timeRange, setTimeRange] = useState<number>(60); // minutes

  const loadData = useCallback(async (search?: string) => {
    try {
      const [targetsData, groupsData] = await Promise.all([
        fetchTargets(search ? { q: search } : undefined),
        fetchGroups(),
      ]);
      setTargets(targetsData);
      setGroups(groupsData);
      if (targetsData.length > 0 && !selectedTargetId) {
        setSelectedTargetId(targetsData[0].id);
      }
    } catch (err) {
      setError('Failed to load data');
      console.error(err);
    }
  }, [selectedTargetId]);

  const loadSeries = useCallback(async (targetId: number, range: number) => {
    try {
      setLoading(true);
      const to = new Date().toISOString();
      const from = new Date(Date.now() - range * 60 * 1000).toISOString();
      const data = await fetchSeries(targetId, from, to);
      setPoints(data.points);
    } catch (err) {
      setError('Failed to load series data');
      console.error(err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData(searchQuery);
  }, [loadData, searchQuery]);

  useEffect(() => {
    if (selectedTargetId) {
      loadSeries(selectedTargetId, timeRange);
    }
  }, [selectedTargetId, timeRange, loadSeries]);

  // Poll for new data every 5 seconds
  useEffect(() => {
    if (!selectedTargetId) return;

    const interval = setInterval(() => {
      loadSeries(selectedTargetId, timeRange);
    }, 5000);

    return () => clearInterval(interval);
  }, [selectedTargetId, timeRange, loadSeries]);

  const selectedTarget = targets.find(t => t.id === selectedTargetId);

  const handleAddTarget = () => {
    setEditingTarget(null);
    setShowModal(true);
  };

  const handleEditTarget = (target: Target) => {
    setEditingTarget(target);
    setShowModal(true);
  };

  const handleSaveTarget = async (data: CreateTargetData | UpdateTargetData) => {
    if (editingTarget) {
      await updateTarget(editingTarget.id, data);
    } else {
      await createTarget(data as CreateTargetData);
    }
    await loadData();
  };

  const handleDeleteTarget = async () => {
    if (!editingTarget) return;
    await deleteTarget(editingTarget.id);
    if (selectedTargetId === editingTarget.id) {
      setSelectedTargetId(null);
      setPoints([]);
    }
    await loadData();
  };

  const handleAddGroup = () => {
    setEditingGroup(null);
    setShowGroupModal(true);
  };

  const handleEditGroup = (group: Group) => {
    setEditingGroup(group);
    setShowGroupModal(true);
  };

  const handleSaveGroup = async (data: CreateGroupData | UpdateGroupData) => {
    if (editingGroup) {
      await updateGroup(editingGroup.id, data);
    } else {
      await createGroup(data as CreateGroupData);
    }
    await loadData();
  };

  const handleDeleteGroup = async () => {
    if (!editingGroup) return;
    await deleteGroup(editingGroup.id);
    await loadData();
  };

  const stats = points.length > 0 ? (() => {
    // Detect if individual RTT data or aggregated
    const isIndividual = points[0]?.rtt_ms !== undefined;
    
    if (isIndividual) {
      const validPoints = points.filter(p => p.rtt_ms !== null && !p.lost);
      const lostPoints = points.filter(p => p.lost);
      const rttValues = validPoints.map(p => p.rtt_ms!);
      
      return {
        latest: rttValues.length > 0 ? rttValues[rttValues.length - 1].toFixed(2) : '-',
        min: rttValues.length > 0 ? Math.min(...rttValues).toFixed(2) : '-',
        max: rttValues.length > 0 ? Math.max(...rttValues).toFixed(2) : '-',
        loss: (lostPoints.length / points.length * 100).toFixed(1),
      };
    } else {
      return {
        latest: points[points.length - 1]?.avg_ms?.toFixed(2) || '-',
        min: Math.min(...points.filter(p => p.min_ms !== null).map(p => p.min_ms!)).toFixed(2),
        max: Math.max(...points.filter(p => p.max_ms !== null).map(p => p.max_ms!)).toFixed(2),
        loss: (points.filter(p => (p.loss_pct || 0) > 0).length / points.length * 100).toFixed(1),
      };
    }
  })() : null;

  if (authLoading) {
    return (
      <div className="flex h-screen bg-zinc-950 items-center justify-center">
        <div className="w-10 h-10 border-2 border-blue-500 border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  if (!user) {
    return null;
  }

  return (
    <div className="flex h-screen bg-zinc-950 text-white overflow-hidden">
      {/* Sidebar */}
      <Sidebar
        targets={targets}
        groups={groups}
        selectedTargetId={selectedTargetId}
        searchQuery={searchQuery}
        onSearchChange={setSearchQuery}
        onSelectTarget={setSelectedTargetId}
        onEditTarget={handleEditTarget}
        onAddTarget={handleAddTarget}
        onEditGroup={handleEditGroup}
        onAddGroup={handleAddGroup}
        user={user}
        onLogout={logout}
      />

      {/* Main Content */}
      <main className="flex-1 overflow-y-auto">
        {/* Top Bar */}
        <header className="sticky top-0 z-10 bg-zinc-950/80 backdrop-blur-xl border-b border-zinc-800/50 px-8 py-4">
          <div className="flex items-center justify-between">
            {selectedTarget ? (
              <div className="flex items-center gap-4">
                <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500/20 to-teal-500/20 border border-emerald-500/30 flex items-center justify-center">
                  <Activity className="w-6 h-6 text-emerald-400" />
                </div>
                <div>
                  <h2 className="text-xl font-bold text-white">{selectedTarget.name}</h2>
                  <p className="text-sm text-zinc-500 font-mono">{selectedTarget.host}</p>
                </div>
              </div>
            ) : (
              <div>
                <h2 className="text-xl font-bold text-white">Select a Target</h2>
                <p className="text-sm text-zinc-500">Choose a target from the sidebar to view metrics</p>
              </div>
            )}

            {selectedTarget && (
              <div className="flex items-center gap-3">
                {/* Time Range Selector */}
                <div className="flex items-center gap-1 bg-zinc-800/50 border border-zinc-700 rounded-lg p-1">
                  {[
                    { value: 15, label: '15m' },
                    { value: 60, label: '1h' },
                    { value: 360, label: '6h' },
                    { value: 1440, label: '24h' },
                    { value: 10080, label: '1w' },
                    { value: 43200, label: '1M' },
                    { value: 129600, label: '3M' },
                    { value: 259200, label: '6M' },
                    { value: 525600, label: '1Y' },
                    { value: 1576800, label: '3Y' },
                  ].map(({ value, label }) => (
                    <button
                      key={value}
                      onClick={() => setTimeRange(value)}
                      className={`px-2.5 py-1 text-xs font-medium rounded-md transition-all ${
                        timeRange === value
                          ? 'bg-blue-600 text-white shadow-sm'
                          : 'text-zinc-400 hover:text-white hover:bg-zinc-700'
                      }`}
                    >
                      {label}
                    </button>
                  ))}
                </div>

                <div className="h-6 w-px bg-zinc-700" />

                <span className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium ${
                  selectedTarget.enabled
                    ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                    : 'bg-zinc-800 text-zinc-500 border border-zinc-700'
                }`}>
                  <span className={`w-1.5 h-1.5 rounded-full ${selectedTarget.enabled ? 'bg-emerald-400' : 'bg-zinc-500'}`} />
                  {selectedTarget.enabled ? 'Active' : 'Disabled'}
                </span>
                <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-zinc-800 text-zinc-400 border border-zinc-700">
                  <Clock className="w-3 h-3" />
                  {selectedTarget.interval_seconds}s interval
                </span>
              </div>
            )}
          </div>
        </header>

        {/* Error Banner */}
        {error && (
          <div className="mx-8 mt-6 flex items-center gap-3 bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl">
            <AlertTriangle className="w-5 h-5 flex-shrink-0" />
            <span className="text-sm">{error}</span>
          </div>
        )}

        {/* Stats Cards */}
        {stats && (
          <div className="px-8 py-6">
            <div className="grid grid-cols-4 gap-4">
              <StatCard
                icon={<Activity className="w-5 h-5" />}
                label="Latest"
                value={stats.latest}
                unit="ms"
                color="blue"
              />
              <StatCard
                icon={<TrendingDown className="w-5 h-5" />}
                label="Minimum"
                value={stats.min}
                unit="ms"
                color="emerald"
              />
              <StatCard
                icon={<TrendingUp className="w-5 h-5" />}
                label="Maximum"
                value={stats.max}
                unit="ms"
                color="amber"
              />
              <StatCard
                icon={<AlertTriangle className="w-5 h-5" />}
                label="Packet Loss"
                value={stats.loss}
                unit="%"
                color={parseFloat(stats.loss) > 0 ? 'red' : 'emerald'}
              />
            </div>
          </div>
        )}

        {/* Chart Section */}
        <section className="px-8 pb-8">
          <div className="bg-zinc-900/50 rounded-2xl border border-zinc-800/50 p-6 backdrop-blur-sm">
            {loading && points.length === 0 ? (
              <div className="flex flex-col items-center justify-center h-[350px]">
                <div className="w-10 h-10 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mb-4" />
                <p className="text-zinc-500">Loading metrics...</p>
              </div>
            ) : points.length === 0 ? (
              <div className="flex flex-col items-center justify-center h-[350px]">
                <div className="w-16 h-16 rounded-2xl bg-zinc-800 flex items-center justify-center mb-4">
                  <Activity className="w-8 h-8 text-zinc-600" />
                </div>
                <p className="text-zinc-400 font-medium mb-1">No data available</p>
                <p className="text-zinc-600 text-sm">Waiting for ping data to arrive...</p>
              </div>
            ) : (
              <SmokeChart points={points} height={350} />
            )}
          </div>
        </section>
      </main>

      {/* Modals */}
      {showModal && (
        <TargetModal
          target={editingTarget}
          groups={groups}
          onSave={handleSaveTarget}
          onClose={() => setShowModal(false)}
          onDelete={editingTarget ? handleDeleteTarget : undefined}
        />
      )}

      {showGroupModal && (
        <GroupModal
          group={editingGroup}
          onSave={handleSaveGroup}
          onClose={() => setShowGroupModal(false)}
          onDelete={editingGroup ? handleDeleteGroup : undefined}
        />
      )}
    </div>
  );
}

interface StatCardProps {
  icon: React.ReactNode;
  label: string;
  value: string;
  unit: string;
  color: 'blue' | 'emerald' | 'amber' | 'red';
}

function StatCard({ icon, label, value, unit, color }: StatCardProps) {
  const colorClasses = {
    blue: 'from-blue-500/20 to-violet-500/20 border-blue-500/30 text-blue-400',
    emerald: 'from-emerald-500/20 to-teal-500/20 border-emerald-500/30 text-emerald-400',
    amber: 'from-amber-500/20 to-orange-500/20 border-amber-500/30 text-amber-400',
    red: 'from-red-500/20 to-rose-500/20 border-red-500/30 text-red-400',
  };

  return (
    <div className={`bg-gradient-to-br ${colorClasses[color]} border rounded-xl p-4 backdrop-blur-sm`}>
      <div className="flex items-center gap-2 mb-2">
        {icon}
        <span className="text-sm font-medium opacity-80">{label}</span>
      </div>
      <p className="text-3xl font-bold text-white">
        {value}
        <span className="text-sm font-normal text-zinc-500 ml-1">{unit}</span>
      </p>
    </div>
  );
}
