/**
 * Nút "Gợi ý AI" trên form tạo công việc thiết kế: gọi endpoint gợi ý AI
 * với project_id/item_type đang chọn (chưa lưu DB) và điền sẵn description —
 * người dùng vẫn có thể sửa trước khi submit.
 */
(function () {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function attach(form) {
        if (form.dataset.aiSuggestBound) return;
        form.dataset.aiSuggestBound = '1';

        var trigger = form.querySelector('[data-ai-suggest-trigger]');
        var statusEl = form.querySelector('[data-ai-suggest-status]');
        var descriptionField = form.querySelector('[data-ai-field="description"]');
        var projectField = form.querySelector('#project_id');
        var itemTypeField = form.querySelector('#item_type');

        if (!trigger) return;

        trigger.addEventListener('click', function () {
            var projectId = projectField ? projectField.value : '';
            var itemType = itemTypeField ? itemTypeField.value : '';

            if (!projectId) {
                if (statusEl) statusEl.textContent = 'Vui lòng chọn dự án trước.';
                return;
            }

            trigger.disabled = true;
            if (statusEl) statusEl.textContent = 'Đang tạo gợi ý...';

            fetch('/operator/design-items/suggest-description', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ project_id: projectId, item_type: itemType }),
            })
                .then(function (response) {
                    return response.json().then(function (body) {
                        return { ok: response.ok, body: body };
                    });
                })
                .then(function (result) {
                    if (!result.ok || !result.body.success) {
                        if (statusEl) statusEl.textContent = 'Không thể tạo gợi ý lúc này.';
                        return;
                    }

                    if (descriptionField) descriptionField.value = result.body.data.description;
                    if (statusEl) statusEl.textContent = 'Đã điền gợi ý — bạn có thể chỉnh sửa.';
                })
                .catch(function () {
                    if (statusEl) statusEl.textContent = 'Không thể tạo gợi ý lúc này.';
                })
                .finally(function () {
                    trigger.disabled = false;
                });
        });
    }

    function init() {
        document.querySelectorAll('[data-ai-design-item-suggest-form]').forEach(attach);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
