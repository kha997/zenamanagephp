@extends('layouts.operator')

@section('title', $template ? 'Chỉnh sửa biểu mẫu' : 'Tạo biểu mẫu mới')
@section('page_title', $template ? 'Chỉnh sửa: ' . $template->name : 'Tạo biểu mẫu mới')

@section('content')
    <x-ui.page-header
        title="{{ $template ? 'Chỉnh sửa biểu mẫu' : 'Tạo biểu mẫu mới' }}"
        description="Soạn HTML với placeholder dữ liệu cho hợp đồng, chứng chỉ hoặc dự án."
    >
        <a href="{{ route('operator.document-templates.index') }}" class="operator-button operator-button-secondary">Quay lại</a>
    </x-ui.page-header>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <form method="POST"
          action="{{ $template ? route('operator.document-templates.update', $template->id) : route('operator.document-templates.store') }}"
          id="template-form">
        @csrf

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Left: Form fields --}}
            <div class="lg:col-span-2 space-y-6">
                <x-ui.card>
                    <div class="space-y-4">
                        <div class="operator-field">
                            <label for="name" class="operator-label">Tên biểu mẫu <span class="text-red-500">*</span></label>
                            <input id="name" name="name" type="text" class="operator-input" value="{{ old('name', $template->name ?? '') }}" required placeholder="VD: Biểu mẫu hợp đồng thi công">
                            @error('name') <p class="operator-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="operator-field">
                            <label for="description" class="operator-label">Mô tả</label>
                            <textarea id="description" name="description" class="operator-input" rows="2" placeholder="Mô tả ngắn về biểu mẫu">{{ old('description', $template->description ?? '') }}</textarea>
                        </div>

                        @if (!$template)
                            <div class="operator-field">
                                <label for="context" class="operator-label">Ngữ cảnh dữ liệu <span class="text-red-500">*</span></label>
                                <select id="context" name="context" class="operator-select" required>
                                    <option value="">-- Chọn ngữ cảnh --</option>
                                    @foreach ($contexts as $ctx)
                                        <option value="{{ $ctx }}" {{ old('context') === $ctx ? 'selected' : '' }}>
                                            {{ $contextLabels[$ctx] ?? $ctx }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('context') <p class="operator-error">{{ $message }}</p> @enderror
                            </div>
                        @else
                            <div class="operator-field">
                                <label class="operator-label">Ngữ cảnh</label>
                                <span class="inline-block rounded bg-blue-100 px-2 py-1 text-sm font-medium text-blue-800">
                                    {{ $contextLabels[$template->context] ?? $template->context }}
                                </span>
                            </div>
                        @endif
                    </div>
                </x-ui.card>

                <x-ui.card>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-slate-900">HTML Template</h3>
                            <div class="flex gap-2">
                                <button type="button" onclick="previewTemplate()" class="operator-button operator-button-secondary text-sm">
                                    Xem thử
                                </button>
                                <button type="submit" class="operator-button operator-button-primary text-sm">
                                    Lưu bản nháp
                                </button>
                            </div>
                        </div>

                        <div class="operator-field">
                            <textarea
                                id="html_body"
                                name="html_body"
                                class="operator-input font-mono text-sm"
                                rows="20"
                                required
                                placeholder="Nhập HTML với placeholder: @{{contract_code}}, @{{project_name}}, ..."
                                style="white-space: pre; tab-size: 2;"
                            >{{ old('html_body', $sampleHtml) }}</textarea>
                            @error('html_body') <p class="operator-error">{{ $message }}</p> @enderror
                        </div>

                        @if ($template && $template->status === 'draft' && $template->latestPublishedVersion === null)
                            <button type="button" onclick="publishTemplate()" class="operator-button operator-button-primary w-full bg-green-600 hover:bg-green-700">
                                Xuất bản
                            </button>
                        @endif
                    </div>
                </x-ui.card>
            </div>

            {{-- Right: Placeholder reference --}}
            <div class="space-y-6">
                <x-ui.card>
                    <h3 class="mb-3 font-semibold text-slate-900">Bảng Placeholder</h3>
                    <p class="mb-3 text-sm text-slate-500">Nhấn để sao chép placeholder vào clipboard.</p>

                    <div id="placeholder-list" class="space-y-1">
                        @if (empty($placeholders))
                            <p class="text-sm text-slate-400" id="no-placeholders-hint">Chọn ngữ cảnh để xem danh sách placeholder.</p>
                        @else
                            @foreach ($placeholders as $ph)
                                <button
                                    type="button"
                                    onclick="copyPlaceholder('{{ $ph['key'] }}')"
                                    class="flex w-full items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-left text-sm hover:bg-slate-50 transition-colors"
                                    title="Nhấn để copy: {{{ $ph['key'] }}}"
                                >
                                    <code class="font-mono text-xs text-blue-700">{{{ $ph['key'] }}}</code>
                                    <span class="ml-2 text-xs text-slate-400">{{ $ph['label'] }}</span>
                                </button>
                            @endforeach
                        @endif
                    </div>

                    <div id="copy-toast" class="hidden mt-2 rounded bg-green-50 p-2 text-center text-xs text-green-700">
                        Đã sao chép!
                    </div>
                </x-ui.card>

                @if (!$template)
                    <x-ui.card>
                        <h3 class="mb-3 font-semibold text-slate-900">Mẫu khởi điểm</h3>
                        <p class="mb-3 text-sm text-slate-500">Chèn HTML mẫu theo ngữ cảnh để bắt đầu.</p>
                        <div class="space-y-2">
                            @foreach ($contexts as $ctx)
                                <button
                                    type="button"
                                    onclick="insertSample('{{ $ctx }}')"
                                    class="operator-button operator-button-secondary w-full text-sm"
                                >
                                    Chèn mẫu: {{ $contextLabels[$ctx] ?? $ctx }}
                                </button>
                            @endforeach
                        </div>
                    </x-ui.card>
                @endif
            </div>
        </div>
    </form>

    @if ($template)
        <form id="publish-form" method="POST" action="{{ route('operator.document-templates.publish', $template->id) }}" class="hidden">
            @csrf
        </form>
    @endif

    <script>
        // Sample HTML templates per context
        const SAMPLES = {
            contract: `<h1>Hợp đồng @{{contract_code}}</h1>
<p><strong>@{{contract_title}}</strong></p>
<table border="1" cellpadding="6" cellspacing="0" style="width:100%;border-collapse:collapse;">
  <tr><td>Mã hợp đồng</td><td>@{{contract_code}}</td></tr>
  <tr><td>Khách hàng</td><td>@{{client_name}}</td></tr>
  <tr><td>Giá trị</td><td>@{{total_value}} @{{currency}}</td></tr>
  <tr><td>Ngày ký</td><td>@{{signed_at}}</td></tr>
  <tr><td>Thời hạn</td><td>@{{start_date}} — @{{end_date}}</td></tr>
  <tr><td>Dự án</td><td>@{{project_name}} (@{{project_code}})</td></tr>
</table>
<h2>Bảng khối lượng</h2>
@{{boq_table_html}}`,
            certificate: `<h1>Giấy chứng nhận nghiệm thu — Kỳ @{{period_no}}</h1>
<table border="1" cellpadding="6" cellspacing="0" style="width:100%;border-collapse:collapse;">
  <tr><td>Hợp đồng</td><td>@{{contract_code}} — @{{contract_title}}</td></tr>
  <tr><td>Kỳ</td><td>@{{period_no}} (@{{period_from}} — @{{period_to}})</td></tr>
  <tr><td>Tổng kỳ này</td><td>@{{total_value}} @{{currency}}</td></tr>
  <tr><td>Giữ lại</td><td>@{{retention_amount}}</td></tr>
  <tr><td>Thu hồi tạm ứng</td><td>@{{advance_deduction}}</td></tr>
  <tr><td>Thanh toán NET</td><td>@{{net_payable}}</td></tr>
</table>
<h2>Chi tiết hạng mục</h2>
@{{lines_table_html}}`,
            project: `<h1>Thông tin dự án @{{project_name}}</h1>
<table border="1" cellpadding="6" cellspacing="0" style="width:100%;border-collapse:collapse;">
  <tr><td>Mã dự án</td><td>@{{project_code}}</td></tr>
  <tr><td>Trạng thái</td><td>@{{project_status}}</td></tr>
  <tr><td>Quản lý</td><td>@{{manager_name}}</td></tr>
  <tr><td>Khách hàng</td><td>@{{client_display}}</td></tr>
  <tr><td>Công ty</td><td>@{{tenant_name}}</td></tr>
</table>
<h2>Hạng mục thiết kế</h2>
@{{design_items_table_html}}`
        };

        function insertSample(ctx) {
            const textarea = document.getElementById('html_body');
            if (textarea && SAMPLES[ctx]) {
                textarea.value = SAMPLES[ctx];
            }
        }

        function copyPlaceholder(key) {
            navigator.clipboard.writeText('{{{ $placeholder_prefix ?? "" }}}' + key + '{{{ $placeholder_suffix ?? "" }}}').then(function() {
                const toast = document.getElementById('copy-toast');
                toast.classList.remove('hidden');
                setTimeout(function() { toast.classList.add('hidden'); }, 1500);
            });
        }

        function previewTemplate() {
            const form = document.getElementById('template-form');
            const htmlBody = document.getElementById('html_body').value;
            const templateId = '{{ $template->id ?? "" }}';

            if (!templateId) {
                alert('Lưu bản nháp trước khi xem thử.');
                return;
            }

            const previewUrl = '{{ route("operator.document-templates.preview", "__ID__") }}'.replace('__ID__', templateId);
            const previewForm = document.createElement('form');
            previewForm.method = 'POST';
            previewForm.action = previewUrl;
            previewForm.target = '_blank';

            const csrfToken = document.querySelector('input[name="_token"]');
            if (csrfToken) {
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = csrfToken.value;
                previewForm.appendChild(csrfInput);
            }

            const htmlInput = document.createElement('input');
            htmlInput.type = 'hidden';
            htmlInput.name = 'html_body';
            htmlInput.value = htmlBody;
            previewForm.appendChild(htmlInput);

            document.body.appendChild(previewForm);
            previewForm.submit();
            document.body.removeChild(previewForm);
        }

        function publishTemplate() {
            if (confirm('Xuất bản biểu mẫu? Phiên bản hiện tại sẽ được đóng gói và hiển thị cho người dùng.')) {
                document.getElementById('publish-form').submit();
            }
        }

        // Auto-update context-dependent placeholder list
        document.addEventListener('DOMContentLoaded', function() {
            const contextSelect = document.getElementById('context');
            if (contextSelect) {
                contextSelect.addEventListener('change', function() {
                    // Reload page with context to update placeholder list
                    const url = new URL(window.location.href);
                    if (this.value) {
                        url.searchParams.set('context', this.value);
                    }
                    window.location.href = url.toString();
                });
            }
        });
    </script>
@endsection
