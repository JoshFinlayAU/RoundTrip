'use client';

import type { Target } from '@/lib/api';

interface TargetSelectorProps {
  targets: Target[];
  selectedId: number | null;
  onSelect: (id: number) => void;
}

export default function TargetSelector({ targets, selectedId, onSelect }: TargetSelectorProps) {
  return (
    <div className="flex flex-wrap gap-2">
      {targets.map(target => (
        <button
          key={target.id}
          onClick={() => onSelect(target.id)}
          className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
            selectedId === target.id
              ? 'bg-blue-600 text-white'
              : 'bg-zinc-800 text-zinc-300 hover:bg-zinc-700'
          }`}
        >
          {target.name}
          <span className="ml-2 text-xs opacity-70">{target.host}</span>
        </button>
      ))}
    </div>
  );
}
