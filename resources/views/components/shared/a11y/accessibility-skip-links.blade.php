{{-- Accessibility Skip Links Component --}}
{{-- Provides keyboard navigation shortcuts for screen readers --}}

<!-- Skip Links -->
<div class="skip-links">
    <a href="#main-content" class="skip-link sr-only focus:not-sr-only focus:absolute focus:top-0 focus:left-0 focus:z-50 focus:bg-blue-600 focus:text-white focus:px-4 focus:py-2 focus:rounded-md focus:shadow-lg">
        Skip to main content
    </a>
    <a href="#navigation" class="skip-link sr-only focus:not-sr-only focus:absolute focus:top-0 focus:left-0 focus:z-50 focus:bg-blue-600 focus:text-white focus:px-4 focus:py-2 focus:rounded-md focus:shadow-lg">
        Skip to navigation
    </a>
    <a href="#search" class="skip-link sr-only focus:not-sr-only focus:absolute focus:top-0 focus:left-0 focus:z-50 focus:bg-blue-600 focus:text-white focus:px-4 focus:py-2 focus:rounded-md focus:shadow-lg">
        Skip to search
    </a>
</div>

<style>
/* Screen reader only class */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* Focus styles for skip links */
.skip-link:focus {
    position: absolute;
    top: 0;
    left: 0;
    z-index: 1000;
    background: #2563eb;
    color: white;
    padding: 8px 16px;
    border-radius: 4px;
    text-decoration: none;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    font-weight: 600;
}

/* High contrast focus indicators */
*:focus {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}

/* Remove default focus outline for custom styled elements */
button:focus,
input:focus,
select:focus,
textarea:focus {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}

/* Focus visible for mouse users */
.focus-visible:focus:not(:focus-visible) {
    outline: none;
}
</style>
