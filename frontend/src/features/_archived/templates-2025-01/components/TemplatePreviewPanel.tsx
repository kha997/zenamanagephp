import React from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import type { TemplatePreviewResult } from '../api';

interface TemplatePreviewPanelProps {
  preview: TemplatePreviewResult | null;
  isLoading: boolean;
  onApply: () => void;
  isApplying: boolean;
}

export const TemplatePreviewPanel: React.FC<TemplatePreviewPanelProps> = ({
  preview,
  isLoading,
  onApply,
  isApplying,
}) => {
  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Preview</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-center py-8">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
            <p className="text-gray-500 mt-2">Generating preview...</p>
          </div>
        </CardContent>
      </Card>
    );
  }

  if (!preview) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Preview</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-gray-500 text-center py-8">
            Select a template and make your selections to see preview
          </p>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Preview</CardTitle>
      </CardHeader>
      <CardContent>
        {/* Statistics */}
        <div className="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
          <div className="bg-blue-50 rounded-lg p-4">
            <div className="text-sm text-blue-600 font-medium">Total Tasks</div>
            <div className="text-2xl font-bold text-blue-900 mt-1">{preview.total_tasks}</div>
          </div>
          <div className="bg-green-50 rounded-lg p-4">
            <div className="text-sm text-green-600 font-medium">Dependencies</div>
            <div className="text-2xl font-bold text-green-900 mt-1">
              {preview.total_dependencies}
            </div>
          </div>
          <div className="bg-purple-50 rounded-lg p-4">
            <div className="text-sm text-purple-600 font-medium">Est. Duration</div>
            <div className="text-2xl font-bold text-purple-900 mt-1">
              {preview.estimated_duration} days
            </div>
          </div>
        </div>

        {/* Breakdown by Phase */}
        {Object.keys(preview.breakdown.phase).length > 0 && (
          <div className="mb-4">
            <h3 className="text-sm font-medium text-gray-900 mb-2">By Phase</h3>
            <div className="space-y-1">
              {Object.entries(preview.breakdown.phase).map(([phase, count]) => (
                <div key={phase} className="flex justify-between text-sm">
                  <span className="text-gray-600">{phase}</span>
                  <span className="font-medium text-gray-900">{count} tasks</span>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Breakdown by Discipline */}
        {Object.keys(preview.breakdown.discipline).length > 0 && (
          <div className="mb-6">
            <h3 className="text-sm font-medium text-gray-900 mb-2">By Discipline</h3>
            <div className="space-y-1">
              {Object.entries(preview.breakdown.discipline).map(([discipline, count]) => (
                <div key={discipline} className="flex justify-between text-sm">
                  <span className="text-gray-600">{discipline}</span>
                  <span className="font-medium text-gray-900">{count} tasks</span>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Apply Button */}
        <Button
          variant="primary"
          onClick={onApply}
          disabled={isApplying || preview.total_tasks === 0}
          style={{ width: '100%' }}
        >
          {isApplying ? 'Applying Template...' : 'Apply Template'}
        </Button>
      </CardContent>
    </Card>
  );
};

