'use client';

import { useState, useEffect } from 'react';
import type { Group, CreateGroupData, UpdateGroupData } from '@/lib/api';

interface GroupModalProps {
  group?: Group | null;
  onSave: (data: CreateGroupData | UpdateGroupData) => Promise<void>;
  onClose: () => void;
  onDelete?: () => Promise<void>;
}

export default function GroupModal({ group, onSave, onClose, onDelete }: GroupModalProps) {
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [sortOrder, setSortOrder] = useState(0);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const isEdit = !!group;

  useEffect(() => {
    if (group) {
      setName(group.name);
      setDescription(group.description || '');
      setSortOrder(group.sort_order);
    }
  }, [group]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSaving(true);

    try {
      await onSave({
        name,
        description: description || undefined,
        sort_order: sortOrder,
      });
      onClose();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to save');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!onDelete || !confirm('Delete this group? Targets will be ungrouped.')) return;
    
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
          {isEdit ? 'Edit Group' : 'Add Group'}
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
              placeholder="DNS Servers"
              required
            />
          </div>

          <div className="mb-4">
            <label className="block text-sm text-zinc-400 mb-1">Description</label>
            <input
              type="text"
              value={description}
              onChange={e => setDescription(e.target.value)}
              className="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-2 text-white"
              placeholder="Public DNS resolvers"
            />
          </div>

          <div className="mb-6">
            <label className="block text-sm text-zinc-400 mb-1">Sort Order</label>
            <input
              type="number"
              value={sortOrder}
              onChange={e => setSortOrder(parseInt(e.target.value) || 0)}
              className="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-2 text-white"
            />
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
