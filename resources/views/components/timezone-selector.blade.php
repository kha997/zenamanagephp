@props(['currentTimezone' => 'UTC', 'availableTimezones' => []])

<div class="timezone-selector">
    <div class="relative">
        <button 
            type="button" 
            class="flex items-center space-x-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            onclick="toggleTimezoneDropdown()"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span>{{ $availableTimezones[$currentTimezone] ?? 'UTC' }}</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>

        <div 
            id="timezone-dropdown" 
            class="hidden absolute right-0 mt-2 w-64 bg-white border border-gray-300 rounded-md shadow-lg z-50 max-h-64 overflow-y-auto"
        >
            <div class="py-1">
                @foreach($availableTimezones as $code => $name)
                    <button 
                        type="button"
                        class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ $code === $currentTimezone ? 'bg-blue-50 text-blue-700' : '' }}"
                        onclick="changeTimezone('{{ $code }}')"
                    >
                        @if($code === $currentTimezone)
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        @endif
                        <div class="flex flex-col">
                            <span class="font-medium">{{ $name }}</span>
                            <span class="text-xs text-gray-500">{{ $code }}</span>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
</div>

<script>
function toggleTimezoneDropdown() {
    const dropdown = document.getElementById('timezone-dropdown');
    dropdown.classList.toggle('hidden');
}

function changeTimezone(timezoneCode) {
    // Close dropdown
    document.getElementById('timezone-dropdown').classList.add('hidden');
    
    // Show loading state
    const button = event.target.closest('button');
    const originalText = button.innerHTML;
    button.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Changing...';
    button.disabled = true;
    
    // Make API call to change timezone
    fetch('/api/i18n/timezone', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            timezone: timezoneCode
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload page to apply timezone change
            window.location.reload();
        } else {
            // Show error message
            alert('Failed to change timezone: ' + (data.error || 'Unknown error'));
            button.innerHTML = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Timezone change error:', error);
        alert('Failed to change timezone. Please try again.');
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('timezone-dropdown');
    const button = event.target.closest('button');
    
    if (!button || !button.onclick || button.onclick.toString().indexOf('toggleTimezoneDropdown') === -1) {
        dropdown.classList.add('hidden');
    }
});
</script>
