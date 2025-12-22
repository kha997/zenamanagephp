{{-- ProjectsNext React Wrapper --}}
{{-- Renders React ProjectsNext component --}}

@props([
    'user' => null,
    'tenant' => null
])

@php
    $user = $user ?? Auth::user();
    $tenant = $tenant ?? ($user ? $user->tenant : null);
@endphp

<div id="projects-next-root" 
     data-user="{{ json_encode($user) }}"
     data-tenant="{{ json_encode($tenant) }}"
     class="projects-next-container">
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Wait for React to be available
    if (typeof React === 'undefined' || typeof ReactDOM === 'undefined') {
        console.error('React or ReactDOM not loaded. ProjectsNext requires React.');
        return;
    }
    
    const container = document.getElementById('projects-next-root');
    if (!container) {
        console.error('ProjectsNext container not found');
        return;
    }
    
    // Parse data from container attributes
    const user = JSON.parse(container.dataset.user || 'null');
    const tenant = JSON.parse(container.dataset.tenant || 'null');
    
    // Import ProjectsNext component dynamically
    import('/resources/js/pages/app/ProjectsNext.tsx').then(({ default: ProjectsNext }) => {
        // Render ProjectsNext component
        ReactDOM.render(
            React.createElement(ProjectsNext),
            container
        );
    }).catch(error => {
        console.error('Failed to load ProjectsNext component:', error);
        container.innerHTML = '<div class="p-4 bg-red-50 border border-red-200 rounded"><p class="text-red-600">Failed to load Projects page. Please refresh.</p></div>';
    });
});
</script>
@endpush

