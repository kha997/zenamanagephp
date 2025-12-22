{{-- KPI Strip Component --}}
@props(['kpis' => []])

<div class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($kpis as $kpi)
            <div class="bg-gradient-to-r {{ $kpi['gradient'] }} rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium opacity-90">{{ $kpi['label'] }}</p>
                        <p class="text-3xl font-bold">{{ $kpi['value'] }}</p>
                        <p class="text-sm opacity-90">{{ $kpi['subtitle'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <i class="{{ $kpi['icon'] }} text-2xl"></i>
                    </div>
                </div>
                @if(isset($kpi['action']))
                <button class="mt-4 w-full bg-white bg-opacity-20 hover:bg-opacity-30 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                    {{ $kpi['action'] }}
                </button>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</div>
