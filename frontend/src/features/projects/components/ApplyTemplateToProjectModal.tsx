import React, { useState, useEffect } from 'react';
import { Modal } from '../../../shared/ui/modal';
import { Button } from '../../../components/ui/primitives/Button';
import { Select, type SelectOption } from '../../../components/ui/primitives/Select';
import { useTemplateSets, useTemplateSetDetail, useApplyTemplateToProject } from '../hooks';
import { generateIdempotencyKey } from '../../../shared/utils/idempotency';
import { useToast } from '../../../shared/ui/toast';

interface ApplyTemplateToProjectModalProps {
  projectId: string | number;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onApplied?: () => void;
}

/**
 * ApplyTemplateToProjectModal
 * 
 * Round 99: Apply Template Set to Project
 * 
 * Modal for applying a task template set to a project with optional preset selection.
 */
export const ApplyTemplateToProjectModal: React.FC<ApplyTemplateToProjectModalProps> = ({
  projectId,
  open,
  onOpenChange,
  onApplied,
}) => {
  const { addToast } = useToast();
  const [selectedTemplateSetId, setSelectedTemplateSetId] = useState<string>('');
  const [selectedPresetId, setSelectedPresetId] = useState<string>('');
  const [includeDependencies, setIncludeDependencies] = useState<boolean>(true);

  // Fetch template sets (only when modal is open)
  const { data: templateSetsData, isLoading: isLoadingSets } = useTemplateSets({
    enabled: open,
    filters: { is_active: true },
  });

  // Fetch template set detail with presets when a set is selected
  const { data: templateSetDetailData, isLoading: isLoadingDetail } = useTemplateSetDetail(
    selectedTemplateSetId || null,
    { enabled: open && !!selectedTemplateSetId }
  );

  const applyMutation = useApplyTemplateToProject();

  // Reset form when modal closes
  useEffect(() => {
    if (!open) {
      setSelectedTemplateSetId('');
      setSelectedPresetId('');
      setIncludeDependencies(true);
    }
  }, [open]);

  // Reset preset when template set changes
  useEffect(() => {
    setSelectedPresetId('');
  }, [selectedTemplateSetId]);

  const templateSets = templateSetsData?.data || [];
  const presets = templateSetDetailData?.data?.presets || [];

  // Build options for template sets
  const templateSetOptions: SelectOption[] = templateSets.map((set) => ({
    value: set.id,
    label: set.name || set.code || set.id,
  }));

  // Build options for presets
  const presetOptions: SelectOption[] = presets.map((preset) => ({
    value: preset.id,
    label: preset.name || preset.code || preset.id,
  }));

  const handleApply = async () => {
    if (!selectedTemplateSetId) {
      addToast({
        type: 'error',
        title: 'Lỗi',
        message: 'Vui lòng chọn mẫu công việc',
      });
      return;
    }

    try {
      const idempotencyKey = generateIdempotencyKey('project', 'apply_template', projectId);
      
      const payload = {
        template_set_id: selectedTemplateSetId,
        preset_id: selectedPresetId || null,
        options: {
          include_dependencies: includeDependencies,
        },
      };

      const result = await applyMutation.mutateAsync({
        projectId,
        payload,
        idempotencyKey,
      });

      const { created_tasks, created_dependencies } = result.data;

      // Show success toast
      addToast({
        type: 'success',
        title: 'Áp dụng mẫu thành công',
        message: `Đã tạo ${created_tasks} công việc${created_dependencies > 0 ? `, ${created_dependencies} phụ thuộc` : ''}`,
      });

      // Close modal and call callback
      onOpenChange(false);
      onApplied?.();
    } catch (error: any) {
      // Error is handled by the mutation, but we can show a toast here too
      const errorMessage = error?.message || 'Không thể áp dụng mẫu. Vui lòng thử lại.';
      addToast({
        type: 'error',
        title: 'Lỗi',
        message: errorMessage,
      });
    }
  };

  const handleRetry = () => {
    // Clear error state and retry with new idempotency key
    handleApply();
  };

  const canApply = selectedTemplateSetId && !applyMutation.isPending;
  const hasError = applyMutation.isError;

  return (
    <Modal
      open={open}
      onOpenChange={onOpenChange}
      title="Áp dụng mẫu công việc"
      description="Chọn mẫu công việc và preset (nếu có) để áp dụng vào dự án này"
      primaryAction={{
        label: 'Áp dụng',
        onClick: handleApply,
        loading: applyMutation.isPending,
        variant: 'primary',
      }}
      secondaryAction={{
        label: 'Hủy',
        onClick: () => onOpenChange(false),
        variant: 'outline',
      }}
    >
      <div className="space-y-4" data-testid="apply-template-modal">
        {/* Template Set Selector */}
        <div>
          <label className="block text-sm font-medium text-[var(--text)] mb-2">
            Mẫu công việc <span className="text-[var(--color-semantic-danger-600)]">*</span>
          </label>
          {isLoadingSets ? (
            <div className="text-sm text-[var(--muted)]">Đang tải...</div>
          ) : templateSetOptions.length === 0 ? (
            <div className="text-sm text-[var(--muted)] py-2" data-testid="apply-template-no-templates">
              Không có mẫu công việc nào khả dụng
            </div>
          ) : (
            <Select
              options={templateSetOptions}
              value={selectedTemplateSetId}
              onChange={setSelectedTemplateSetId}
              placeholder="Chọn mẫu công việc"
              disabled={applyMutation.isPending}
              data-testid="apply-template-select-trigger"
            />
          )}
        </div>

        {/* Preset Selector (only shown if template set has presets) */}
        {selectedTemplateSetId && (
          <div>
            <label className="block text-sm font-medium text-[var(--text)] mb-2">
              Preset (tùy chọn)
            </label>
            {isLoadingDetail ? (
              <div className="text-sm text-[var(--muted)]">Đang tải...</div>
            ) : presetOptions.length === 0 ? (
              <div className="text-sm text-[var(--muted)] py-2">
                Mẫu này không có preset
              </div>
            ) : (
              <Select
                options={presetOptions}
                value={selectedPresetId}
                onChange={setSelectedPresetId}
                placeholder="Chọn preset (tùy chọn)"
                disabled={applyMutation.isPending}
              />
            )}
          </div>
        )}

        {/* Include Dependencies Toggle */}
        <div className="flex items-center gap-2">
          <input
            type="checkbox"
            id="include-dependencies"
            checked={includeDependencies}
            onChange={(e) => setIncludeDependencies(e.target.checked)}
            disabled={applyMutation.isPending}
            className="w-4 h-4 rounded border-[var(--border)] text-[var(--accent)] focus:ring-[var(--accent)]"
          />
          <label htmlFor="include-dependencies" className="text-sm text-[var(--text)] cursor-pointer">
            Tạo dependencies (phụ thuộc)
          </label>
        </div>

        {/* Error Display */}
        {hasError && (
          <div className="p-4 bg-[var(--color-semantic-danger-50)] border border-[var(--color-semantic-danger-200)] rounded-lg">
            <div className="flex items-start gap-3">
              <span className="text-lg">❌</span>
              <div className="flex-1">
                <h4 className="text-sm font-medium text-[var(--color-semantic-danger-800)] mb-1">
                  Không thể áp dụng mẫu
                </h4>
                <p className="text-sm text-[var(--color-semantic-danger-700)] mb-3">
                  {applyMutation.error?.message || 'Đã xảy ra lỗi. Vui lòng thử lại.'}
                </p>
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={handleRetry}
                  disabled={applyMutation.isPending}
                >
                  Thử lại
                </Button>
              </div>
            </div>
          </div>
        )}
      </div>
    </Modal>
  );
};

