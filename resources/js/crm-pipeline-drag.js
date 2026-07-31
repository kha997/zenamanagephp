/**
 * Kéo-thả (và click "Chuyển giai đoạn" làm fallback) để đổi pipeline_stage
 * trên board CRM. Vanilla JS — layout operator không có Alpine. Copy pattern
 * từ work-template-apply.js.
 *
 * requestStageTransition() là điểm quyết định DUY NHẤT cho cả click và
 * kéo-thả — không có luồng quyết định stage riêng cho từng lối vào.
 */
(function () {
    'use strict';

    var activeDialogContext = null; // { card, targetGroupKey } khi group đích cần chọn thêm (lost_nurture)

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

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: JSON.stringify(body),
        });
    }

    function postStageUpdate(opportunityId, toStage, reason) {
        var url = '/operator/crm/opportunities/' + encodeURIComponent(opportunityId) + '/stage';
        var body = { pipeline_stage: toStage };
        if (reason) body.lost_reason = reason;
        return postJson(url, body);
    }

    function parseErrorResponse(response) {
        if (!response) {
            return Promise.resolve({ userMessage: 'Có lỗi xảy ra, vui lòng thử lại.' });
        }
        if (response.status === 401) {
            return Promise.resolve({ userMessage: 'Phiên đăng nhập không còn hợp lệ, vui lòng đăng nhập lại.' });
        }
        if (response.status === 403) {
            return Promise.resolve({ userMessage: 'Bạn không có quyền thực hiện thao tác này.' });
        }
        if (response.status === 419) {
            return Promise.resolve({ userMessage: 'Phiên làm việc đã hết hạn, vui lòng tải lại trang.', reload: true });
        }
        return response.json()
            .then(function (body) {
                if (response.status === 422) {
                    var firstFieldError = body && body.errors && Object.values(body.errors)[0];
                    var firstMessage = Array.isArray(firstFieldError) ? firstFieldError[0] : null;
                    return { userMessage: firstMessage || (body && body.message) || 'Dữ liệu không hợp lệ.' };
                }
                return { userMessage: (body && body.message) || 'Có lỗi xảy ra, vui lòng thử lại.' };
            })
            .catch(function () {
                return { userMessage: response.status >= 500
                    ? 'Có lỗi xảy ra, vui lòng thử lại.'
                    : 'Có lỗi xảy ra, vui lòng thử lại (mã lỗi ' + response.status + ').' };
            });
    }

    function showToast(message, options) {
        var el = document.createElement('div');
        el.className = 'fixed bottom-4 right-4 z-50 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 shadow-lg';
        el.textContent = message;
        if (options && options.reload) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = 'Tải lại trang';
            btn.className = 'ml-3 underline';
            btn.addEventListener('click', function () { window.location.reload(); });
            el.appendChild(btn);
        }
        document.body.appendChild(el);
        if (!(options && options.reload)) {
            setTimeout(function () { el.remove(); }, 5000);
        }
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

    function formatVnd(amount) {
        return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(amount);
    }

    function recalculateColumnSummary(columnEl) {
        var cards = columnEl.querySelectorAll('[data-opportunity-id]');
        var total = 0;
        cards.forEach(function (card) { total += parseInt(card.dataset.amount, 10) || 0; });
        var countEl = columnEl.querySelector('[data-column-count]');
        var totalEl = columnEl.querySelector('[data-column-total]');
        if (countEl) countEl.textContent = String(cards.length);
        if (totalEl) totalEl.textContent = formatVnd(total);
        var emptyEl = columnEl.querySelector('[data-column-empty]');
        if (emptyEl) emptyEl.classList.toggle('hidden', cards.length > 0);
    }

    function stageLabelFor(groupKey) {
        var groupEl = document.querySelector('[data-board-group="' + groupKey + '"]');
        return groupEl ? groupEl.dataset.columnLabel : groupKey;
    }

    function commitCardTransition(card, targetGroupKey, responseData) {
        var sourceGroupEl = card.closest('[data-board-group]');
        var targetGroupEl = document.querySelector('[data-board-group="' + targetGroupKey + '"]');
        var targetList = targetGroupEl.querySelector('ul');

        if (!targetList) {
            targetList = document.createElement('ul');
            targetList.className = 'space-y-2';
            targetGroupEl.appendChild(targetList);
        }

        // 1. vị trí card
        targetList.appendChild(card);

        // 2. data-current-stage
        card.dataset.currentStage = responseData.pipeline_stage;

        // 3. stage badge hiển thị (nếu card có phần tử badge riêng)
        var badgeEl = card.querySelector('[data-stage-badge]');
        if (badgeEl) badgeEl.textContent = stageLabelFor(targetGroupKey);

        // 4 + 5 + 6. data-terminal + xóa drag handle + nút "Chuyển giai đoạn" nếu terminal
        card.dataset.terminal = responseData.is_terminal ? '1' : '0';
        if (responseData.is_terminal) {
            var handle = card.querySelector('.crm-drag-handle');
            if (handle) handle.remove();
            var transitionBtn = card.querySelector('.crm-stage-transition-btn');
            if (transitionBtn) transitionBtn.remove();
        }

        // 7 + 8. count và tổng estimated_fee của cột nguồn và cột đích
        if (sourceGroupEl) recalculateColumnSummary(sourceGroupEl);
        recalculateColumnSummary(targetGroupEl);

        setCardPending(card, false);
    }

    function submitStageTransition(card, targetGroupKey, toStage, reason) {
        var dialog = document.querySelector('[data-crm-stage-dialog]');
        if (dialog.open) dialog.close();
        activeDialogContext = null;
        setCardPending(card, true);

        return postStageUpdate(card.dataset.opportunityId, toStage, reason)
            .then(function (response) {
                if (response.ok) {
                    return response.json().then(function (body) {
                        commitCardTransition(card, targetGroupKey, body.data);
                    });
                }
                return parseErrorResponse(response).then(function (error) {
                    setCardPending(card, false);
                    showToast(error.userMessage, error);
                });
            })
            .catch(function () {
                return parseErrorResponse(null).then(function (error) {
                    setCardPending(card, false);
                    showToast(error.userMessage, error);
                });
            });
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

    var draggedCard = null;

    function clearAllDragover() {
        document.querySelectorAll('[data-board-group][data-dragover]').forEach(function (el) {
            el.removeAttribute('data-dragover');
        });
    }

    function initDragDrop() {
        document.querySelectorAll('.crm-drag-handle').forEach(function (handle) {
            handle.addEventListener('dragstart', function (event) {
                var card = event.currentTarget.closest('[data-opportunity-id]');
                if (card.getAttribute('aria-busy') === 'true') {
                    event.preventDefault();
                    return;
                }
                draggedCard = card;
                if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
            });

            handle.addEventListener('dragend', function () {
                draggedCard = null;
                clearAllDragover();
            });
        });

        document.querySelectorAll('[data-board-group]').forEach(function (columnEl) {
            columnEl.addEventListener('dragenter', function (event) {
                event.preventDefault();
                if (draggedCard) {
                    var sourceGroupKey = draggedCard.closest('[data-board-group]').dataset.boardGroup;
                    if (columnEl.dataset.boardGroup === sourceGroupKey) return; // không highlight cột nguồn
                }
                columnEl.setAttribute('data-dragover', '1');
            });

            columnEl.addEventListener('dragover', function (event) {
                event.preventDefault(); // bắt buộc để drop bắn được
            });

            columnEl.addEventListener('dragleave', function (event) {
                // chỉ clear khi con trỏ THỰC SỰ rời cột (relatedTarget không còn nằm trong columnEl)
                if (event.relatedTarget && columnEl.contains(event.relatedTarget)) return;
                columnEl.removeAttribute('data-dragover');
            });

            columnEl.addEventListener('drop', function (event) {
                event.preventDefault();
                columnEl.removeAttribute('data-dragover');
                if (!draggedCard) return;

                var sourceGroupKey = draggedCard.closest('[data-board-group]').dataset.boardGroup;
                var targetGroupKey = columnEl.dataset.boardGroup;
                var card = draggedCard;
                draggedCard = null;

                if (sourceGroupKey === targetGroupKey) {
                    return; // no-op tuyệt đối — không gọi API, không đổi DOM
                }

                requestStageTransition(card, targetGroupKey);
            });
        });
    }

    function initializePipelineDragDrop() {
        if (!document.querySelector('[data-board-group]')) return; // không phải trang crm.index
        initStageDialog();
        initClickFallback();
        initDragDrop();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePipelineDragDrop);
    } else {
        initializePipelineDragDrop();
    }
})();
