# Projects Page - Chi tiết thiết kế và chức năng

## 🎯 **Trang: http://localhost:8000/app/projects**

### **1. PAGE HEADER (Header Section)**
```
┌─────────────────────────────────────────────────────────────┐
│ Projects                                    [Filters] [New Project] │
│ Manage and track your projects                               │
└─────────────────────────────────────────────────────────────┘
```

**Thông tin:**
- **Title**: "Projects" (text-2xl font-bold text-gray-900)
- **Description**: "Manage and track your projects" (text-sm text-gray-500)
- **Action Buttons**: 
  - Filters button (border-gray-300)
  - New Project button (bg-blue-600)

### **2. SEARCH & FILTERS SECTION**
```
┌─────────────────────────────────────────────────────────────┐
│ [🔍 Search projects...]                                    │
│ ────────────────────────────────────────────────────────── │
│ Status: [All Statuses ▼] Priority: [All Priorities ▼] Team: [All Teams ▼] │
│ [Active] [High Priority] [Design Team] [Clear all]         │
└─────────────────────────────────────────────────────────────┘
```

**Thông tin:**
- **Search Bar**: 
  - Placeholder: "Search projects..."
  - Icon: Font Awesome search icon
  - Real-time search với debouncing
- **Filter Dropdowns**:
  - Status: All, Active, Planning, On Hold, Completed, Cancelled
  - Priority: All, High, Medium, Low
  - Team: All, Design Team, Development Team, Marketing Team
- **Active Filter Tags**: Hiển thị filters đã chọn với nút X để remove
- **Clear All**: Xóa tất cả filters

### **3. PROJECTS GRID (Main Content)**
```
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ 🏗️ Website     │ │ 📱 Mobile App   │ │ 📊 Marketing    │
│ Redesign        │ │ Development     │ │ Campaign        │
│ Complete redesign│ │ Development of  │ │ Q1 marketing    │
│ of company      │ │ iOS and Android │ │ campaign for new │
│ website with    │ │ mobile app      │ │ product launch  │
│ modern UI/UX    │ │                 │ │                 │
│                 │ │                 │ │                 │
│ Tasks: 15/20    │ │ Tasks: 5/20     │ │ Tasks: 12/20    │
│ Progress: 75%   │ │ Progress: 25%   │ │ Progress: 60%   │
│ ████████░░      │ │ ███░░░░░░░      │ │ ██████░░░░      │
│                 │ │                 │ │                 │
│ 📅 Due: Jan 15  │ │ 📅 Due: Feb 28  │ │ 📅 Due: Jan 30  │
│ 👥 5 members    │ │ 👥 8 members    │ │ 👥 4 members    │
│                 │ │                 │ │                 │
│ [Edit] [View]   │ │ [Edit] [View]   │ │ [Edit] [View]   │
└─────────────────┘ └─────────────────┘ └─────────────────┘
```

**Thông tin mỗi Project Card:**

#### **A. Project Header**
- **Project Icon**: Font Awesome icon (fas fa-project-diagram)
- **Project Name**: text-lg font-semibold text-gray-900
- **Team Name**: text-sm text-gray-500
- **Status Badge**: 
  - Active: bg-green-100 text-green-800
  - Planning: bg-blue-100 text-blue-800
  - On Hold: bg-yellow-100 text-yellow-800
  - Completed: bg-gray-100 text-gray-800
  - Cancelled: bg-red-100 text-red-800

#### **B. Project Description**
- **Description**: text-gray-600 text-sm line-clamp-2
- **Truncated**: Hiển thị 2 dòng đầu, có ellipsis

#### **C. Project Stats**
- **Tasks Completed**: text-2xl font-bold text-blue-600
- **Progress Percentage**: text-2xl font-bold text-green-600
- **Progress Bar**: 
  - Background: bg-gray-200
  - Fill: bg-gradient-to-r from-blue-500 to-green-500
  - Height: h-2

#### **D. Project Footer**
- **Due Date**: text-sm text-gray-500 với calendar icon
- **Members Count**: text-sm text-gray-500 với users icon

#### **E. Action Buttons**
- **Edit Button**: bg-gray-100 text-gray-700 hover:bg-gray-200
- **View Button**: bg-blue-600 text-white hover:bg-blue-700

### **4. EMPTY STATE (Khi không có projects)**
```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│                    🏗️                                      │
│                                                             │
│              No projects found                              │
│        Get started by creating your first project.         │
│                                                             │
│              [Create New Project]                           │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Thông tin:**
- **Icon**: fas fa-project-diagram text-6xl text-gray-300
- **Title**: "No projects found" (text-lg font-medium text-gray-900)
- **Description**: "Get started by creating your first project." (text-gray-500)
- **CTA Button**: "Create New Project" (bg-blue-600 text-white)

### **5. LOADING STATE**
```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│              [🔄] Loading projects...                      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Thông tin:**
- **Spinner**: animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600
- **Text**: "Loading projects..." (text-gray-600)

### **6. ERROR STATE**
```
┌─────────────────────────────────────────────────────────────┐
│ ⚠️  Error loading projects                                  │
│ HTTP error! status: 500                                     │
│                    [Retry]                                  │
└─────────────────────────────────────────────────────────────┘
```

**Thông tin:**
- **Icon**: fas fa-exclamation-circle
- **Title**: "Error loading projects" (font-bold)
- **Error Message**: Chi tiết lỗi
- **Retry Button**: bg-red-500 text-white hover:bg-red-600

### **7. PAGINATION (Nếu có nhiều projects)**
```
┌─────────────────────────────────────────────────────────────┐
│ Showing 1 to 12 of 25 projects              [Previous] [Next] │
└─────────────────────────────────────────────────────────────┘
```

**Thông tin:**
- **Info Text**: "Showing X to Y of Z projects"
- **Previous Button**: Disabled khi ở trang đầu
- **Next Button**: Disabled khi ở trang cuối

## 🔧 **CHỨC NĂNG CHI TIẾT:**

### **1. Search Functionality**
```javascript
// Real-time search với debouncing
debounceSearch() {
    clearTimeout(this.searchTimeout);
    this.searchTimeout = setTimeout(() => {
        this.applyFilters();
    }, 300);
}

// Search trong các trường:
- project.name
- project.description  
- project.team
- project.tags
```

### **2. Filter Functionality**
```javascript
// Status Filter
if (this.filters.status) {
    filtered = filtered.filter(project => project.status === this.filters.status);
}

// Priority Filter  
if (this.filters.priority) {
    filtered = filtered.filter(project => project.priority === this.filters.priority);
}

// Team Filter
if (this.filters.team) {
    filtered = filtered.filter(project => project.team.toLowerCase().includes(this.filters.team.toLowerCase()));
}
```

### **3. Project Card Interactions**
```javascript
// Click để view project detail
viewProject(projectId) {
    window.location.href = `/app/projects/${projectId}`;
}

// Click để edit project
editProject(projectId) {
    window.location.href = `/app/projects/${projectId}/edit`;
}

// Click để create new project
createProject() {
    window.location.href = '/app/projects/create';
}
```

### **4. Responsive Behavior**
```css
/* Mobile (sm) */
grid-cols-1

/* Tablet (md) */
md:grid-cols-2

/* Desktop (lg) */
lg:grid-cols-3

/* Large Desktop (xl) */
xl:grid-cols-4
```

## 📊 **DATA STRUCTURE:**

### **Project Object**
```javascript
{
    id: 1,
    name: 'Website Redesign',
    description: 'Complete redesign of the company website with modern UI/UX',
    status: 'active', // active, planning, on-hold, completed, cancelled
    priority: 'high', // high, medium, low
    team: 'Design Team',
    progress: 75, // percentage
    tasks_completed: 15,
    total_tasks: 20,
    due_date: '2024-01-15',
    members_count: 5,
    created_at: '2024-01-01',
    updated_at: '2024-01-10',
    tags: ['ui', 'ux', 'frontend'],
    budget: 50000,
    client: 'Acme Corp'
}
```

## 🎨 **VISUAL DESIGN:**

### **Color Scheme**
```css
/* Status Colors */
.status-active { @apply bg-green-100 text-green-800 border-green-200; }
.status-planning { @apply bg-blue-100 text-blue-800 border-blue-200; }
.status-on-hold { @apply bg-yellow-100 text-yellow-800 border-yellow-200; }
.status-completed { @apply bg-gray-100 text-gray-800 border-gray-200; }
.status-cancelled { @apply bg-red-100 text-red-800 border-red-200; }

/* Priority Indicators */
.priority-high { @apply border-l-4 border-red-500; }
.priority-medium { @apply border-l-4 border-yellow-500; }
.priority-low { @apply border-l-4 border-green-500; }
```

### **Animations**
```css
/* Card Hover Effect */
.project-card:hover {
    transform: translateY(-4px) scale(1.02);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

/* Progress Bar Animation */
.progress-bar {
    transition: width 0.3s ease-in-out;
}
```

## 🚀 **PERFORMANCE OPTIMIZATIONS:**

### **1. Lazy Loading**
- Load projects theo batch (12 items per page)
- Infinite scroll hoặc pagination

### **2. Debounced Search**
- 300ms delay để tránh quá nhiều API calls
- Clear timeout khi user tiếp tục typing

### **3. Caching**
- Cache search results
- Cache filter options
- Invalidate cache khi có updates

## 📱 **MOBILE OPTIMIZATION:**

### **Touch-Friendly Design**
```css
/* Touch targets */
.touch-target {
    min-height: 44px;
    min-width: 44px;
}

/* Swipe gestures */
.swipe-container {
    touch-action: pan-x;
    overflow-x: auto;
}
```

### **Mobile Layout**
- Single column layout trên mobile
- Larger touch targets
- Simplified navigation
- Optimized images và icons

## 🔐 **SECURITY CONSIDERATIONS:**

### **1. Input Validation**
- Sanitize search queries
- Validate filter parameters
- Prevent XSS attacks

### **2. Authorization**
- Check user permissions cho mỗi project
- Hide sensitive information
- Validate project access

### **3. Rate Limiting**
- Limit search requests
- Limit filter requests
- Implement request throttling

## 📈 **ANALYTICS & TRACKING:**

### **User Interactions**
- Track search queries
- Track filter usage
- Track project views
- Track action button clicks

### **Performance Metrics**
- Page load time
- Search response time
- Filter response time
- User engagement metrics

## 🎯 **SUCCESS CRITERIA:**

### **User Experience**
- ✅ Easy to find projects
- ✅ Quick search và filtering
- ✅ Clear project status
- ✅ Intuitive navigation
- ✅ Responsive design

### **Performance**
- ✅ Fast page load (< 2s)
- ✅ Smooth animations
- ✅ Efficient search
- ✅ Optimized images

### **Functionality**
- ✅ All CRUD operations
- ✅ Real-time updates
- ✅ Error handling
- ✅ Offline support
