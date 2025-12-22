import React, { useState, useMemo } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Input } from '../../../components/ui/primitives/Input';
import type { TemplateSet, TemplatePhase, TemplateDiscipline, TemplatePreset } from '../api';

interface TemplateSelectionTabsProps {
  templateSet: TemplateSet | null;
  selectedPreset?: string;
  selectedPhases: string[];
  selectedDisciplines: string[];
  selectedTasks: string[];
  onPresetChange: (presetCode: string | null) => void;
  onPhasesChange: (phaseCodes: string[]) => void;
  onDisciplinesChange: (disciplineCodes: string[]) => void;
  onTasksChange: (taskCodes: string[]) => void;
  options: {
    mapPhaseToKanban: boolean;
    autoAssignByRole: boolean;
    createDeliverableFolders: boolean;
  };
  onOptionsChange: (options: TemplateSelectionTabsProps['options']) => void;
}

type TabType = 'presets' | 'phases' | 'disciplines' | 'tasks';

export const TemplateSelectionTabs: React.FC<TemplateSelectionTabsProps> = ({
  templateSet,
  selectedPreset,
  selectedPhases,
  selectedDisciplines,
  selectedTasks,
  onPresetChange,
  onPhasesChange,
  onDisciplinesChange,
  onTasksChange,
  options,
  onOptionsChange,
}) => {
  const [activeTab, setActiveTab] = useState<TabType>('presets');
  const [searchQuery, setSearchQuery] = useState('');

  const filteredPhases = useMemo(() => {
    if (!templateSet) return [];
    if (!searchQuery) return templateSet.phases;
    const query = searchQuery.toLowerCase();
    return templateSet.phases.filter(
      (p) => p.code.toLowerCase().includes(query) || p.name.toLowerCase().includes(query)
    );
  }, [templateSet, searchQuery]);

  const filteredDisciplines = useMemo(() => {
    if (!templateSet) return [];
    if (!searchQuery) return templateSet.disciplines;
    const query = searchQuery.toLowerCase();
    return templateSet.disciplines.filter(
      (d) => d.code.toLowerCase().includes(query) || d.name.toLowerCase().includes(query)
    );
  }, [templateSet, searchQuery]);

  const handlePresetSelect = (presetCode: string) => {
    if (selectedPreset === presetCode) {
      onPresetChange(null);
    } else {
      onPresetChange(presetCode);
      // Apply preset filters
      const preset = templateSet?.presets.find((p) => p.code === presetCode);
      if (preset) {
        if (preset.filters.phases) {
          onPhasesChange(preset.filters.phases);
        }
        if (preset.filters.disciplines) {
          onDisciplinesChange(preset.filters.disciplines);
        }
        if (preset.filters.tasks) {
          onTasksChange(preset.filters.tasks);
        }
      }
    }
  };

  const handlePhaseToggle = (phaseCode: string) => {
    if (selectedPhases.includes(phaseCode)) {
      onPhasesChange(selectedPhases.filter((c) => c !== phaseCode));
    } else {
      onPhasesChange([...selectedPhases, phaseCode]);
    }
  };

  const handleDisciplineToggle = (disciplineCode: string) => {
    if (selectedDisciplines.includes(disciplineCode)) {
      onDisciplinesChange(selectedDisciplines.filter((c) => c !== disciplineCode));
    } else {
      onDisciplinesChange([...selectedDisciplines, disciplineCode]);
    }
  };

  const handleTaskToggle = (taskCode: string) => {
    if (selectedTasks.includes(taskCode)) {
      onTasksChange(selectedTasks.filter((c) => c !== taskCode));
    } else {
      onTasksChange([...selectedTasks, taskCode]);
    }
  };

  if (!templateSet) {
    return (
      <Card>
        <CardContent>
          <p className="text-gray-500 text-center py-8">No template selected</p>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Select Template Components</CardTitle>
      </CardHeader>
      <CardContent>
        {/* Tabs */}
        <div className="flex border-b border-gray-200 mb-4">
          {(['presets', 'phases', 'disciplines', 'tasks'] as TabType[]).map((tab) => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={`px-4 py-2 font-medium text-sm border-b-2 transition-colors ${
                activeTab === tab
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700'
              }`}
            >
              {tab.charAt(0).toUpperCase() + tab.slice(1)}
            </button>
          ))}
        </div>

        {/* Search */}
        <div className="mb-4">
          <Input
            type="text"
            placeholder="Search by code or name..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
          />
        </div>

        {/* Tab Content */}
        <div className="max-h-96 overflow-y-auto">
          {activeTab === 'presets' && (
            <div className="space-y-2">
              {templateSet.presets.length === 0 ? (
                <p className="text-gray-500 text-center py-4">No presets available</p>
              ) : (
                templateSet.presets.map((preset) => (
                  <label
                    key={preset.id}
                    className="flex items-center p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50"
                  >
                    <input
                      type="radio"
                      name="preset"
                      checked={selectedPreset === preset.code}
                      onChange={() => handlePresetSelect(preset.code)}
                      className="mr-3"
                    />
                    <div className="flex-1">
                      <div className="font-medium text-gray-900">{preset.name}</div>
                      {preset.description && (
                        <div className="text-sm text-gray-500 mt-1">{preset.description}</div>
                      )}
                      <div className="text-xs text-gray-400 mt-1">Code: {preset.code}</div>
                    </div>
                  </label>
                ))
              )}
            </div>
          )}

          {activeTab === 'phases' && (
            <div className="space-y-2">
              {filteredPhases.length === 0 ? (
                <p className="text-gray-500 text-center py-4">No phases found</p>
              ) : (
                filteredPhases.map((phase) => (
                  <label
                    key={phase.id}
                    className="flex items-center p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50"
                  >
                    <input
                      type="checkbox"
                      checked={selectedPhases.includes(phase.code)}
                      onChange={() => handlePhaseToggle(phase.code)}
                      className="mr-3"
                    />
                    <div className="flex-1">
                      <div className="font-medium text-gray-900">{phase.name}</div>
                      <div className="text-xs text-gray-400 mt-1">Code: {phase.code}</div>
                    </div>
                  </label>
                ))
              )}
            </div>
          )}

          {activeTab === 'disciplines' && (
            <div className="space-y-2">
              {filteredDisciplines.length === 0 ? (
                <p className="text-gray-500 text-center py-4">No disciplines found</p>
              ) : (
                filteredDisciplines.map((discipline) => (
                  <label
                    key={discipline.id}
                    className="flex items-center p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50"
                  >
                    <input
                      type="checkbox"
                      checked={selectedDisciplines.includes(discipline.code)}
                      onChange={() => handleDisciplineToggle(discipline.code)}
                      className="mr-3"
                    />
                    <div className="flex-1 flex items-center gap-2">
                      {discipline.color_hex && (
                        <span
                          className="w-4 h-4 rounded"
                          style={{ backgroundColor: discipline.color_hex }}
                        />
                      )}
                      <div>
                        <div className="font-medium text-gray-900">{discipline.name}</div>
                        <div className="text-xs text-gray-400 mt-1">Code: {discipline.code}</div>
                      </div>
                    </div>
                  </label>
                ))
              )}
            </div>
          )}

          {activeTab === 'tasks' && (
            <div className="space-y-2">
              <p className="text-sm text-gray-500 mb-2">
                Task selection will be available after phase/discipline selection
              </p>
            </div>
          )}
        </div>

        {/* Options */}
        <div className="mt-6 pt-6 border-t border-gray-200">
          <h3 className="text-sm font-medium text-gray-900 mb-3">Options</h3>
          <div className="space-y-2">
            <label className="flex items-center">
              <input
                type="checkbox"
                checked={options.mapPhaseToKanban}
                onChange={(e) =>
                  onOptionsChange({ ...options, mapPhaseToKanban: e.target.checked })
                }
                className="mr-2"
              />
              <span className="text-sm text-gray-700">Map Phase → Kanban columns</span>
            </label>
            <label className="flex items-center">
              <input
                type="checkbox"
                checked={options.autoAssignByRole}
                onChange={(e) =>
                  onOptionsChange({ ...options, autoAssignByRole: e.target.checked })
                }
                className="mr-2"
              />
              <span className="text-sm text-gray-700">Auto-assign by Role</span>
            </label>
            <label className="flex items-center">
              <input
                type="checkbox"
                checked={options.createDeliverableFolders}
                onChange={(e) =>
                  onOptionsChange({ ...options, createDeliverableFolders: e.target.checked })
                }
                className="mr-2"
              />
              <span className="text-sm text-gray-700">Create deliverable folders</span>
            </label>
          </div>
        </div>
      </CardContent>
    </Card>
  );
};

