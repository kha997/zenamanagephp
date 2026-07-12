/**
 * Nút "Gợi ý AI" trên form chuyển lead → cơ hội: gọi endpoint gợi ý AI
 * và điền sẵn service_category/service_scope_summary — người dùng vẫn
 * có thể sửa trước khi submit.
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
        var categoryField = form.querySelector('[data-ai-field="service_category"]');
        var summaryField = form.querySelector('[data-ai-field="scope_summary"]');
        var leadId = form.dataset.leadId;

        if (!trigger || !leadId) return;

        trigger.addEventListener('click', function () {
            trigger.disabled = true;
            if (statusEl) statusEl.textContent = 'Đang tạo gợi ý...';

            fetch('/operator/crm/leads/' + encodeURIComponent(leadId) + '/suggest-conversion', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
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

                    if (categoryField) categoryField.value = result.body.data.service_category;
                    if (summaryField) summaryField.value = result.body.data.scope_summary;
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
        document.querySelectorAll('[data-ai-lead-suggest-form]').forEach(attach);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
