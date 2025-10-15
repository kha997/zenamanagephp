


<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'user' => null,
    'tenant' => null
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'user' => null,
    'tenant' => null
]); ?>
<?php foreach (array_filter(([
    'user' => null,
    'tenant' => null
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
    $user = $user ?? Auth::user();
    $tenant = $tenant ?? ($user ? $user->tenant : null);
?>

<div id="projects-root" 
     data-user="<?php echo e(json_encode($user)); ?>"
     data-tenant="<?php echo e(json_encode($tenant)); ?>"
     class="projects-container">
</div>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Wait for React to be available
    if (typeof React === 'undefined' || typeof ReactDOM === 'undefined') {
        console.error('React or ReactDOM not loaded. Projects requires React.');
        return;
    }
    
    const container = document.getElementById('projects-root');
    if (!container) {
        console.error('Projects container not found');
        return;
    }
    
    // Parse data from container attributes
    const user = JSON.parse(container.dataset.user || 'null');
    const tenant = JSON.parse(container.dataset.tenant || 'null');
    
    // Import Projects component dynamically
    import('/resources/js/pages/app/Projects.tsx').then(({ default: Projects }) => {
        // Render Projects component
        ReactDOM.render(
            React.createElement(Projects),
            container
        );
    }).catch(error => {
        console.error('Failed to load Projects component:', error);
        // Fallback to simple projects page
        container.innerHTML = `
            <div class="min-h-screen bg-gray-50">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-red-400 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Projects failed to load</h3>
                                <p class="text-sm text-red-700 mt-1">Please refresh the page or contact support.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
});
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/projects-wrapper.blade.php ENDPATH**/ ?>