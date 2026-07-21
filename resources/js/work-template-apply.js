/**
 * Card "Áp dụng mẫu công việc" trên trang chi tiết dự án: tải danh sách mẫu
 * đã publish, xem trước (dry-run) và áp dụng thật. Vanilla JS — layout
 * operator không có Alpine. Copy pattern từ ai-opportunity-summary.js.
 */
(function () {
    'use strict';

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
        }).then(function (response) {
            return response.json().then(function (json) {
                return { ok: response.ok, body: json };
            });
        });
    }

    function renderSummary(resultEl, data) {
        resultEl.textContent = '';
        if (data.duplicate) {
            var warn = document.createElement('p');
            warn.className = 'text-amber-600';
            warn.textContent = 'Mẫu này đã được áp dụng cho dự án trước đó.';
            resultEl.appendChild(warn);
        } else {
            var list = document.createElement('ul');
            list.className = 'space-y-1 text-slate-700';
            [
                ['Giai đoạn', data.summary.phases],
                ['Công việc', data.summary.tasks],
                ['Checklist', data.summary.checklists],
                ['Tài liệu', data.summary.docs],
            ].forEach(function (row) {
                var item = document.createElement('li');
                item.textContent = row[0] + ': ' + row[1];
                list.appendChild(item);
            });
            resultEl.appendChild(list);
        }
        resultEl.classList.remove('hidden');
    }

    function attach(container) {
        if (container.dataset.wtaBound) return;
        container.dataset.wtaBound = '1';

        var projectId = container.dataset.projectId;
        var loadingEl = container.querySelector('[data-wta-loading]');
        var emptyEl = container.querySelector('[data-wta-empty]');
        var bodyEl = container.querySelector('[data-wta-body]');
        var selectEl = container.querySelector('[data-wta-select]');
        var previewBtn = container.querySelector('[data-wta-preview-btn]');
        var applyBtn = container.querySelector('[data-wta-apply-btn]');
        var errorEl = container.querySelector('[data-wta-error]');
        var resultEl = container.querySelector('[data-wta-result]');

        if (!projectId || !selectEl || !previewBtn || !applyBtn) return;

        var base = '/app/projects/' + encodeURIComponent(projectId) + '/work-templates';

        function setError(message) {
            errorEl.textContent = message || '';
            errorEl.classList.toggle('hidden', !message);
        }

        function resetPreviewState() {
            setError('');
            resultEl.classList.add('hidden');
            resultEl.textContent = '';
            applyBtn.classList.add('hidden');
        }

        fetch(base, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.json(); })
            .then(function (json) {
                var templates = (json && json.data) || [];
                loadingEl.classList.add('hidden');
                if (templates.length === 0) {
                    emptyEl.classList.remove('hidden');
                    return;
                }
                templates.forEach(function (tpl) {
                    var option = document.createElement('option');
                    option.value = tpl.id;
                    option.textContent = tpl.name;
                    selectEl.appendChild(option);
                });
                bodyEl.classList.remove('hidden');
            })
            .catch(function () {
                loadingEl.classList.add('hidden');
                emptyEl.textContent = 'Không tải được danh sách mẫu.';
                emptyEl.classList.remove('hidden');
            });

        selectEl.addEventListener('change', function () {
            previewBtn.disabled = !selectEl.value;
            resetPreviewState();
        });

        previewBtn.addEventListener('click', function () {
            if (!selectEl.value) return;
            previewBtn.disabled = true;
            resetPreviewState();

            postJson(base + '/preview', { work_template_id: selectEl.value })
                .then(function (result) {
                    if (!result.ok || !result.body.success) {
                        setError(result.body.message || 'Không xem trước được.');
                        return;
                    }
                    renderSummary(resultEl, result.body.data);
                    if (!result.body.data.duplicate) {
                        applyBtn.classList.remove('hidden');
                    }
                })
                .catch(function () { setError('Có lỗi xảy ra, thử lại.'); })
                .finally(function () { previewBtn.disabled = false; });
        });

        applyBtn.addEventListener('click', function () {
            applyBtn.disabled = true;
            setError('');

            postJson(base + '/apply', { work_template_id: selectEl.value })
                .then(function (result) {
                    if (result.ok && result.body.success && !result.body.data.duplicate) {
                        window.location.reload();
                    } else if (result.ok && result.body.success && result.body.data.duplicate) {
                        renderSummary(resultEl, result.body.data);
                        applyBtn.classList.add('hidden');
                    } else {
                        setError(result.body.message || 'Áp dụng thất bại.');
                    }
                })
                .catch(function () { setError('Có lỗi xảy ra, thử lại.'); })
                .finally(function () { applyBtn.disabled = false; });
        });
    }

    function init() {
        document.querySelectorAll('[data-work-template-apply]').forEach(attach);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
