/**
 * Nút "Tạo tóm tắt" trên trang cơ hội: gọi endpoint AI summary và hiển thị
 * kết quả tại chỗ (không lưu). Copy pattern từ ai-lead-suggest.js.
 */
(function () {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function attach(container) {
        if (container.dataset.aiSummaryBound) return;
        container.dataset.aiSummaryBound = '1';

        var trigger = container.querySelector('[data-ai-summary-trigger]');
        var statusEl = container.querySelector('[data-ai-summary-status]');
        var resultEl = container.querySelector('[data-ai-summary-result]');
        var captionEl = container.querySelector('[data-ai-summary-caption]');
        var opportunityId = container.dataset.opportunityId;

        if (!trigger || !opportunityId) return;

        trigger.addEventListener('click', function () {
            trigger.disabled = true;
            if (statusEl) statusEl.textContent = 'Đang tạo tóm tắt...';

            fetch('/operator/crm/opportunities/' + encodeURIComponent(opportunityId) + '/ai-summary', {
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
                        if (statusEl) statusEl.textContent = 'AI chưa bật hoặc đang lỗi — thử lại sau.';
                        return;
                    }

                    if (statusEl) statusEl.textContent = '';
                    if (resultEl) {
                        resultEl.textContent = result.body.data.summary;
                        resultEl.classList.remove('hidden');
                    }
                    if (captionEl) {
                        var at = new Date(result.body.data.generated_at);
                        captionEl.textContent = 'Tạo lúc ' + at.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
                            + ' — AI chỉ tóm tắt từ dữ liệu CRM, hãy kiểm chứng trước khi trao đổi với khách.';
                        captionEl.classList.remove('hidden');
                    }
                    trigger.textContent = 'Tạo lại';
                })
                .catch(function () {
                    if (statusEl) statusEl.textContent = 'AI chưa bật hoặc đang lỗi — thử lại sau.';
                })
                .finally(function () {
                    trigger.disabled = false;
                });
        });
    }

    function init() {
        document.querySelectorAll('[data-ai-summary]').forEach(attach);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
