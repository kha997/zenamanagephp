@if (auth()->user()?->hasPermission('template.apply'))
    <div x-data="workTemplateApply('{{ $project->id }}')" x-init="init()">
        <x-ui.card title="Áp dụng mẫu công việc">
            <template x-if="loadingList">
                <p class="text-sm text-slate-500">Đang tải danh sách mẫu...</p>
            </template>

            <template x-if="!loadingList && templates.length === 0">
                <p class="text-sm text-slate-500">Chưa có mẫu công việc nào được publish. Liên hệ quản trị viên để tạo mẫu.</p>
            </template>

            <template x-if="!loadingList && templates.length > 0">
                <div class="space-y-3">
                    <select x-model="selectedTemplateId" class="operator-select" @change="preview = null; error = ''">
                        <option value="">-- Chọn mẫu công việc --</option>
                        <template x-for="tpl in templates" :key="tpl.id">
                            <option :value="tpl.id" x-text="tpl.name"></option>
                        </template>
                    </select>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="operator-button operator-button-secondary"
                            :disabled="!selectedTemplateId || loadingPreview"
                            @click="fetchPreview()"
                        >Xem trước</button>
                        <button
                            type="button"
                            class="operator-button operator-button-primary"
                            x-show="preview && !preview.duplicate"
                            :disabled="applying"
                            @click="applyTemplate()"
                        >Áp dụng</button>
                    </div>

                    <template x-if="error">
                        <p class="text-sm text-rose-600" x-text="error"></p>
                    </template>

                    <template x-if="preview">
                        <div class="rounded border border-slate-200 p-3 text-sm">
                            <template x-if="preview.duplicate">
                                <p class="text-amber-600">Mẫu này đã được áp dụng cho dự án trước đó.</p>
                            </template>
                            <template x-if="!preview.duplicate">
                                <ul class="space-y-1 text-slate-700">
                                    <li>Giai đoạn: <span x-text="preview.summary.phases"></span></li>
                                    <li>Công việc: <span x-text="preview.summary.tasks"></span></li>
                                    <li>Checklist: <span x-text="preview.summary.checklists"></span></li>
                                    <li>Tài liệu: <span x-text="preview.summary.docs"></span></li>
                                </ul>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </x-ui.card>
    </div>

    <script>
        function workTemplateApply(projectId) {
            return {
                projectId: projectId,
                templates: [],
                selectedTemplateId: '',
                preview: null,
                loadingList: true,
                loadingPreview: false,
                applying: false,
                error: '',
                csrfToken() {
                    return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                },
                async init() {
                    try {
                        const response = await fetch(`/app/projects/${this.projectId}/work-templates`);
                        const result = await response.json();
                        this.templates = result.data ?? [];
                    } catch (e) {
                        this.error = 'Không tải được danh sách mẫu.';
                    } finally {
                        this.loadingList = false;
                    }
                },
                async fetchPreview() {
                    this.loadingPreview = true;
                    this.error = '';
                    this.preview = null;
                    try {
                        const response = await fetch(`/app/projects/${this.projectId}/work-templates/preview`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                            },
                            body: JSON.stringify({ work_template_id: this.selectedTemplateId }),
                        });
                        const result = await response.json();
                        if (result.success) {
                            this.preview = result.data;
                        } else {
                            this.error = result.message || 'Không xem trước được.';
                        }
                    } catch (e) {
                        this.error = 'Có lỗi xảy ra, thử lại.';
                    } finally {
                        this.loadingPreview = false;
                    }
                },
                async applyTemplate() {
                    this.applying = true;
                    this.error = '';
                    try {
                        const response = await fetch(`/app/projects/${this.projectId}/work-templates/apply`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                            },
                            body: JSON.stringify({ work_template_id: this.selectedTemplateId }),
                        });
                        const result = await response.json();
                        if (result.success && !result.data.duplicate) {
                            window.location.reload();
                        } else if (result.success && result.data.duplicate) {
                            this.preview = result.data;
                        } else {
                            this.error = result.message || 'Áp dụng thất bại.';
                        }
                    } catch (e) {
                        this.error = 'Có lỗi xảy ra, thử lại.';
                    } finally {
                        this.applying = false;
                    }
                },
            };
        }
    </script>
@endif
