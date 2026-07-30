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

    function openStageDialog(card, targetGroupKey) {
        var dialog = document.querySelector('[data-crm-stage-dialog]');
        var nameEl = dialog.querySelector('[data-dialog-opportunity-name]');
        var groupPicker = dialog.querySelector('[data-dialog-group-picker]');
        var choicePicker = dialog.querySelector('[data-dialog-choice-picker]');
        var reasonEl = dialog.querySelector('[data-dialog-reason]');
        var confirmBtn = dialog.querySelector('[data-dialog-confirm]');

        var opportunityNameEl = card.querySelector('.operator-link');
        nameEl.textContent = opportunityNameEl ? opportunityNameEl.textContent.trim() : '';

        choicePicker.classList.add('hidden');
        choicePicker.innerHTML = '';
        reasonEl.classList.add('hidden');
        reasonEl.value = '';
        confirmBtn.disabled = true;

        if (targetGroupKey) {
            // Preselect: bỏ qua bước chọn cột, vào thẳng choice_options (dùng cho kéo-thả
            // vào lost_nurture VÀ cho click khi user vừa chọn group requires_choice trong
            // group-picker đang mở).
            groupPicker.classList.add('hidden');
            activeDialogContext = { card: card, targetGroupKey: targetGroupKey };
            renderChoiceOptions(targetGroupKey, choicePicker, reasonEl, confirmBtn);
            if (!dialog.open) dialog.showModal();
            return;
        }

        var currentGroupKey = card.closest('[data-board-group]').dataset.boardGroup;
        groupPicker.classList.remove('hidden');
        groupPicker.querySelectorAll('.crm-dialog-group-option').forEach(function (btn) {
            btn.classList.toggle('hidden', btn.dataset.group === currentGroupKey);
        });

        activeDialogContext = { card: card };
        dialog.showModal();
    }

    function renderChoiceOptions(groupKey, choicePicker, reasonEl, confirmBtn) {
        var groupEl = document.querySelector('[data-board-group="' + groupKey + '"]');
        var options = JSON.parse(groupEl.dataset.choiceOptions || '[]');

        choicePicker.innerHTML = '';
        choicePicker.classList.remove('hidden');

        options.forEach(function (option) {
            var label = document.createElement('label');
            var radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'stage_choice';
            radio.value = option.stage;
            radio.dataset.requiresReason = option.requires_reason ? '1' : '0';
            label.appendChild(radio);
            label.appendChild(document.createTextNode(' ' + option.label));
            choicePicker.appendChild(label);

            radio.addEventListener('change', function () {
                var requiresReason = radio.dataset.requiresReason === '1';
                reasonEl.classList.toggle('hidden', !requiresReason);
                confirmBtn.disabled = requiresReason && reasonEl.value.trim() === '';
            });
        });

        reasonEl.oninput = function () {
            var checked = choicePicker.querySelector('input[name="stage_choice"]:checked');
            var requiresReason = checked && checked.dataset.requiresReason === '1';
            confirmBtn.disabled = requiresReason && reasonEl.value.trim() === '';
        };
    }

    function setCardPending(card, pending) {
        card.setAttribute('aria-busy', pending ? 'true' : 'false');
        var handle = card.querySelector('.crm-drag-handle');
        if (handle) {
            handle.draggable = !pending;
            handle.disabled = pending;
        }
        var transitionBtn = card.querySelector('.crm-stage-transition-btn');
        if (transitionBtn) {
            transitionBtn.disabled = pending;
        }
    }

    function submitStageTransition(card, targetGroupKey, toStage, reason) {
        var dialog = document.querySelector('[data-crm-stage-dialog]');
        if (dialog.open) dialog.close();
        activeDialogContext = null;
        setCardPending(card, true);
        // Task 9 (slice 3) thay toàn bộ phần dưới đây: gọi postStageUpdate(), xử lý
        // thành công (Task 10 gắn commitCardTransition) / lỗi qua parseErrorResponse()
        // + showToast(). Ở slice này CHƯA gọi mạng — card sẽ pending vĩnh viễn, đúng ý.
    }

    function requestStageTransition(card, targetGroupKey) {
        var targetGroupEl = document.querySelector('[data-board-group="' + targetGroupKey + '"]');
        var requiresChoice = targetGroupEl.dataset.requiresChoice === '1';

        if (requiresChoice) {
            openStageDialog(card, targetGroupKey);
            return;
        }

        var toStage = targetGroupEl.dataset.defaultEntryStage;
        submitStageTransition(card, targetGroupKey, toStage, null);
    }

    function initStageDialog() {
        var dialog = document.querySelector('[data-crm-stage-dialog]');
        if (!dialog) return;

        var groupPicker = dialog.querySelector('[data-dialog-group-picker]');
        var choicePicker = dialog.querySelector('[data-dialog-choice-picker]');
        var reasonEl = dialog.querySelector('[data-dialog-reason]');
        var confirmBtn = dialog.querySelector('[data-dialog-confirm]');
        var cancelBtn = dialog.querySelector('[data-dialog-cancel]');

        groupPicker.querySelectorAll('.crm-dialog-group-option').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var card = activeDialogContext.card;
                requestStageTransition(card, btn.dataset.group);
            });
        });

        confirmBtn.addEventListener('click', function () {
            if (confirmBtn.disabled || !activeDialogContext) return;

            var card = activeDialogContext.card;
            var targetGroupKey = activeDialogContext.targetGroupKey;
            var checked = choicePicker.querySelector('input[name="stage_choice"]:checked');
            if (!checked) return;

            var toStage = checked.value;
            var reason = checked.dataset.requiresReason === '1' ? reasonEl.value.trim() : null;

            submitStageTransition(card, targetGroupKey, toStage, reason);
        });

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
