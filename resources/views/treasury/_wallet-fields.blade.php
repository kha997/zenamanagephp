<div class="operator-form-grid">
    <div class="operator-field">
        <label for="name">Tên ví <span class="text-rose-600">*</span></label>
        <input id="name" name="name" type="text" class="operator-input" value="{{ old('name', $wallet?->name) }}" maxlength="255" required>
    </div>
    <div class="operator-field">
        <label for="wallet_type">Loại <span class="text-rose-600">*</span></label>
        <select id="wallet_type" name="wallet_type" class="operator-input" required>
            @foreach ($walletTypes as $value => $label)
                <option value="{{ $value }}" @selected(old('wallet_type', $wallet?->wallet_type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="operator-field">
        <label for="custodian_party_id">Người giữ</label>
        <select id="custodian_party_id" name="custodian_party_id" class="operator-input">
            <option value="">— Không có —</option>
            @foreach ($parties as $party)
                <option value="{{ $party->id }}" @selected(old('custodian_party_id', $wallet?->custodian_party_id) === $party->id)>{{ $party->name }}</option>
            @endforeach
        </select>
    </div>
</div>
