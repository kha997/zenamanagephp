


<div class="aria-labels-container">
    <!-- Screen reader only text -->
    <span class="sr-only" id="screen-reader-instructions">
        Use Tab to navigate through the interface. Use Enter or Space to activate buttons and links. 
        Use Alt+M to open modal, Alt+S to focus search, Alt+N to focus navigation.
    </span>
    
    <!-- Landmark roles -->
    <header role="banner" aria-label="Site header">
        <slot name="header"></slot>
    </header>
    
    <nav role="navigation" aria-label="Main navigation" id="navigation">
        <slot name="navigation"></slot>
    </nav>
    
    <main role="main" id="main-content" aria-label="Main content">
        <slot name="main"></slot>
    </main>
    
    <aside role="complementary" aria-label="Sidebar content">
        <slot name="sidebar"></slot>
    </aside>
    
    <footer role="contentinfo" aria-label="Site footer">
        <slot name="footer"></slot>
    </footer>
    
    <!-- Form accessibility -->
    <form role="form" aria-label="Form">
        <slot name="form"></slot>
    </form>
    
    <!-- Button accessibility -->
    <button 
        type="button" 
        aria-label="Close dialog"
        aria-describedby="close-button-description"
        class="close-button"
    >
        <span aria-hidden="true">×</span>
        <span class="sr-only">Close</span>
    </button>
    <div id="close-button-description" class="sr-only">
        Closes the current dialog or modal
    </div>
    
    <!-- Loading states -->
    <div 
        role="status" 
        aria-live="polite" 
        aria-label="Loading status"
        class="loading-indicator sr-only"
    >
        <slot name="loading"></slot>
    </div>
    
    <!-- Error states -->
    <div 
        role="alert" 
        aria-live="assertive" 
        aria-label="Error message"
        class="error-message"
    >
        <slot name="error"></slot>
    </div>
    
    <!-- Success states -->
    <div 
        role="status" 
        aria-live="polite" 
        aria-label="Success message"
        class="success-message"
    >
        <slot name="success"></slot>
    </div>
    
    <!-- Progress indicators -->
    <div 
        role="progressbar" 
        aria-valuenow="0" 
        aria-valuemin="0" 
        aria-valuemax="100" 
        aria-label="Progress"
        class="progress-bar"
    >
        <div class="progress-fill" style="width: 0%"></div>
    </div>
    
    <!-- Tab panels -->
    <div role="tablist" aria-label="Tab navigation">
        <button 
            role="tab" 
            aria-selected="true" 
            aria-controls="tab-panel-1" 
            id="tab-1"
            class="tab-button"
        >
            Tab 1
        </button>
        <button 
            role="tab" 
            aria-selected="false" 
            aria-controls="tab-panel-2" 
            id="tab-2"
            class="tab-button"
        >
            Tab 2
        </button>
    </div>
    
    <div 
        role="tabpanel" 
        aria-labelledby="tab-1" 
        id="tab-panel-1"
        class="tab-panel"
    >
        <slot name="tab-panel-1"></slot>
    </div>
    
    <div 
        role="tabpanel" 
        aria-labelledby="tab-2" 
        id="tab-panel-2"
        class="tab-panel"
        hidden
    >
        <slot name="tab-panel-2"></slot>
    </div>
    
    <!-- List accessibility -->
    <ul role="list" aria-label="Item list">
        <li role="listitem">
            <slot name="list-item"></slot>
        </li>
    </ul>
    
    <!-- Table accessibility -->
    <table role="table" aria-label="Data table">
        <caption class="sr-only">Table caption</caption>
        <thead>
            <tr role="row">
                <th role="columnheader" scope="col">Column 1</th>
                <th role="columnheader" scope="col">Column 2</th>
            </tr>
        </thead>
        <tbody>
            <tr role="row">
                <td role="cell">Data 1</td>
                <td role="cell">Data 2</td>
            </tr>
        </tbody>
    </table>
    
    <!-- Dialog accessibility -->
    <div 
        role="dialog" 
        aria-modal="true" 
        aria-labelledby="dialog-title" 
        aria-describedby="dialog-description"
        class="dialog"
    >
        <h2 id="dialog-title">Dialog Title</h2>
        <p id="dialog-description">Dialog description</p>
        <slot name="dialog-content"></slot>
    </div>
    
    <!-- Tooltip accessibility -->
    <button 
        aria-describedby="tooltip-1" 
        class="tooltip-trigger"
    >
        Hover for tooltip
    </button>
    <div 
        id="tooltip-1" 
        role="tooltip" 
        class="tooltip sr-only"
    >
        Tooltip content
    </div>
    
    <!-- Menu accessibility -->
    <button 
        aria-expanded="false" 
        aria-haspopup="menu" 
        aria-controls="menu-1"
        class="menu-trigger"
    >
        Open menu
    </button>
    <ul 
        role="menu" 
        id="menu-1" 
        aria-label="Action menu"
        class="menu"
    >
        <li role="none">
            <button role="menuitem">Menu item 1</button>
        </li>
        <li role="none">
            <button role="menuitem">Menu item 2</button>
        </li>
    </ul>
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

/* Focus styles for accessibility */
.aria-labels-container *:focus {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}

/* Tab styles */
.tab-button {
    padding: 8px 16px;
    border: 1px solid #d1d5db;
    background: #f9fafb;
    cursor: pointer;
}

.tab-button[aria-selected="true"] {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}

.tab-button:focus {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}

/* Tab panel styles */
.tab-panel {
    padding: 16px;
    border: 1px solid #d1d5db;
    border-top: none;
}

/* Progress bar styles */
.progress-bar {
    width: 100%;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: #2563eb;
    transition: width 0.3s ease;
}

/* Error and success message styles */
.error-message {
    padding: 12px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 4px;
    color: #dc2626;
}

.success-message {
    padding: 12px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 4px;
    color: #16a34a;
}

/* Tooltip styles */
.tooltip-trigger {
    position: relative;
    padding: 8px 16px;
    background: #f3f4f6;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    cursor: help;
}

.tooltip-trigger:hover + .tooltip,
.tooltip-trigger:focus + .tooltip {
    position: absolute;
    top: 100%;
    left: 0;
    background: #1f2937;
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 14px;
    white-space: nowrap;
    z-index: 1000;
}

/* Menu styles */
.menu-trigger {
    padding: 8px 16px;
    background: #f3f4f6;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    cursor: pointer;
}

.menu {
    position: absolute;
    top: 100%;
    left: 0;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    list-style: none;
    padding: 0;
    margin: 0;
    min-width: 200px;
}

.menu li {
    margin: 0;
}

.menu button {
    width: 100%;
    padding: 8px 16px;
    text-align: left;
    background: none;
    border: none;
    cursor: pointer;
}

.menu button:hover,
.menu button:focus {
    background: #f3f4f6;
}

/* Dialog styles */
.dialog {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    padding: 24px;
    max-width: 500px;
    width: 90%;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .aria-labels-container *:focus {
        outline: 3px solid #000000;
        outline-offset: 2px;
    }
    
    .tab-button {
        border-width: 2px;
    }
    
    .error-message {
        border-width: 2px;
    }
    
    .success-message {
        border-width: 2px;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    .progress-fill {
        transition: none;
    }
    
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
</style>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/a11y/accessibility-aria-labels.blade.php ENDPATH**/ ?>