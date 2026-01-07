'use client';

import { useState, useEffect } from 'react';
import type { Target, Group, CreateTargetData, UpdateTargetData } from '@/lib/api';

interface TargetModalProps {
  target?: Target | null;
  groups: Group[];
  onSave: (data: CreateTargetData | UpdateTargetData) => Promise<void>;
  onClose: () => void;
  onDelete?: () => Promise<void>;
}

export default function TargetModal({ target, groups, onSave, onClose, onDelete }: TargetModalProps) {
  const [name, setName] = useState('');
  const [host, setHost] = useState('');
  const [intervalSeconds, setIntervalSeconds] = useState(5);
  const [enabled, setEnabled] = useState(true);
  const [groupId, setGroupId] = useState<number | null>(null);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const isEdit = !!target;

  useEffect(() => {
    if (target) {
      setName(target.name);
      setHost(target.host);
      setIntervalSeconds(target.interval_seconds);
      setEnabled(target.enabled);
      setGroupId(target.group_id);
    }
  }, [target]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSaving(true);

    try {
      await onSave({
        name,
        host,
        interval_seconds: intervalSeconds,
        enabled,
        group_id: groupId,
      });
      onClose();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to save');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!onDelete || !confirm('Delete this target? All historical data will be lost.')) return;
    
    setSaving(true);
    try {
      await onDelete();
      onClose();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to delete');
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-zinc-900 rounded-lg p-6 w-full max-w-md">
        <h2 className="text-xl font-semibold mb-4">
          {isEdit ? 'Edit Target' : 'Add Target'}
        </h2>

        {error && (
          <div className="bg-red-900/50 border border-red-500 text-red-200 px-3 py-2 rounded mb-4 text-sm">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div className="mb-4">
            <label className="block text-sm text-zinc-400 mb-1">Name</label>
            <input
              type="text"
              value={name}
              onChange={e => setName(e.target.value)}
              className="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-2 text-white"
              placeholder="Google DNS"
              required
            />
          </div>

          <div className="mb-4">
            <label className="block text-sm text-zinc-400 mb-1">Host / IP</label>
            <input
              type="text"
              value={host}
              onChange={e => setHost(e.target.value)}
              className="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-2 text-white"
              placeholder="8.8.8.8"
              required
            />
          </div>

          <div className="mb-4">
            <label className="block text-sm text-zinc-400 mb-1">Poll Interval (seconds)</label>
            <input
              type="number"
              value={intervalSeconds}
              onChange={e => setIntervalSeconds(parseInt(e.target.value) || 5)}
              className="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-2 text-white"
              min={1}
              max={3600}
            />
          </div>

          <div className="mb-4">
            <label className="block text-sm text-zinc-400 mb-1">Group</label>
            <select
              value={groupId ?? ''}
              onChange={e => setGroupId(e.target.value ? parseInt(e.target.value) : null)}
              className="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-2 text-white"
            >
              <option value="">No group</option>
              {groups.map(g => (
                <option key={g.id} value={g.id}>{g.name}</option>
              ))}
            </select>
          </div>

          <div className="mb-6">
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={enabled}
                onChange={e => setEnabled(e.target.checked)}
                className="w-4 h-4"
              />
              <span className="text-sm text-zinc-300">Enabled</span>
            </label>
          </div>

          <div className="flex gap-3">
            <button
              type="submit"
              disabled={saving}
              className="flex-1 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white px-4 py-2 rounded font-medium"
            >
              {saving ? 'Saving...' : 'Save'}
            </button>
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded border border-zinc-600 text-zinc-300 hover:bg-zinc-800"
            >
              Cancel
            </button>
            {isEdit && onDelete && (
              <button
                type="button"
                onClick={handleDelete}
                disabled={saving}
                className="px-4 py-2 rounded bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white"
              >
                Delete
              </button>
            )}
          </div>
        </form>
      </div>
    </div>
  );
}
