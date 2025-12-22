import React from 'react';
import { Button } from '../../../components/ui/primitives/Button';
import { getErrorExplanation, type ErrorExplanation } from '../utils/errorExplanation';
import type { Task } from '../types';

export interface TaskMoveErrorModalProps {
  isOpen: boolean;
  error: {
    code: string;
    message: string;
    details?: Record<string, any>;
  };
  task: Task;
  targetStatus: string;
  onClose: () => void;
  onAction?: (action: string, data?: any) => void;
}

export const TaskMoveErrorModal: React.FC<TaskMoveErrorModalProps> = ({
  isOpen,
  error,
  task,
  targetStatus,
  onClose,
  onAction,
}) => {
  if (!isOpen) return null;
  
  const explanation = getErrorExplanation(error, task, targetStatus);
  
  return (
    <div 
      className="fixed bottom-4 right-4 z-50 max-w-md" 
      style={{ 
        animation: 'slideInRight 0.3s ease-out',
      }}
    >
      <div className="bg-[var(--surface)] border border-[var(--border)] rounded-lg shadow-xl p-4">
        <div className="flex items-start gap-3">
          <div className="flex-shrink-0 w-8 h-8 rounded-full bg-yellow-100 dark:bg-yellow-900 flex items-center justify-center">
            <span className="text-yellow-600 dark:text-yellow-300 text-lg">⚠️</span>
          </div>
          
          <div className="flex-1 min-w-0">
            <h3 className="font-semibold text-[var(--text)] mb-1 text-sm">
              {explanation.title}
            </h3>
            <p className="text-xs text-[var(--muted)] mb-3">
              {explanation.description}
            </p>
            
            {explanation.solutions && explanation.solutions.length > 0 && (
              <div className="mb-3">
                <p className="text-xs font-semibold text-[var(--text)] mb-1">
                  What you can do:
                </p>
                <ul className="text-xs text-[var(--muted)] list-disc list-inside space-y-0.5">
                  {explanation.solutions.map((solution, i) => (
                    <li key={i}>{solution}</li>
                  ))}
                </ul>
              </div>
            )}
            
            {explanation.relatedTasks && explanation.relatedTasks.length > 0 && (
              <div className="mb-3 p-2 bg-[var(--muted-surface)] rounded text-xs">
                <p className="font-semibold text-[var(--text)] mb-1">
                  Related tasks ({explanation.relatedTasks.length}):
                </p>
                <div className="text-[var(--muted)]">
                  {explanation.relatedTasks.slice(0, 3).map((taskId, i) => (
                    <span key={i}>
                      Task {taskId}
                      {i < Math.min(2, explanation.relatedTasks.length - 1) ? ', ' : ''}
                    </span>
                  ))}
                  {explanation.relatedTasks.length > 3 && '...'}
                </div>
              </div>
            )}
            
            <div className="flex gap-2 mt-3">
              <Button variant="secondary" size="sm" onClick={onClose}>
                Got it
              </Button>
              {explanation.actionButton && (
                <Button 
                  variant="primary" 
                  size="sm"
                  onClick={() => {
                    onAction?.(explanation.actionButton!.action, explanation.actionButton!.data);
                    onClose();
                  }}
                >
                  {explanation.actionButton.label}
                </Button>
              )}
            </div>
          </div>
          
          <button 
            onClick={onClose} 
            className="flex-shrink-0 text-[var(--muted)] hover:text-[var(--text)] transition-colors"
            aria-label="Close"
          >
            ×
          </button>
        </div>
      </div>
    </div>
  );
};

