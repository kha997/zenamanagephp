# Kế Hoạch Deduplication Thực Tế - Baseline & Roadmap

## 📊 Baseline Metrics (Thực tế ngày hôm nay)

### File Counts (Excluding vendor/)
- **Blade files**: 360 files
- **Controllers**: 257 files  
- **Request files**: 110 files
- **Total**: 727 files

### Duplicate Analysis
- **Layout files**: 10+ files (app-layout, auth-layout, simple-layout, no-nav-layout, etc.)
- **Header files**: 3 files (admin/header, shared/header, shared/header-shell)
- **Dashboard files**: 28+ files (nhiều versions, archives, backups)
- **Project files**: Multiple versions scattered across directories

## 🎯 Kế Hoạch Thực Hiện Chi Tiết

### Phase 1: Header/Layout Consolidation (Tuần 1) - Ưu tiên cao

#### 1.1 Xác định HeaderShell.tsx làm nguồn duy nhất
```bash
# Kiểm tra React HeaderShell
find . -name "HeaderShell.tsx" -not -path "./vendor/*"
find . -name "*header*" -name "*.tsx" -not -path "./vendor/*"
```

#### 1.2 Tạo Blade wrapper cho HeaderShell
```blade
{{-- resources/views/components/shared/header-wrapper.blade.php --}}
<div id="header-shell-root"></div>
<script>
    // Mount React HeaderShell component
    ReactDOM.render(
        React.createElement(HeaderShell, {
            user: @json(Auth::user()),
            navigation: @json($navigation ?? []),
            notifications: @json($notifications ?? []),
            unreadCount: {{ $unreadCount ?? 0 }},
            theme: '{{ $theme ?? 'light' }}'
        }),
        document.getElementById('header-shell-root')
    );
</script>
```

#### 1.3 Thay thế các layout files
**Files cần thay thế**:
- `layouts/app-layout.blade.php` → sử dụng header-wrapper
- `layouts/auth-layout.blade.php` → sử dụng header-wrapper  
- `layouts/simple-layout.blade.php` → sử dụng header-wrapper
- `layouts/no-nav-layout.blade.php` → sử dụng header-wrapper

**Files cần xóa**:
- `components/shared/header.blade.php` (legacy)
- `components/admin/header.blade.php` (legacy)
- `components/shared/header-shell.blade.php` (Blade version)

#### 1.4 Di chuyển logic Alpine/mock data
```php
// Tạo HeaderService
class HeaderService
{
    public function getNavigation(User $user): array
    public function getNotifications(User $user): array
    public function getBreadcrumbs(string $route): array
}
```

#### 1.5 Test & Validation
- [ ] Test UI trên /app/* pages
- [ ] Test UI trên /admin/* pages  
- [ ] Verify RBAC (ẩn menu nếu không có quyền)
- [ ] Test responsive design
- [ ] Test dark mode toggle

### Phase 2: Dashboard/Projects UI Consolidation (Tuần 2)

#### 2.1 Chọn React + Vite làm nguồn duy nhất
```typescript
// resources/js/pages/app/dashboard.tsx
export default function Dashboard() {
    const [kpis, setKpis] = useState([]);
    const [charts, setCharts] = useState([]);
    const [activities, setActivities] = useState([]);
    
    useEffect(() => {
        fetchDashboardData();
    }, []);
    
    return (
        <div className="dashboard">
            <KPIWidget data={kpis} />
            <ChartWidget data={charts} />
            <ActivityList data={activities} />
        </div>
    );
}
```

#### 2.2 Tạo API endpoints thực
```php
// routes/api.php
Route::get('/dashboard/kpis', [DashboardController::class, 'getKPIs']);
Route::get('/dashboard/charts', [DashboardController::class, 'getCharts']);
Route::get('/dashboard/activities', [DashboardController::class, 'getActivities']);
```

#### 2.3 Component hóa UI elements
```typescript
// KPIWidget.tsx
export function KPIWidget({ data }: { data: KPIData[] }) {
    return (
        <div className="grid grid-cols-4 gap-6">
            {data.map(kpi => (
                <KPICard key={kpi.id} data={kpi} />
            ))}
        </div>
    );
}

// ChartWidget.tsx  
export function ChartWidget({ data }: { data: ChartData[] }) {
    return (
        <div className="grid grid-cols-2 gap-8">
            {data.map(chart => (
                <ChartCard key={chart.id} data={chart} />
            ))}
        </div>
    );
}
```

#### 2.4 Dọn các Blade dashboard files
**Files cần xóa/consolidate**:
- `app/dashboard.blade.php` (legacy)
- `app/dashboard-new.blade.php` (legacy)
- `admin/dashboard.blade.php` (legacy)
- `tenant/dashboard.blade.php` (legacy)
- `simple-dashboard.blade.php` (legacy)
- `test-dashboard.blade.php` (legacy)

**Chỉ giữ lại**:
- `layouts/app.blade.php` (render React dashboard)
- `layouts/admin.blade.php` (render React dashboard)

#### 2.5 Projects page unification
```typescript
// resources/js/pages/app/projects.tsx
export default function Projects() {
    return (
        <div className="projects">
            <ProjectFilters />
            <ProjectTable />
            <ProjectActions />
        </div>
    );
}
```

**Files cần consolidate**:
- `app/projects/index.blade.php` → React component
- `app/projects-new.blade.php` → React component

### Phase 3: Backend Controllers/Services Consolidation (Tuần 3)

#### 3.1 User Controllers consolidation
**Files cần consolidate**:
```bash
# Tìm tất cả User controllers
find . -name "*User*Controller.php" -not -path "./vendor/*"
```

**Kế hoạch**:
- Tạo `UserManagementController` duy nhất
- Sử dụng `UserManagementService`
- Middleware-based guard detection (app/admin)
- Policy-based authorization

#### 3.2 Project Controllers consolidation
**Files cần consolidate**:
```bash
# Tìm tất cả Project controllers  
find . -name "*Project*Controller.php" -not -path "./vendor/*"
```

**Kế hoạch**:
- Tạo `ProjectManagementController` duy nhất
- Sử dụng `ProjectManagementService`
- Consolidate với `src/CoreProject/Controllers/ProjectController`
- Deprecate và xóa `src/` nếu không dùng

#### 3.3 Services refactoring
```php
// Tạo base trait cho audit/event
trait AuditableTrait
{
    public function logActivity(string $action, array $data = []): void
    public function fireEvent(string $event, array $data = []): void
}

// UserManagementService sử dụng trait
class UserManagementService
{
    use AuditableTrait;
    
    public function createUser(array $data): User
    public function updateUser(User $user, array $data): User
    public function deleteUser(User $user): bool
}
```

### Phase 4: Validators/Requests Consolidation (Tuần 3)

#### 4.1 Project Requests consolidation
**Files cần consolidate**:
```bash
find . -name "*Project*Request.php" -not -path "./vendor/*"
```

**Kế hoạch**:
```php
// ProjectBaseRequest.php
abstract class ProjectBaseRequest extends FormRequest
{
    protected const STATUSES = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];
    protected const PRIORITIES = ['low', 'medium', 'high', 'critical'];
    
    protected function getBaseRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'status' => ['required', 'string', 'in:' . implode(',', self::STATUSES)],
            'priority' => ['required', 'string', 'in:' . implode(',', self::PRIORITIES)],
        ];
    }
}

// StoreProjectRequest.php
class StoreProjectRequest extends ProjectBaseRequest
{
    public function rules(): array
    {
        return array_merge($this->getBaseRules(), [
            'code' => ['required', 'string', 'unique:projects,code'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);
    }
}
```

#### 4.2 Task/User Requests consolidation
- Tương tự pattern cho Task*Request
- Tương tự pattern cho User*Request

### Phase 5: Rate Limit Middleware Consolidation (Tuần 3)

#### 5.1 Chọn AdvancedRateLimitMiddleware làm chuẩn
```php
// AdvancedRateLimitMiddleware.php (chuẩn)
class AdvancedRateLimitMiddleware
{
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1)
    {
        // Logic với penalty + logging
    }
}
```

#### 5.2 Consolidate config/logic từ các middleware khác
**Files cần consolidate**:
```bash
find . -name "*RateLimit*Middleware.php" -not -path "./vendor/*"
```

#### 5.3 Cập nhật Kernel.php và routes
```php
// Kernel.php
protected $middlewareAliases = [
    'rate.limit' => AdvancedRateLimitMiddleware::class,
    // Xóa các alias cũ
];
```

### Phase 6: Mock Data Cleanup (Tuần 1-2)

#### 6.1 Loại bỏ hardcoded notifications/alerts
```php
// Thay thế mock data bằng API calls
class NotificationService
{
    public function getUserNotifications(User $user): Collection
    public function getUnreadCount(User $user): int
}
```

#### 6.2 Kiểm tra API endpoints
**Endpoints cần tạo**:
- `/api/badges/{id}` → tạo route/service
- `/api/user-preferences/pin` → tạo route/service

**Hoặc bỏ tính năng** nếu không cần thiết

### Phase 7: CI/CD Setup (Tuần 1)

#### 7.1 Baseline duplicate detection
```bash
# Chạy jscpd để đo baseline
npx jscpd --min-lines 5 --min-tokens 50 --output reports/jscpd-baseline

# Chạy phpcpd để đo PHP duplicates
vendor/bin/phpcpd app/ --min-lines 5 --min-tokens 50
```

#### 7.2 CI Script setup
```yaml
# .github/workflows/deduplication-check.yml
name: Deduplication Check
on: [push, pull_request]
jobs:
  check-duplicates:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup Node.js
        uses: actions/setup-node@v3
      - name: Install dependencies
        run: npm install
      - name: Check JavaScript duplicates
        run: npx jscpd --min-lines 5 --min-tokens 50 --threshold 20
      - name: Check PHP duplicates  
        run: vendor/bin/phpcpd app/ --min-lines 5 --min-tokens 50
```

#### 7.3 ESLint SonarJS setup
```javascript
// .eslintrc.js
module.exports = {
  extends: ['plugin:sonarjs/recommended'],
  plugins: ['sonarjs'],
  rules: {
    'sonarjs/no-duplicate-string': ['error', { threshold: 3 }],
    'sonarjs/cognitive-complexity': ['error', 15],
  }
};
```

### Phase 8: Documentation (Tuần 4)

#### 8.1 Dedup Playbook
```markdown
# Deduplication Playbook

## Component Standards
- HeaderShell: React component với Blade wrapper
- KPIWidget: Reusable React component
- ActionButton: Standardized button component

## Usage Rules
1. Luôn sử dụng Shell components cho UI chính
2. Không tạo duplicate controllers cho cùng domain
3. Sử dụng base Request classes cho validation
4. Middleware phải có unified interface
```

#### 8.2 Migration Guide
- Step-by-step migration instructions
- Before/after examples
- Common pitfalls và solutions

## 📅 Timeline Thực Tế

### Tuần 1: Foundation
- [ ] Setup CI baseline (jscpd, phpcpd)
- [ ] HeaderShell.tsx → Blade wrapper
- [ ] Replace layout files
- [ ] Remove mock data
- [ ] Test header functionality

### Tuần 2: UI Consolidation  
- [ ] Dashboard React components
- [ ] Projects React components
- [ ] API endpoints cho real data
- [ ] Remove legacy Blade files
- [ ] Test UI functionality

### Tuần 3: Backend Consolidation
- [ ] Controllers consolidation
- [ ] Services refactoring
- [ ] Requests consolidation
- [ ] Middleware consolidation
- [ ] RBAC testing

### Tuần 4: Cleanup & Documentation
- [ ] Remove legacy modules
- [ ] E2E testing
- [ ] Documentation updates
- [ ] Final metrics measurement
- [ ] Production readiness check

## 📊 Success Metrics

### Target Reductions
- **Blade files**: 360 → 200 files (44% reduction)
- **Controllers**: 257 → 150 files (42% reduction)  
- **Request files**: 110 → 60 files (45% reduction)
- **Duplicate clones**: <20 (from current 60+)

### Quality Metrics
- **Test coverage**: >80% cho Shell components
- **Performance**: <500ms page load time
- **Bundle size**: <2MB total
- **CI pass rate**: 100%

## 🚨 Risk Mitigation

### Technical Risks
- **Breaking changes**: Gradual migration với feature flags
- **Performance impact**: Lazy loading và code splitting
- **Browser compatibility**: Polyfills và fallbacks

### Process Risks  
- **Team adoption**: Training sessions và documentation
- **Timeline delays**: Phased approach với rollback plans
- **Quality regression**: Comprehensive testing strategy

## ✅ Acceptance Criteria

### Phase 1 Complete
- [ ] HeaderShell.tsx là nguồn duy nhất
- [ ] Tất cả layouts sử dụng header wrapper
- [ ] Legacy header files đã xóa
- [ ] RBAC và responsive test pass

### Phase 2 Complete
- [ ] Dashboard React components hoạt động
- [ ] Projects React components hoạt động  
- [ ] API endpoints trả về real data
- [ ] Legacy Blade files đã xóa

### Phase 3 Complete
- [ ] Controllers consolidated
- [ ] Services sử dụng base traits
- [ ] Requests sử dụng base classes
- [ ] Middleware unified

### Phase 4 Complete
- [ ] CI checks pass
- [ ] Documentation complete
- [ ] E2E tests pass
- [ ] Metrics measured và verified

**Kế hoạch này dựa trên hiện trạng thực tế và có thể thực hiện được với timeline hợp lý.**
