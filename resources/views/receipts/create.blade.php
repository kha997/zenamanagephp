@extends('layouts.operator')

@section('title', 'Tạo phiếu nhập')
@section('page_title', 'Tạo phiếu nhập')

@section('content')
    <x-ui.page-header
        title="Tạo phiếu nhập"
        description="Tạo header phiếu nhập trước khi thêm dòng vật tư."
    >
        <x-ui.button-link :href="route('operator.receipts.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thông tin phiếu nhập">
        @if ($errors->any())
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('operator.receipts.store') }}" class="space-y-5">
            @csrf

            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="project_id">Dự án</label>
                    <select id="project_id" name="project_id" class="operator-select" required>
                        <option value="">Chọn dự án</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id') === (string) $project->id)>
                                {{ $project->name }}{{ $project->code ? ' (' . $project->code . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="operator-field">
                    <label for="vendor_id">Nhà cung cấp</label>
                    <select id="vendor_id" name="vendor_id" class="operator-select">
                        <option value="">Không chọn</option>
                        @foreach ($vendors as $vendor)
                            <option value="{{ $vendor->id }}" @selected(old('vendor_id') === (string) $vendor->id)>
                                {{ $vendor->name }}{{ $vendor->code ? ' (' . $vendor->code . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="operator-field">
                    <label for="contract_id">Hợp đồng</label>
                    <select id="contract_id" name="contract_id" class="operator-select">
                        <option value="">Không chọn</option>
                        @foreach ($contracts as $contract)
                            <option value="{{ $contract->id }}" @selected(old('contract_id') === (string) $contract->id)>
                                {{ $contract->code }} - {{ $contract->title }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="operator-field">
                    <label for="material_request_id">Yêu cầu vật tư</label>
                    <select id="material_request_id" name="material_request_id" class="operator-select">
                        <option value="">Không chọn</option>
                        @foreach ($materialRequests as $materialRequest)
                            <option value="{{ $materialRequest->id }}" @selected(old('material_request_id') === (string) $materialRequest->id)>
                                {{ $materialRequest->request_number }} - {{ $materialRequest->project?->name ?? '—' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="operator-field">
                    <label for="receipt_number">Mã phiếu nhập</label>
                    <input id="receipt_number" name="receipt_number" type="text" class="operator-input" value="{{ old('receipt_number') }}" required>
                </div>

                <div class="operator-field">
                    <label for="receipt_date">Ngày nhập</label>
                    <input id="receipt_date" name="receipt_date" type="date" class="operator-input" value="{{ old('receipt_date') }}" required>
                </div>
            </div>

            <button type="submit" class="operator-button operator-button-primary">Tạo phiếu nhập</button>
        </form>
    </x-ui.card>
@endsection
