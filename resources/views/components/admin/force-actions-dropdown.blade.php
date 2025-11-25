{{-- Force Actions Dropdown --}}
@props(['projectId'])

<div class="relative" x-data="{ open: false }">
    <button @click="open = !open" 
            class="text-gray-600 hover:text-gray-800 focus:outline-none"
            title="Force Actions">
        <i class="fas fa-ellipsis-v"></i>
    </button>
    
    <div x-show="open" 
         @click.away="open = false"
         x-transition
         class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10 border border-gray-200">
        <div class="py-1">
            <button onclick="forceFreezeProject('{{ $projectId }}')" 
                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                <i class="fas fa-snowflake mr-2 text-blue-600"></i>
                Freeze Project
            </button>
            <button onclick="forceArchiveProject('{{ $projectId }}')" 
                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                <i class="fas fa-archive mr-2 text-yellow-600"></i>
                Archive Project
            </button>
            <button onclick="emergencySuspendProject('{{ $projectId }}')" 
                    class="block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50">
                <i class="fas fa-exclamation-triangle mr-2 text-red-600"></i>
                Emergency Suspend
            </button>
        </div>
    </div>
</div>

<script>
function forceFreezeProject(projectId) {
    if (!confirm('Are you sure you want to freeze this project? This action can only be undone by an admin.')) {
        return;
    }
    
    fetch(`/admin/projects/${projectId}/freeze`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ reason: prompt('Reason for freezing:') || 'Admin freeze action' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Project frozen successfully');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to freeze project'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to freeze project');
    });
}

function forceArchiveProject(projectId) {
    if (!confirm('Are you sure you want to archive this project? This action can only be undone by an admin.')) {
        return;
    }
    
    fetch(`/admin/projects/${projectId}/archive`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ reason: prompt('Reason for archiving:') || 'Admin archive action' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Project archived successfully');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to archive project'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to archive project');
    });
}

function emergencySuspendProject(projectId) {
    if (!confirm('WARNING: This will emergency suspend the project. Are you absolutely sure?')) {
        return;
    }
    
    const reason = prompt('Emergency suspension reason (required):');
    if (!reason) {
        alert('Reason is required for emergency suspension');
        return;
    }
    
    fetch(`/admin/projects/${projectId}/emergency-suspend`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ reason: reason })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Project emergency suspended successfully');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to suspend project'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to suspend project');
    });
}
</script>

