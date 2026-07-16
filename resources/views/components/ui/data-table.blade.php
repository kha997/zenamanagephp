@props(['headers' => []])

<div class="overflow-x-auto">
    <table class="operator-table">
        @if ($headers !== [])
            <thead>
                <tr>
                    @foreach ($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
