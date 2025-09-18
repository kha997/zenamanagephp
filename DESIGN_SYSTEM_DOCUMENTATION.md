# ZenaManage Design System Documentation

## 🎨 **Overview**

ZenaManage Design System là một hệ thống thiết kế thống nhất được xây dựng với Tailwind CSS và CSS Custom Properties, cung cấp các components và utilities để tạo ra giao diện người dùng nhất quán và hiện đại.

## 🚀 **Quick Start**

### Installation

```html
<!-- Include Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- Include Design System CSS -->
<link rel="stylesheet" href="{{ asset('css/design-system.css') }}">

<!-- Include Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<!-- Include Inter Font -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
```

### Basic Usage

```html
<!-- Card Component -->
<div class="zena-card zena-p-lg">
    <h3 class="text-lg font-semibold text-gray-900">Card Title</h3>
    <p class="text-gray-600">Card content goes here...</p>
</div>

<!-- Button Component -->
<button class="zena-btn zena-btn-primary">
    <i class="fas fa-plus mr-2"></i>
    Create New
</button>
```

## 🎯 **Design Tokens**

### Colors

```css
/* Primary Colors */
--primary-blue: #3B82F6;
--primary-blue-dark: #2563EB;
--primary-blue-light: #DBEAFE;

/* Secondary Colors */
--secondary-green: #10B981;
--secondary-green-dark: #059669;
--secondary-green-light: #D1FAE5;

/* Accent Colors */
--accent-purple: #8B5CF6;
--accent-purple-dark: #7C3AED;
--accent-purple-light: #EDE9FE;

/* Status Colors */
--warning-orange: #F59E0B;
--danger-red: #EF4444;
--neutral-gray: #6B7280;
```

### Typography

```css
/* Font Families */
--font-family-primary: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
--font-family-mono: 'JetBrains Mono', 'Fira Code', Consolas, monospace;

/* Font Sizes */
.text-xs { font-size: 0.75rem; line-height: 1rem; }
.text-sm { font-size: 0.875rem; line-height: 1.25rem; }
.text-base { font-size: 1rem; line-height: 1.5rem; }
.text-lg { font-size: 1.125rem; line-height: 1.75rem; }
.text-xl { font-size: 1.25rem; line-height: 1.75rem; }
.text-2xl { font-size: 1.5rem; line-height: 2rem; }
.text-3xl { font-size: 1.875rem; line-height: 2.25rem; }
.text-4xl { font-size: 2.25rem; line-height: 2.5rem; }
```

### Spacing

```css
/* Spacing Scale */
--spacing-xs: 0.25rem;   /* 4px */
--spacing-sm: 0.5rem;    /* 8px */
--spacing-md: 1rem;      /* 16px */
--spacing-lg: 1.5rem;    /* 24px */
--spacing-xl: 2rem;      /* 32px */
--spacing-2xl: 3rem;     /* 48px */
```

### Border Radius

```css
/* Border Radius Scale */
--radius-sm: 0.375rem;   /* 6px */
--radius-md: 0.5rem;     /* 8px */
--radius-lg: 0.75rem;    /* 12px */
--radius-xl: 1rem;       /* 16px */
```

## 🧩 **Components**

### Cards

#### Basic Card

```html
<div class="zena-card zena-p-lg">
    <h3 class="text-lg font-semibold text-gray-900">Card Title</h3>
    <p class="text-gray-600">Card content goes here...</p>
</div>
```

#### Interactive Card

```html
<div class="zena-card zena-card-interactive zena-p-lg">
    <h3 class="text-lg font-semibold text-gray-900">Clickable Card</h3>
    <p class="text-gray-600">This card has hover effects</p>
</div>
```

#### Metric Card

```html
<div class="zena-metric-card green zena-p-lg">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-white/80 text-sm">Total Projects</p>
            <p class="text-3xl font-bold text-white">24</p>
            <p class="text-white/80 text-sm">+3 this week</p>
        </div>
        <i class="fas fa-project-diagram text-4xl text-white/60"></i>
    </div>
</div>
```

### Buttons

#### Button Variants

```html
<!-- Primary Button -->
<button class="zena-btn zena-btn-primary">
    <i class="fas fa-plus mr-2"></i>
    Primary Action
</button>

<!-- Secondary Button -->
<button class="zena-btn zena-btn-secondary">
    <i class="fas fa-save mr-2"></i>
    Secondary Action
</button>

<!-- Outline Button -->
<button class="zena-btn zena-btn-outline">
    <i class="fas fa-edit mr-2"></i>
    Outline Action
</button>

<!-- Ghost Button -->
<button class="zena-btn zena-btn-ghost">
    <i class="fas fa-cancel mr-2"></i>
    Ghost Action
</button>

<!-- Danger Button -->
<button class="zena-btn zena-btn-danger">
    <i class="fas fa-trash mr-2"></i>
    Delete
</button>
```

#### Button Sizes

```html
<!-- Small Button -->
<button class="zena-btn zena-btn-primary zena-btn-sm">Small</button>

<!-- Default Button -->
<button class="zena-btn zena-btn-primary">Default</button>

<!-- Large Button -->
<button class="zena-btn zena-btn-primary zena-btn-lg">Large</button>

<!-- Extra Large Button -->
<button class="zena-btn zena-btn-primary zena-btn-xl">Extra Large</button>
```

### Form Elements

#### Input Fields

```html
<div class="mb-4">
    <label class="block text-sm font-medium text-gray-700 mb-2">Project Name</label>
    <input 
        type="text" 
        class="zena-input"
        placeholder="Enter project name"
    >
</div>
```

#### Select Dropdowns

```html
<div class="mb-4">
    <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
    <select class="zena-select">
        <option value="">Select priority</option>
        <option value="low">Low</option>
        <option value="medium">Medium</option>
        <option value="high">High</option>
        <option value="urgent">Urgent</option>
    </select>
</div>
```

#### Textarea

```html
<div class="mb-4">
    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
    <textarea 
        class="zena-textarea"
        placeholder="Enter description..."
    ></textarea>
</div>
```

### Status Badges

```html
<!-- Success Badge -->
<span class="zena-badge zena-badge-success">Completed</span>

<!-- Warning Badge -->
<span class="zena-badge zena-badge-warning">Pending</span>

<!-- Danger Badge -->
<span class="zena-badge zena-badge-danger">Overdue</span>

<!-- Info Badge -->
<span class="zena-badge zena-badge-info">In Progress</span>

<!-- Neutral Badge -->
<span class="zena-badge zena-badge-neutral">Draft</span>
```

### Progress Bars

```html
<!-- Basic Progress Bar -->
<div class="zena-progress">
    <div class="zena-progress-bar zena-progress-bar-success" style="width: 75%"></div>
</div>

<!-- With Label -->
<div class="mb-2">
    <div class="flex justify-between items-center mb-1">
        <span class="text-sm text-gray-600">Progress</span>
        <span class="text-sm font-medium text-gray-900">75%</span>
    </div>
    <div class="zena-progress">
        <div class="zena-progress-bar zena-progress-bar-success" style="width: 75%"></div>
    </div>
</div>
```

### Navigation

```html
<!-- Navigation Item -->
<a href="/dashboard" class="zena-nav-item">
    <i class="fas fa-home mr-2"></i>
    Dashboard
</a>

<!-- Active Navigation Item -->
<a href="/projects" class="zena-nav-item active">
    <i class="fas fa-project-diagram mr-2"></i>
    Projects
</a>
```

### Tables

```html
<div class="zena-table">
    <table class="min-w-full">
        <thead>
            <tr>
                <th class="zena-table th">Name</th>
                <th class="zena-table th">Status</th>
                <th class="zena-table th">Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="zena-table td">Project Alpha</td>
                <td class="zena-table td">
                    <span class="zena-badge zena-badge-success">Active</span>
                </td>
                <td class="zena-table td">
                    <button class="zena-btn zena-btn-ghost zena-btn-sm">Edit</button>
                </td>
            </tr>
        </tbody>
    </table>
</div>
```

### Modals

```html
<!-- Modal Overlay -->
<div class="zena-modal-overlay">
    <div class="zena-modal">
        <div class="zena-modal-header">
            <h2 class="text-xl font-semibold text-gray-900">Modal Title</h2>
            <button class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="zena-modal-body">
            <p class="text-gray-600">Modal content goes here...</p>
        </div>
        
        <div class="zena-modal-footer">
            <button class="zena-btn zena-btn-outline">Cancel</button>
            <button class="zena-btn zena-btn-primary">Save</button>
        </div>
    </div>
</div>
```

### Alerts

```html
<!-- Success Alert -->
<div class="zena-alert zena-alert-success">
    <i class="fas fa-check-circle"></i>
    <div>
        <h4 class="font-medium">Success!</h4>
        <p class="text-sm">Your action was completed successfully.</p>
    </div>
</div>

<!-- Warning Alert -->
<div class="zena-alert zena-alert-warning">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <h4 class="font-medium">Warning!</h4>
        <p class="text-sm">Please review your input before proceeding.</p>
    </div>
</div>

<!-- Danger Alert -->
<div class="zena-alert zena-alert-danger">
    <i class="fas fa-times-circle"></i>
    <div>
        <h4 class="font-medium">Error!</h4>
        <p class="text-sm">Something went wrong. Please try again.</p>
    </div>
</div>

<!-- Info Alert -->
<div class="zena-alert zena-alert-info">
    <i class="fas fa-info-circle"></i>
    <div>
        <h4 class="font-medium">Information</h4>
        <p class="text-sm">Here's some helpful information for you.</p>
    </div>
</div>
```

## 🎨 **Utility Classes**

### Layout Utilities

```html
<!-- Flexbox Utilities -->
<div class="zena-flex zena-items-center zena-justify-between">
    <span>Left content</span>
    <span>Right content</span>
</div>

<!-- Spacing Utilities -->
<div class="zena-p-lg zena-m-md">
    <p class="zena-mb-sm">Content with margin bottom</p>
    <p class="zena-mt-lg">Content with margin top</p>
</div>

<!-- Gap Utilities -->
<div class="zena-flex zena-gap-md">
    <button class="zena-btn zena-btn-primary">Button 1</button>
    <button class="zena-btn zena-btn-secondary">Button 2</button>
</div>
```

### Text Utilities

```html
<!-- Text Alignment -->
<p class="zena-text-center">Centered text</p>
<p class="zena-text-left">Left aligned text</p>
<p class="zena-text-right">Right aligned text</p>

<!-- Display Utilities -->
<div class="zena-hidden">Hidden element</div>
<div class="zena-block">Block element</div>
<div class="zena-inline-block">Inline block element</div>
```

### Animation Utilities

```html
<!-- Fade In Animation -->
<div class="zena-fade-in">
    <p>This element fades in</p>
</div>

<!-- Slide In Animation -->
<div class="zena-slide-in">
    <p>This element slides in from left</p>
</div>
```

## 🌙 **Dark Mode Support**

The design system includes built-in dark mode support using CSS media queries:

```css
@media (prefers-color-scheme: dark) {
    :root {
        --neutral-gray-light: #1F2937;
        --neutral-gray-dark: #F9FAFB;
    }
    
    body {
        background-color: #111827;
        color: #F9FAFB;
    }
    
    .zena-card {
        background-color: #1F2937;
        border-color: #374151;
    }
}
```

## 📱 **Responsive Design**

The design system is mobile-first and includes responsive utilities:

```html
<!-- Responsive Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <div class="zena-card zena-p-lg">Card 1</div>
    <div class="zena-card zena-p-lg">Card 2</div>
    <div class="zena-card zena-p-lg">Card 3</div>
</div>

<!-- Responsive Buttons -->
<button class="zena-btn zena-btn-primary w-full md:w-auto">
    Responsive Button
</button>
```

## 🎯 **Best Practices**

### 1. Consistent Spacing

```html
<!-- Good: Use design system spacing -->
<div class="zena-p-lg zena-mb-md">
    <h3 class="zena-mb-sm">Title</h3>
    <p class="zena-mb-lg">Content</p>
</div>

<!-- Avoid: Custom spacing -->
<div class="p-8 mb-6">
    <h3 class="mb-3">Title</h3>
    <p class="mb-4">Content</p>
</div>
```

### 2. Semantic Color Usage

```html
<!-- Good: Use semantic color classes -->
<span class="zena-badge zena-badge-success">Completed</span>
<span class="zena-badge zena-badge-warning">Pending</span>
<span class="zena-badge zena-badge-danger">Overdue</span>

<!-- Avoid: Custom colors -->
<span class="bg-green-100 text-green-800">Completed</span>
```

### 3. Component Composition

```html
<!-- Good: Compose components -->
<div class="zena-card zena-p-lg">
    <h3 class="text-lg font-semibold text-gray-900 zena-mb-sm">Title</h3>
    <p class="text-gray-600 zena-mb-md">Description</p>
    <div class="zena-flex zena-gap-sm">
        <button class="zena-btn zena-btn-primary">Save</button>
        <button class="zena-btn zena-btn-outline">Cancel</button>
    </div>
</div>
```

### 4. Accessibility

```html
<!-- Good: Include proper labels and ARIA attributes -->
<button class="zena-btn zena-btn-primary" aria-label="Create new project">
    <i class="fas fa-plus mr-2"></i>
    Create Project
</button>

<!-- Good: Use semantic HTML -->
<nav class="zena-nav">
    <a href="/dashboard" class="zena-nav-item" aria-current="page">Dashboard</a>
</nav>
```

## 🔧 **Customization**

### Custom Color Scheme

```css
:root {
    /* Override default colors */
    --primary-blue: #your-color;
    --secondary-green: #your-color;
    --accent-purple: #your-color;
}
```

### Custom Components

```css
/* Create custom components following the design system patterns */
.your-custom-component {
    background: white;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
    transition: all var(--transition-normal);
}

.your-custom-component:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-2px);
}
```

## 📚 **Examples**

### Complete Dashboard Layout

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/design-system.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="zena-nav">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
                        <p class="text-gray-600 mt-1">Welcome back!</p>
                    </div>
                    <button class="zena-btn zena-btn-primary">
                        <i class="fas fa-sync-alt mr-2"></i>
                        Refresh
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="zena-metric-card green zena-p-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Total Projects</p>
                            <p class="text-3xl font-bold text-white">24</p>
                        </div>
                        <i class="fas fa-project-diagram text-4xl text-white/60"></i>
                    </div>
                </div>
                <!-- More metric cards... -->
            </div>

            <!-- Content Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="zena-card zena-p-lg">
                    <h3 class="text-lg font-semibold text-gray-900 zena-mb-md">Recent Projects</h3>
                    <div class="space-y-3">
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <h4 class="font-medium text-gray-900">Project Alpha</h4>
                            <p class="text-sm text-gray-600">In progress</p>
                        </div>
                    </div>
                </div>
                <!-- More content cards... -->
            </div>
        </main>
    </div>
</body>
</html>
```

## 🚀 **Getting Started**

1. **Include the CSS**: Add the design system CSS to your project
2. **Use Components**: Start with basic components like cards and buttons
3. **Follow Patterns**: Use the established patterns for consistency
4. **Customize**: Override CSS variables for your brand colors
5. **Test**: Ensure your implementation works across different screen sizes

## 📞 **Support**

For questions or issues with the design system:

- Check this documentation first
- Review the component examples
- Test in different browsers and devices
- Follow the best practices outlined above

---

**ZenaManage Design System** - Building consistent, beautiful, and accessible user interfaces.
