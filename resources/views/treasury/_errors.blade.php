@if ($errors->any())
    <div class="operator-error-list">
        <ul class="space-y-1 text-sm">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
