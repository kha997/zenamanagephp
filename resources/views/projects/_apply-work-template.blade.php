@if (auth()->user()?->hasPermission('template.apply'))
    <div data-work-template-apply data-project-id="{{ $project->id }}">
        <x-ui.card title="Áp dụng mẫu công việc">
            <p data-wta-loading class="text-sm text-slate-500">Đang tải danh sách mẫu...</p>

            <p data-wta-empty class="hidden text-sm text-slate-500">Chưa có mẫu công việc nào được publish. Liên hệ quản trị viên để tạo mẫu.</p>

            <div data-wta-body class="hidden space-y-3">
                <select data-wta-select class="operator-select">
                    <option value="">-- Chọn mẫu công việc --</option>
                </select>

                <div class="flex gap-2">
                    <button type="button" data-wta-preview-btn class="operator-button operator-button-secondary" disabled>Xem trước</button>
                    <button type="button" data-wta-apply-btn class="operator-button operator-button-primary hidden">Áp dụng</button>
                </div>

                <p data-wta-error class="hidden text-sm text-rose-600"></p>

                <div data-wta-result class="hidden rounded border border-slate-200 p-3 text-sm"></div>
            </div>
        </x-ui.card>
    </div>
@endif
