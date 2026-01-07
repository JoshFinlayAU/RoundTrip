'use client';

import type { Target } from '@/lib/api';

interface TargetSelectorProps {
  targets: Target[];
  selectedId: number | null;
  onSelect: (id: number) => void;
  onEdit?: (target: Target) => void;
}

export default function TargetSelector({ targets, selectedId, onSelect, onEdit }: TargetSelectorProps) {
  return (
    <div className="flex flex-wrap gap-2">
      {targets.map(target => (
        <div key={target.id} className="flex items-center">
          <button
            onClick={() => onSelect(target.id)}
            className={`px-4 py-2 rounded-l-lg text-sm font-medium transition-colors ${
              selectedId === target.id
                ? 'bg-blue-600 text-white'
                : 'bg-zinc-800 text-zinc-300 hover:bg-zinc-700'
            }`}
          >
            {target.name}
            <span className="ml-2 text-xs opacity-70">{target.host}</span>
          </button>
          {onEdit && (
            <button
              onClick={() => onEdit(target)}
              className={`px-2 py-2 rounded-r-lg text-sm transition-colors border-l border-zinc-700 ${
                selectedId === target.id
                  ? 'bg-blue-700 text-white hover:bg-blue-800'
                  : 'bg-zinc-800 text-zinc-400 hover:bg-zinc-700'
              }`}
              title="Edit target"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </button>
          )}
        </div>
      ))}
    </div>
  );
}
