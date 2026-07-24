@extends('layouts.operator')

@section('title', 'Submittal ' . $submittal->submittal_number)
@section('page_title', 'Submittal ' . $submittal->submittal_number)

@section('content')
    <x-ui.page-header
        :title="'Submittal ' . $submittal->submittal_number"
        :description="$submittal->title"
    >
        <x-ui.button-link :href="route('operator.submittals.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    @if (session('error'))
        <div class="operator-error-list">{{ session('error') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-ui.card title="Mô tả">
                <div class="whitespace-pre-line text-slate-800">{{ $submittal->description }}</div>
            </x-ui.card>

            @if ($submittal->approval_comments)
                <x-ui.card title="Ý kiến phê duyệt">
                    <div class="whitespace-pre-line text-slate-800">{{ $submittal->approval_comments }}</div>
                </x-ui.card>
            @endif

            @if (in_array($submittal->status, ['rejected', 'revising'], true) && $submittal->rejection_reason)
                <x-ui.card title="Lần nộp #{{ $submittal->current_revision_no }} bị từ chối">
                    <div class="space-y-3">
                        <x-ui.field-value label="Người từ chối" :value="$submittal->rejectedBy?->name" />
                        <x-ui.field-value label="Thời gian" :value="optional($submittal->rejected_at)->format('d/m/Y H:i')" />
                        <div class="whitespace-pre-line text-slate-800">{{ $submittal->rejection_reason }}</div>
                        @if ($submittal->rejection_comments)
                            <div class="whitespace-pre-line text-sm text-slate-600">{{ $submittal->rejection_comments }}</div>
                        @endif
                    </div>
                </x-ui.card>
            @endif

            @can('update', $submittal)
                @if (in_array($submittal->status, ['draft', 'revising'], true))
                    <x-ui.card title="Sửa nội dung">
                        @if ($errors->submittalUpdate->any())
                            <div class="operator-error-list">
                                <ul class="space-y-1 text-sm">
                                    @foreach ($errors->submittalUpdate->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form id="submittal-edit-form" method="POST" action="{{ route('operator.submittals.update', $submittal->id) }}" class="space-y-5">
                            @csrf
                            @method('PUT')

                            <div class="operator-form-grid">
                                <div class="operator-field">
                                    <label for="submittal_type">Loại hồ sơ</label>
                                    <select id="submittal_type" name="submittal_type" class="operator-select">
                                        <option value="shop_drawing" @selected(old('submittal_type', $submittal->submittal_type) === 'shop_drawing')>Shop drawing</option>
                                        <option value="material_sample" @selected(old('submittal_type', $submittal->submittal_type) === 'material_sample')>Mẫu vật liệu</option>
                                        <option value="product_data" @selected(old('submittal_type', $submittal->submittal_type) === 'product_data')>Tài liệu sản phẩm</option>
                                        <option value="test_report" @selected(old('submittal_type', $submittal->submittal_type) === 'test_report')>Báo cáo thí nghiệm</option>
                                        <option value="other" @selected(old('submittal_type', $submittal->submittal_type) === 'other')>Khác</option>
                                    </select>
                                </div>

                                <div class="operator-field">
                                    <label for="specification_section">Mục spec</label>
                                    <input id="specification_section" name="specification_section" type="text" class="operator-input" value="{{ old('specification_section', $submittal->specification_section) }}">
                                </div>

                                <div class="operator-field">
                                    <label for="due_date">Hạn duyệt</label>
                                    <input id="due_date" name="due_date" type="date" class="operator-input" value="{{ old('due_date', optional($submittal->due_date)->format('Y-m-d')) }}">
                                </div>

                                <div class="operator-field">
                                    <label for="contractor">Nhà thầu</label>
                                    <select id="contractor" name="contractor" class="operator-select">
                                        <option value="">— Chọn nhà cung cấp —</option>
                                        @php $currentContractor = old('contractor', $submittal->contractor); @endphp
                                        @if ($currentContractor && !$vendors->pluck('name')->contains($currentContractor))
                                            <option value="{{ $currentContractor }}" selected>{{ $currentContractor }} (không còn hoạt động)</option>
                                        @endif
                                        @foreach ($vendors as $vendor)
                                            <option value="{{ $vendor->name }}" @selected($currentContractor === $vendor->name)>{{ $vendor->name }}{{ $vendor->code ? ' (' . $vendor->code . ')' : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="operator-field">
                                    <label for="manufacturer">Nhà sản xuất</label>
                                    <select id="manufacturer" name="manufacturer" class="operator-select">
                                        <option value="">— Chọn nhà cung cấp —</option>
                                        @php $currentManufacturer = old('manufacturer', $submittal->manufacturer); @endphp
                                        @if ($currentManufacturer && !$vendors->pluck('name')->contains($currentManufacturer))
                                            <option value="{{ $currentManufacturer }}" selected>{{ $currentManufacturer }} (không còn hoạt động)</option>
                                        @endif
                                        @foreach ($vendors as $vendor)
                                            <option value="{{ $vendor->name }}" @selected($currentManufacturer === $vendor->name)>{{ $vendor->name }}{{ $vendor->code ? ' (' . $vendor->code . ')' : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="operator-field">
                                <label for="title">Tiêu đề <span class="text-rose-600">*</span></label>
                                <input id="title" name="title" type="text" class="operator-input" value="{{ old('title', $submittal->title) }}" maxlength="255" required>
                            </div>

                            <div class="operator-field">
                                <label for="description">Mô tả <span class="text-rose-600">*</span></label>
                                <textarea id="description" name="description" class="operator-textarea" required>{{ old('description', $submittal->description) }}</textarea>
                            </div>

                            <button type="submit" class="operator-button operator-button-primary">Lưu thay đổi</button>
                        </form>
                    </x-ui.card>
                @endif
            @endcan

            @if (in_array($submittal->status, ['submitted', 'pending_review'], true))
                <x-ui.card title="Xét duyệt">
                    @if ($errors->any())
                        <div class="operator-error-list">
                            <ul class="space-y-1 text-sm">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="space-y-6">
                        <form method="POST" action="{{ route('operator.submittals.approve', $submittal->id) }}" class="space-y-4">
                            @csrf
                            <div class="operator-field">
                                <label for="approval_comments">Ý kiến phê duyệt (không bắt buộc)</label>
                                <textarea id="approval_comments" name="approval_comments" class="operator-textarea">{{ old('approval_comments') }}</textarea>
                            </div>
                            <button type="submit" class="operator-button operator-button-primary">Phê duyệt</button>
                        </form>

                        <hr class="border-gray-200">

                        <form method="POST" action="{{ route('operator.submittals.reject', $submittal->id) }}" class="space-y-4">
                            @csrf
                            <div class="operator-field">
                                <label for="rejection_reason">Lý do từ chối <span class="text-rose-600">*</span></label>
                                <textarea id="rejection_reason" name="rejection_reason" class="operator-textarea" required>{{ old('rejection_reason') }}</textarea>
                            </div>
                            <button type="submit" class="operator-button operator-button-secondary">Từ chối</button>
                        </form>
                    </div>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            <x-ui.card title="Thông tin">
                <div class="space-y-4">
                    <x-ui.field-value label="Trạng thái">
                        <x-ui.status-badge :status="$submittal->status" />
                    </x-ui.field-value>
                    <x-ui.field-value label="Dự án" :value="($submittal->project?->name ?? '—') . ($submittal->project?->code ? ' (' . $submittal->project->code . ')' : '')" />
                    <x-ui.field-value label="Loại hồ sơ" :value="match($submittal->submittal_type) { 'shop_drawing' => 'Shop drawing', 'material_sample' => 'Mẫu vật liệu', 'product_data' => 'Tài liệu sản phẩm', 'test_report' => 'Báo cáo thí nghiệm', default => 'Khác' }" />
                    <x-ui.field-value label="Mục spec" :value="$submittal->specification_section" />
                    <x-ui.field-value label="Nhà thầu" :value="$submittal->contractor" />
                    <x-ui.field-value label="Nhà sản xuất" :value="$submittal->manufacturer" />
                    <x-ui.field-value label="Người trình" :value="$submittal->submittedBy?->name" />
                    <x-ui.field-value label="Hạn duyệt" :value="optional($submittal->due_date)->format('d/m/Y')" />
                    <x-ui.field-value label="Ngày tạo" :value="optional($submittal->created_at)->format('d/m/Y H:i')" />
                </div>
            </x-ui.card>

            <x-ui.card title="Thao tác">
                @can('submit', $submittal)
                    @if ($submittal->status === 'draft')
                        <form method="POST" action="{{ route('operator.submittals.submit', $submittal->id) }}">
                            @csrf
                            <button type="submit" class="operator-button operator-button-primary w-full">Gửi duyệt</button>
                        </form>
                    @endif

                    @if ($submittal->status === 'revising')
                        @if ($errors->submittalResubmit->any())
                            <div class="operator-error-list">
                                <ul class="space-y-1 text-sm">
                                    @foreach ($errors->submittalResubmit->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('operator.submittals.submit', $submittal->id) }}" class="space-y-3">
                            @csrf
                            <div class="operator-field">
                                <label for="revision_summary">Tóm tắt thay đổi <span class="text-rose-600">*</span></label>
                                <textarea id="revision_summary" name="revision_summary" class="operator-textarea" required>{{ old('revision_summary') }}</textarea>
                            </div>
                            <p id="unsaved-changes-warning" class="hidden text-sm text-rose-600">Bạn có thay đổi chưa lưu — bấm "Lưu thay đổi" trước.</p>
                            <button id="resubmit-button" type="submit" class="operator-button operator-button-primary w-full">Gửi lại</button>
                        </form>
                    @endif
                @endcan

                @can('startRevision', $submittal)
                    @if ($submittal->status === 'rejected')
                        <form method="POST" action="{{ route('operator.submittals.start-revision', $submittal->id) }}">
                            @csrf
                            <button type="submit" class="operator-button operator-button-primary w-full">Mở lại để sửa</button>
                        </form>
                    @endif
                @endcan
            </x-ui.card>
        </div>
    </div>

    @if ($submittal->status === 'revising')
        <script>
        (function () {
          var editForm = document.getElementById('submittal-edit-form');
          var resubmitBtn = document.getElementById('resubmit-button');
          var warning = document.getElementById('unsaved-changes-warning');
          if (!editForm || !resubmitBtn) return;

          var IGNORED_FIELDS = ['_token', '_method'];

          function snapshot(form) {
            var data = {};
            Array.prototype.forEach.call(form.elements, function (el) {
              if (!el.name || IGNORED_FIELDS.indexOf(el.name) !== -1) return;
              if (el.type === 'submit' || el.type === 'button') return;
              data[el.name] = (el.value || '').trim();
            });
            return data;
          }

          var initial = snapshot(editForm);

          function isDirty() {
            var current = snapshot(editForm);
            for (var key in initial) {
              if (initial[key] !== current[key]) return true;
            }
            return false;
          }

          function refresh() {
            var dirty = isDirty();
            resubmitBtn.disabled = dirty;
            resubmitBtn.classList.toggle('opacity-50', dirty);
            resubmitBtn.classList.toggle('cursor-not-allowed', dirty);
            if (warning) warning.classList.toggle('hidden', !dirty);
          }

          editForm.addEventListener('input', refresh);
          editForm.addEventListener('change', refresh);
          refresh();
        })();
        </script>
    @endif
@endsection
