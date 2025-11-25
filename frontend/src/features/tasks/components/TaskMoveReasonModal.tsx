import React, { useState } from 'react';
import { Button } from '../../../components/ui/primitives/Button';
import { Input } from '../../../components/ui/primitives/Input';

export interface TaskMoveReasonModalProps {
  isOpen: boolean;
  taskTitle: string;
  targetStatus: string;
  onConfirm: (reason: string) => void;
  onCancel: () => void;
}

export const TaskMoveReasonModal: React.FC<TaskMoveReasonModalProps> = ({
  isOpen,
  taskTitle,
  targetStatus,
  onConfirm,
  onCancel,
}) => {
  const [reason, setReason] = useState('');
  const [error, setError] = useState('');

  if (!isOpen) return null;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    const trimmedReason = reason.trim();
    if (!trimmedReason) {
      setError('Reason is required');
      return;
    }

    if (trimmedReason.length > 500) {
      setError('Reason cannot exceed 500 characters');
      return;
    }

    onConfirm(trimmedReason);
    setReason('');
    setError('');
  };

  const handleCancel = () => {
    setReason('');
    setError('');
    onCancel();
  };

  const statusLabel = targetStatus === 'blocked' ? 'Blocked' : 'Canceled';

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-[var(--surface)] rounded-lg p-6 max-w-md w-full mx-4 shadow-xl">
        <h2 className="text-lg font-semibold text-[var(--text)] mb-2">
          Reason Required
        </h2>
        <p className="text-sm text-[var(--muted)] mb-4">
          Please provide a reason for moving "{taskTitle}" to <strong>{statusLabel}</strong> status.
        </p>
        
        <form onSubmit={handleSubmit}>
          <div className="mb-4">
            <label htmlFor="reason" className="block text-sm font-medium text-[var(--text)] mb-2">
              Reason <span className="text-red-500">*</span>
            </label>
            <textarea
              id="reason"
              value={reason}
              onChange={(e) => {
                setReason(e.target.value);
                setError('');
              }}
              placeholder={`Enter reason for moving task to ${statusLabel.toLowerCase()}...`}
              className="w-full px-3 py-2 border border-[var(--border)] rounded-lg bg-[var(--surface)] text-[var(--text)] resize-none focus:outline-none focus:ring-2 focus:ring-[var(--accent)]"
              rows={4}
              maxLength={500}
              autoFocus
            />
            <div className="flex justify-between items-center mt-1">
              {error && (
                <span className="text-xs text-red-500">{error}</span>
              )}
              <span className="text-xs text-[var(--muted)] ml-auto">
                {reason.length}/500
              </span>
            </div>
          </div>

          <div className="flex justify-end gap-3">
            <Button
              type="button"
              variant="secondary"
              onClick={handleCancel}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              variant="primary"
              disabled={!reason.trim()}
            >
              Confirm
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
};

