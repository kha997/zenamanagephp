@extends('layouts.operator')

@section('title', 'CRM — Hộp lead')
@section('page_title', 'CRM — Hộp lead')

@section('content')
    <x-ui.page-header
        title="Hộp lead"
        description="Ghi nhanh nhu cầu từ Zalo/hotline/fanpage — rồi chuyển thành cơ hội."
    >
        <x-ui.button-link :href="route('operator.crm.index')" variant="secondary">Pipeline</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Ghi nhận lead mới">
        @if ($errors->any())
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('operator.crm.leads.store') }}" class="space-y-4">
            @csrf
            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="contact_hint">Liên hệ (tên/SĐT/Zalo) <span class="text-rose-600">*</span></label>
                    <input id="contact_hint" name="contact_hint" type="text" class="operator-input" value="{{ old('contact_hint') }}" required placeholder="vd: Anh Hùng 09xx — Zalo">
                </div>
                <div class="operator-field">
                    <label for="source">Nguồn</label>
                    <select id="source" name="source" class="operator-select">
                        @foreach (['zalo' => 'Zalo', 'facebook' => 'Facebook', 'hotline' => 'Hotline', 'referral' => 'Giới thiệu', 'website' => 'Website', 'walk_in' => 'Ghé văn phòng', 'other' => 'Khác'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('source') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="operator-field">
                <label for="project_description">Mô tả nhu cầu</label>
                <textarea id="project_description" name="project_description" class="operator-textarea" placeholder="vd: Xây nhà phố 5x20, 3 tầng, khu Bình Chánh...">{{ old('project_description') }}</textarea>
            </div>
            <button type="submit" class="operator-button operator-button-primary">Ghi nhận</button>
        </form>
    </x-ui.card>

    @if ($leads->isEmpty())
        <x-ui.empty-state title="Chưa có lead" description="Ghi nhận lead đầu tiên từ form phía trên." />
    @else
        <x-ui.card title="Danh sách lead">
            <x-ui.data-table :headers="['Liên hệ', 'Nhu cầu', 'Nguồn', 'Trạng thái', 'Người ghi', 'Thao tác']">
                @foreach ($leads as $lead)
                    <tr>
                        <td class="font-medium text-slate-900">{{ $lead->contact_hint }}</td>
                        <td class="text-sm text-slate-600 max-w-xs">{{ Str::limit($lead->project_description, 80) }}</td>
                        <td class="text-sm text-slate-600">{{ $lead->source }}</td>
                        <td><x-ui.status-badge :status="$lead->status" /></td>
                        <td class="text-sm text-slate-600">{{ $lead->capturedBy?->name ?? '—' }}</td>
                        <td>
                            @if ($lead->status === 'new')
                                <details>
                                    <summary class="operator-link cursor-pointer text-sm">Chuyển thành cơ hội</summary>
                                    <form method="POST" action="{{ route('operator.crm.leads.convert', $lead->id) }}" class="mt-2 space-y-2">
                                        @csrf
                                        <select name="account_id" class="operator-select">
                                            <option value="">Tạo khách hàng mới từ lead</option>
                                            @foreach ($accounts as $account)
                                                <option value="{{ $account->id }}">Gắn: {{ $account->display_name }}</option>
                                            @endforeach
                                        </select>
                                        <input name="account_name" type="text" class="operator-input" placeholder="Tên khách hàng (nếu tạo mới)" value="{{ $lead->contact_hint }}">
                                        <input name="opportunity_name" type="text" class="operator-input" placeholder="Tên cơ hội *" required>
                                        <button type="submit" class="operator-button operator-button-primary">Chuyển</button>
                                    </form>
                                </details>
                                <form method="POST" action="{{ route('operator.crm.leads.discard', $lead->id) }}" class="mt-1"
                                      onsubmit="return confirm('Loại lead này?');">
                                    @csrf
                                    <button type="submit" class="operator-link text-sm text-rose-600">Loại</button>
                                </form>
                            @elseif ($lead->converted_opportunity_id)
                                <a href="{{ route('operator.crm.opportunities.show', $lead->converted_opportunity_id) }}" class="operator-link text-sm">Xem cơ hội →</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        </x-ui.card>
    @endif
@endsection
