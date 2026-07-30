/**
 * Kéo-thả (và click "Chuyển giai đoạn" làm fallback) để đổi pipeline_stage
 * trên board CRM. Vanilla JS — layout operator không có Alpine. Copy pattern
 * từ work-template-apply.js.
 *
 * File này được xây dần qua nhiều task (xem
 * docs/superpowers/plans/2026-07-30-pipeline-drag-drop-implementation-plan.md).
 * Hiện tại đây chỉ là khung rỗng để trang crm.index tải được — chưa có hành
 * vi nào. Task tiếp theo (slice 1: module init + click mở dialog) thêm hành
 * vi đầu tiên.
 */
(function () {
    'use strict';

    var activeDialogContext = null; // { card } — Task 8 mở rộng thêm targetGroupKey

    function openStageDialog(card) {
        var dialog = document.querySelector('[data-crm-stage-dialog]');
        var nameEl = dialog.querySelector('[data-dialog-opportunity-name]');
        var groupPicker = dialog.querySelector('[data-dialog-group-picker]');

        var opportunityNameEl = card.querySelector('.operator-link');
        nameEl.textContent = opportunityNameEl ? opportunityNameEl.textContent.trim() : '';

        var currentGroupKey = card.closest('[data-board-group]').dataset.boardGroup;
        groupPicker.classList.remove('hidden');
        groupPicker.querySelectorAll('.crm-dialog-group-option').forEach(function (btn) {
            btn.classList.toggle('hidden', btn.dataset.group === currentGroupKey);
        });

        activeDialogContext = { card: card };
        dialog.showModal();
    }

    function initStageDialog() {
        var dialog = document.querySelector('[data-crm-stage-dialog]');
        if (!dialog) return;

        var cancelBtn = dialog.querySelector('[data-dialog-cancel]');
        cancelBtn.addEventListener('click', function () {
            activeDialogContext = null;
            dialog.close();
        });
    }

    function initClickFallback() {
        document.querySelectorAll('.crm-stage-transition-btn').forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                var card = event.currentTarget.closest('[data-opportunity-id]');
                if (card.getAttribute('aria-busy') === 'true') return;
                openStageDialog(card);
            });
        });
    }

    function initializePipelineDragDrop() {
        if (!document.querySelector('[data-board-group]')) return; // không phải trang crm.index
        initStageDialog();
        initClickFallback();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePipelineDragDrop);
    } else {
        initializePipelineDragDrop();
    }
})();
