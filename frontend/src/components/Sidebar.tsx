'use client';

import { useState, useMemo } from 'react';
import { useRouter } from 'next/navigation';
import {
  ChevronDown,
  ChevronRight,
  Plus,
  Settings,
  Activity,
  Search,
  FolderOpen,
  Folder,
  Server,
  MoreHorizontal,
  LogOut,
  User,
} from 'lucide-react';
import type { Target, Group, User as UserType } from '@/lib/api';

interface SidebarProps {
  targets: Target[];
  groups: Group[];
  selectedTargetId: number | null;
  searchQuery: string;
  onSearchChange: (query: string) => void;
  onSelectTarget: (id: number) => void;
  onEditTarget: (target: Target) => void;
  onAddTarget: () => void;
  onEditGroup: (group: Group) => void;
  onAddGroup: () => void;
  user: UserType | null;
  onLogout: () => void;
}

export default function Sidebar({
  targets,
  groups,
  selectedTargetId,
  searchQuery,
  onSearchChange,
  onSelectTarget,
  onEditTarget,
  onAddTarget,
  onEditGroup,
  onAddGroup,
  user,
  onLogout,
}: SidebarProps) {
  const router = useRouter();
  const [expandedGroups, setExpandedGroups] = useState<Set<number>>(new Set(groups.map(g => g.id)));
  const [showUngrouped, setShowUngrouped] = useState(true);

  const sortedGroups = useMemo(() => {
    return [...groups].sort((a, b) => a.sort_order - b.sort_order);
  }, [groups]);

  const ungroupedTargets = useMemo(() => {
    return targets.filter(t => t.group_id === null);
  }, [targets]);

  const targetsByGroup = useMemo(() => {
    const map = new Map<number, Target[]>();
    for (const target of targets) {
      if (target.group_id !== null) {
        const existing = map.get(target.group_id) || [];
        existing.push(target);
        map.set(target.group_id, existing);
      }
    }
    return map;
  }, [targets]);

  const toggleGroup = (groupId: number) => {
    setExpandedGroups(prev => {
      const next = new Set(prev);
      if (next.has(groupId)) {
        next.delete(groupId);
      } else {
        next.add(groupId);
      }
      return next;
    });
  };

  return (
    <aside className="w-72 h-screen bg-gradient-to-b from-zinc-900 via-zinc-900 to-zinc-950 border-r border-zinc-800/50 flex flex-col overflow-hidden">
      {/* Header */}
      <div className="p-5 border-b border-zinc-800/50">
        <div className="flex items-center gap-3 mb-5">
          <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center shadow-lg shadow-blue-500/20">
            <Activity className="w-5 h-5 text-white" />
          </div>
          <div>
            <h1 className="text-lg font-bold text-white tracking-tight">RoundTrip</h1>
            <p className="text-xs text-zinc-500">Network Monitor</p>
          </div>
        </div>

        {/* Search */}
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-500" />
          <input
            type="text"
            value={searchQuery}
            onChange={e => onSearchChange(e.target.value)}
            placeholder="Search targets..."
            className="w-full bg-zinc-800/50 border border-zinc-700/50 rounded-lg pl-10 pr-4 py-2.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50 transition-all"
          />
        </div>
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto py-4 px-3 space-y-1 scrollbar-thin scrollbar-thumb-zinc-700 scrollbar-track-transparent">
        {/* Groups Section Header */}
        <div className="flex items-center justify-between px-2 mb-2">
          <span className="text-xs font-semibold text-zinc-500 uppercase tracking-wider">Groups</span>
          <button
            onClick={onAddGroup}
            className="p-1 rounded-md text-zinc-500 hover:text-white hover:bg-zinc-800 transition-colors"
            title="Add Group"
          >
            <Plus className="w-4 h-4" />
          </button>
        </div>

        {/* Groups */}
        {sortedGroups.map(group => {
          const groupTargets = targetsByGroup.get(group.id) || [];
          const isExpanded = expandedGroups.has(group.id);

          return (
            <div key={group.id} className="mb-1">
              <div
                className="group flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-zinc-800/50 cursor-pointer transition-all"
                onClick={() => toggleGroup(group.id)}
              >
                <button className="text-zinc-500 hover:text-white transition-colors">
                  {isExpanded ? (
                    <ChevronDown className="w-4 h-4" />
                  ) : (
                    <ChevronRight className="w-4 h-4" />
                  )}
                </button>
                {isExpanded ? (
                  <FolderOpen className="w-4 h-4 text-amber-500" />
                ) : (
                  <Folder className="w-4 h-4 text-zinc-500" />
                )}
                <span className="flex-1 text-sm font-medium text-zinc-300 truncate">
                  {group.name}
                </span>
                <span className="text-xs text-zinc-600 tabular-nums">
                  {groupTargets.length}
                </span>
                <button
                  onClick={e => {
                    e.stopPropagation();
                    onEditGroup(group);
                  }}
                  className="p-1 rounded opacity-0 group-hover:opacity-100 text-zinc-500 hover:text-white hover:bg-zinc-700 transition-all"
                  title="Edit Group"
                >
                  <Settings className="w-3.5 h-3.5" />
                </button>
              </div>

              {/* Group Targets */}
              {isExpanded && groupTargets.length > 0 && (
                <div className="ml-4 pl-4 border-l border-zinc-800 space-y-0.5 mt-1">
                  {groupTargets.map(target => (
                    <TargetItem
                      key={target.id}
                      target={target}
                      isSelected={selectedTargetId === target.id}
                      onSelect={() => onSelectTarget(target.id)}
                      onEdit={() => onEditTarget(target)}
                    />
                  ))}
                </div>
              )}
            </div>
          );
        })}

        {/* Ungrouped Targets */}
        {ungroupedTargets.length > 0 && (
          <div className="mt-4">
            <div
              className="group flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-zinc-800/50 cursor-pointer transition-all"
              onClick={() => setShowUngrouped(!showUngrouped)}
            >
              <button className="text-zinc-500 hover:text-white transition-colors">
                {showUngrouped ? (
                  <ChevronDown className="w-4 h-4" />
                ) : (
                  <ChevronRight className="w-4 h-4" />
                )}
              </button>
              <Server className="w-4 h-4 text-zinc-500" />
              <span className="flex-1 text-sm font-medium text-zinc-400 truncate">
                Ungrouped
              </span>
              <span className="text-xs text-zinc-600 tabular-nums">
                {ungroupedTargets.length}
              </span>
            </div>

            {showUngrouped && (
              <div className="ml-4 pl-4 border-l border-zinc-800 space-y-0.5 mt-1">
                {ungroupedTargets.map(target => (
                  <TargetItem
                    key={target.id}
                    target={target}
                    isSelected={selectedTargetId === target.id}
                    onSelect={() => onSelectTarget(target.id)}
                    onEdit={() => onEditTarget(target)}
                  />
                ))}
              </div>
            )}
          </div>
        )}
      </nav>

      {/* Footer */}
      <div className="p-3 border-t border-zinc-800/50 space-y-3">
        <button
          onClick={onAddTarget}
          className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-violet-600 hover:from-blue-500 hover:to-violet-500 text-white text-sm font-medium rounded-lg transition-all shadow-lg shadow-blue-500/20 hover:shadow-blue-500/30"
        >
          <Plus className="w-4 h-4" />
          Add Target
        </button>

        {/* User Menu */}
        {user && (
          <div className="flex items-center gap-2">
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-zinc-300 truncate">{user.name}</p>
              <p className="text-xs text-zinc-500 truncate">{user.email}</p>
            </div>
            <button
              onClick={() => router.push('/roundtrip/settings')}
              className="p-2 rounded-lg text-zinc-500 hover:text-white hover:bg-zinc-800 transition-colors"
              title="Settings"
            >
              <User className="w-4 h-4" />
            </button>
            <button
              onClick={onLogout}
              className="p-2 rounded-lg text-zinc-500 hover:text-red-400 hover:bg-zinc-800 transition-colors"
              title="Logout"
            >
              <LogOut className="w-4 h-4" />
            </button>
          </div>
        )}
      </div>
    </aside>
  );
}

function TargetItem({
  target,
  isSelected,
  onSelect,
  onEdit,
}: {
  target: Target;
  isSelected: boolean;
  onSelect: () => void;
  onEdit: () => void;
}) {
  return (
    <div
      className={`group flex items-center gap-2 px-3 py-2 rounded-lg cursor-pointer transition-all ${
        isSelected
          ? 'bg-gradient-to-r from-blue-600/20 to-violet-600/20 border border-blue-500/30 text-white'
          : 'hover:bg-zinc-800/50 text-zinc-400 hover:text-zinc-200'
      }`}
      onClick={onSelect}
    >
      <div
        className={`w-2 h-2 rounded-full ${
          target.enabled
            ? isSelected
              ? 'bg-emerald-400 shadow-lg shadow-emerald-400/50'
              : 'bg-emerald-500'
            : 'bg-zinc-600'
        }`}
      />
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium truncate">{target.name}</p>
        <p className="text-xs text-zinc-500 truncate">{target.host}</p>
      </div>
      <button
        onClick={e => {
          e.stopPropagation();
          onEdit();
        }}
        className={`p-1 rounded opacity-0 group-hover:opacity-100 transition-all ${
          isSelected
            ? 'text-blue-300 hover:text-white hover:bg-blue-500/20'
            : 'text-zinc-500 hover:text-white hover:bg-zinc-700'
        }`}
        title="Edit Target"
      >
        <MoreHorizontal className="w-4 h-4" />
      </button>
    </div>
  );
}
