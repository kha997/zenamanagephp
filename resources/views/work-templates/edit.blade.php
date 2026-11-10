@extends('layouts.operator')

@section('title', 'Soạn mẫu: ' . $template->name)
@section('page_title', 'Soạn mẫu công việc')

@section('content')
    {{-- GAP-123 E-1: edit the draft — each step becomes one project task when the template is applied. --}}
    @php
        $formRows = old('steps', $rows);
        $formRows = is_array($formRows) ? array_values($formRows) : [];
        $countFilled = count($formRows);
        for ($i = 0; $i < 3; $i++) {
            $formRows[] = ['key' => '', 'order' => $countFilled + $i + 1, 'name' => '', 'type' => 'task', 'days' => 1, 'after' => [], 'description' => '', 'priority' => 'medium', 'phase' => '', 'checklist' => '', 'docs' => ''];
        }
        $fieldCounts = collect($rows)->pluck('field_count', 'key')->all();
    @endphp
    <x-ui.page-header :title="$template->name" :description="$template->code . ($latestPublished ? ' · Đã phát hành ' . $latestPublished->semver : ' · Chưa phát hành')">
        <x-ui.button-link :href="route('app.work-templates.index')" variant="secondary">Danh sách mẫu</x-ui.button-link>
        @if ($editable)
            <form method="POST" action="{{ route('app.work-templates.duplicate', $template->id) }}" class="inline">
                @csrf
                <button type="submit" class="operator-button operator-button-secondary">Nhân bản</button>
            </form>
        @endif
        @if ($canPublish && $hasDraft)
            <form method="POST" action="{{ route('app.work-templates.publish', $template->id) }}" class="inline" onsubmit="return confirm('Phát hành bản nháp này? PM sẽ áp dụng được cho dự án.')">
                @csrf
                <button type="submit" class="operator-button operator-button-primary" data-testid="work-template-publish">Phát hành</button>
            </form>
        @endif
    </x-ui.page-header>

    @if ($errors->any())
        <ul class="mb-3 space-y-1 text-sm text-red-700">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    @endif

    @if (! $editable)
        <x-ui.card>
            <p class="p-4 text-sm text-slate-600">Mẫu này dùng cấu trúc giai đoạn lồng nhau và chỉ sửa được qua API.</p>
        </x-ui.card>
    @else
        <p class="mb-3 text-sm text-slate-600">
            @if ($hasDraft)
                Đang sửa <strong>bản nháp</strong>. Bấm "Phát hành" khi xong để PM áp dụng được.
            @else
                Đang xem bản đã phát hành. Lưu thay đổi sẽ tạo <strong>bản nháp mới</strong>; dự án đã áp dụng bản cũ không bị ảnh hưởng.
            @endif
        </p>

        <form method="POST" action="{{ route('app.work-templates.update', $template->id) }}" class="space-y-4" data-testid="work-template-edit-form">
            @csrf
            <x-ui.card>
                <div class="grid gap-3 p-4 md:grid-cols-2">
                    <label class="grid gap-1 text-sm">Tên mẫu
                        <input type="text" name="name" maxlength="255" required class="operator-input" value="{{ old('name', $template->name) }}">
                    </label>
                    <label class="grid gap-1 text-sm">Mô tả
                        <input type="text" name="description" maxlength="2000" class="operator-input" value="{{ old('description', $template->description) }}">
                    </label>
                </div>
            </x-ui.card>

            <x-ui.card title="Các bước">
                <div class="space-y-3 p-4">
                    <p class="text-xs text-slate-500">Mỗi bước thành một việc của dự án, giao cho PM của dự án. Checklist và tài liệu: mỗi dòng một mục, thêm <code>*</code> đầu dòng nếu bắt buộc. Dòng để trống tên sẽ được bỏ qua.</p>
                    @foreach ($formRows as $i => $row)
                        @php
                            $key = (string) ($row['key'] ?? '');
                            $isNew = $key === '';
                            $earlier = array_values(array_filter(array_slice($formRows, 0, $i), fn ($r) => trim((string) ($r['name'] ?? '')) !== '' && (string) ($r['key'] ?? '') !== ''));
                            $after = array_map('strval', (array) ($row['after'] ?? []));
                        @endphp
                        <fieldset class="operator-item-card" data-testid="work-template-step">
                            <legend class="sr-only">Bước {{ $i + 1 }}</legend>
                            <input type="hidden" name="steps[{{ $i }}][key]" value="{{ $key }}">
                            <div class="grid gap-2 md:grid-cols-12">
                                <label class="grid gap-1 text-xs md:col-span-1">Thứ tự
                                    <input type="number" name="steps[{{ $i }}][order]" min="1" max="999" class="operator-input" value="{{ $row['order'] ?? $i + 1 }}">
                                </label>
                                <label class="grid gap-1 text-xs md:col-span-4">Tên bước {{ $isNew ? '(bước mới)' : '' }}
                                    <input type="text" name="steps[{{ $i }}][name]" maxlength="255" class="operator-input" value="{{ $row['name'] ?? '' }}" placeholder="VD: Đổ bê tông móng">
                                </label>
                                <label class="grid gap-1 text-xs md:col-span-2">Loại
                                    <select name="steps[{{ $i }}][type]" class="operator-input">
                                        @foreach ($types as $value => $label)
                                            <option value="{{ $value }}" @selected(($row['type'] ?? 'task') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="grid gap-1 text-xs md:col-span-1">Số ngày
                                    <input type="number" name="steps[{{ $i }}][days]" min="0" max="365" class="operator-input" value="{{ $row['days'] ?? 1 }}">
                                </label>
                                <label class="grid gap-1 text-xs md:col-span-2">Ưu tiên
                                    <select name="steps[{{ $i }}][priority]" class="operator-input">
                                        @foreach ($priorities as $value => $label)
                                            <option value="{{ $value }}" @selected(($row['priority'] ?? 'medium') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="grid gap-1 text-xs md:col-span-2">Giai đoạn
                                    <input type="text" name="steps[{{ $i }}][phase]" maxlength="100" class="operator-input" value="{{ $row['phase'] ?? '' }}" placeholder="VD: Phần móng">
                                </label>
                            </div>
                            <label class="grid gap-1 text-xs">Mô tả
                                <input type="text" name="steps[{{ $i }}][description]" maxlength="1000" class="operator-input" value="{{ $row['description'] ?? '' }}">
                            </label>
                            <div class="grid gap-2 md:grid-cols-2">
                                <label class="grid gap-1 text-xs">Checklist
                                    <textarea name="steps[{{ $i }}][checklist]" rows="3" class="operator-input">{{ $row['checklist'] ?? '' }}</textarea>
                                </label>
                                <label class="grid gap-1 text-xs">Tài liệu cần nộp
                                    <textarea name="steps[{{ $i }}][docs]" rows="3" class="operator-input">{{ $row['docs'] ?? '' }}</textarea>
                                </label>
                            </div>
                            <div class="text-xs">
                                <span class="text-slate-600">Làm sau bước:</span>
                                @forelse ($earlier as $prev)
                                    <label class="mr-3 inline-flex items-center gap-1">
                                        <input type="checkbox" name="steps[{{ $i }}][after][]" value="{{ $prev['key'] }}" @checked(in_array((string) $prev['key'], $after, true))>
                                        {{ $prev['name'] }}
                                    </label>
                                @empty
                                    <span class="text-slate-500">— (bắt đầu từ ngày áp dụng)</span>
                                @endforelse
                            </div>
                            @if (($fieldCounts[$key] ?? 0) > 0)
                                <p class="text-xs text-slate-500">Bước này có {{ $fieldCounts[$key] }} trường nhập liệu riêng (giữ nguyên, chỉ sửa qua API).</p>
                            @endif
                        </fieldset>
                    @endforeach
                    <p class="text-xs text-slate-500">Bước mới chỉ chọn được "làm sau" các bước đã lưu; lưu nháp rồi chọn tiếp.</p>
                </div>
            </x-ui.card>

            <div><button type="submit" class="operator-button operator-button-primary">Lưu nháp</button></div>
        </form>

        <x-ui.card title="Xem trước: các việc sẽ tạo khi áp dụng">
            <div class="p-4" data-testid="work-template-preview">
                @if ($preview === [])
                    <p class="text-sm text-slate-500">Chưa có bước nào.</p>
                @else
                    <x-ui.data-table :headers="['#', 'Việc', 'Loại', 'Giai đoạn', 'Làm sau', 'Thời gian', 'Checklist / tài liệu']">
                        @foreach ($preview as $n => $item)
                            <tr>
                                <td class="text-sm">{{ $n + 1 }}</td>
                                <td class="text-sm font-semibold">{{ $item['name'] }}</td>
                                <td class="text-sm">{{ $types[$item['type']] ?? $item['type'] }}</td>
                                <td class="text-sm">{{ $item['phase'] ?: '—' }}</td>
                                <td class="text-xs">{{ $item['after'] === [] ? '—' : implode(', ', $item['after']) }}</td>
                                <td class="text-sm">Ngày {{ $item['from'] }} – {{ $item['to'] }}</td>
                                <td class="text-xs">{{ $item['checklist'] }} mục / {{ $item['docs'] }} tài liệu</td>
                            </tr>
                        @endforeach
                    </x-ui.data-table>
                    <p class="mt-2 text-xs text-slate-500">Ngày 1 là ngày bắt đầu của dự án khi áp dụng.</p>
                @endif
            </div>
        </x-ui.card>
    @endif
@endsection
