import React, { useState } from 'react';
import { ChangeRequest, ChangeRequestDecision } from '@/lib/types';
import { 
  Dialog, 
  DialogContent, 
  DialogHeader, 
  DialogTitle,
  DialogFooter
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/Button';
import { Textarea } from '@/components/ui/Textarea';
import { Label } from '@/components/ui/label';
import { CheckCircleIcon, XCircleIcon } from '@/lib/heroicons';

interface DecisionModalProps {
  isOpen: boolean;
  onClose: () => void;
  changeRequest: ChangeRequest;
  onDecision: (decision: ChangeRequestDecision) => Promise<void>;
}

export const DecisionModal: React.FC<DecisionModalProps> = ({
  isOpen,
  onClose,
  changeRequest,
  onDecision
}) => {
  const [decision, setDecision] = useState<'approve' | 'reject' | null>(null);
  const [note, setNote] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async () => {
    if (!decision) return;

    setIsSubmitting(true);
    try {
      await onDecision({
        decision,
        decision_note: note.trim() || undefined
      });
      onClose();
      setDecision(null);
      setNote('');
    } catch (error) {
      console.error('Error submitting decision:', error);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleClose = () => {
    if (!isSubmitting) {
      onClose();
      setDecision(null);
      setNote('');
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Quyết định Change Request</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div>
            <h4 className="font-medium text-gray-900">{changeRequest.code} - {changeRequest.title}</h4>
            <p className="text-sm text-gray-600 mt-1">{changeRequest.description}</p>
          </div>

          <div className="space-y-3">
            <Label>Quyết định</Label>
            <div className="flex space-x-4">
              <button
                type="button"
                onClick={() => setDecision('approve')}
                className={`flex items-center px-4 py-2 rounded-md border transition-colors ${
                  decision === 'approve'
                    ? 'bg-green-50 border-green-200 text-green-700'
                    : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'
                }`}
              >
                <CheckCircleIcon className="h-5 w-5 mr-2" />
                Duyệt
              </button>
              <button
                type="button"
                onClick={() => setDecision('reject')}
                className={`flex items-center px-4 py-2 rounded-md border transition-colors ${
                  decision === 'reject'
                    ? 'bg-red-50 border-red-200 text-red-700'
                    : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'
                }`}
              >
                <XCircleIcon className="h-5 w-5 mr-2" />
                Từ chối
              </button>
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="decision-note">Ghi chú (tùy chọn)</Label>
            <Textarea
              id="decision-note"
              value={note}
              onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setNote(e.target.value)}
              placeholder="Nhập ghi chú về quyết định..."
              rows={3}
            />
          </div>
        </div>

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            onClick={handleClose}
            disabled={isSubmitting}
          >
            Hủy
          </Button>
          <Button
            type="button"
            onClick={handleSubmit}
            disabled={!decision || isSubmitting}
            className={decision === 'approve' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'}
          >
            {isSubmitting ? 'Đang xử lý...' : 'Xác nhận'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
};
