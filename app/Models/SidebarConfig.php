<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SidebarConfig extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'sidebar_configs';

    // ULID primary key configuration
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'role_name',
        'config',
        'is_enabled',
        'version',
        'updated_by',
    ];

    protected $casts = [
        'config' => 'array',
        'is_enabled' => 'boolean',
        'version' => 'integer',
    ];

    protected $attributes = [
        'is_enabled' => true,
        'version' => 1,
    ];

    /**
     * Valid role names
     */
    public const VALID_ROLES = [
        'super_admin',
        'admin',
        'project_manager',
        'designer',
        'site_engineer',
        'qc',
        'procurement',
        'finance',
        'client',
    ];

    /**
     * Get the tenant that owns the sidebar config.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Get the user who last updated this config.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to get configs for a specific role.
     */
    public function scopeForRole($query, string $roleName)
    {
        return $query->where('role_name', $roleName);
    }

    /**
     * Scope to get enabled configs only.
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope to get configs for a specific tenant.
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope to get global configs (no tenant).
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('tenant_id');
    }

    /**
     * Validate the config JSON structure.
     */
    public function validateConfig(): bool
    {
        $validator = Validator::make(['config' => $this->config], [
            'config' => 'required|array',
            'config.items' => 'required|array',
            'config.items.*.id' => 'required|string',
            'config.items.*.type' => 'required|in:group,link,external,divider',
            'config.items.*.label' => 'required|string',
            'config.items.*.enabled' => 'boolean',
            'config.items.*.order' => 'integer',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return true;
    }

    /**
     * Get the default config for a role.
     */
    public static function getDefaultForRole(string $roleName): array
    {
        return self::getDefaultConfigs()[$roleName] ?? [];
    }

    /**
     * Get all default configs.
     */
    public static function getDefaultConfigs(): array
    {
        return [
            'super_admin' => [
                'items' => [
                    [
                        'id' => 'dashboard',
                        'type' => 'link',
                        'label' => 'Dashboard',
                        'icon' => 'TachometerAlt',
                        'to' => '/dashboard',
                        'required_permissions' => [],
                        'enabled' => true,
                        'order' => 10,
                    ],
                    [
                        'id' => 'grp-projects',
                        'type' => 'group',
                        'label' => 'Projects',
                        'children' => [
                            [
                                'id' => 'design-projects',
                                'type' => 'link',
                                'label' => 'Design Projects',
                                'icon' => 'Pencil',
                                'to' => '/projects',
                                'query' => ['type' => 'design'],
                                'required_permissions' => ['project.read'],
                                'enabled' => true,
                                'order' => 10,
                            ],
                            [
                                'id' => 'construction-projects',
                                'type' => 'link',
                                'label' => 'Construction Projects',
                                'icon' => 'Crane',
                                'to' => '/projects',
                                'query' => ['type' => 'construction'],
                                'required_permissions' => ['project.read'],
                                'enabled' => true,
                                'order' => 20,
                            ],
                        ],
                        'enabled' => true,
                        'order' => 20,
                    ],
                    [
                        'id' => 'tasks',
                        'type' => 'link',
                        'label' => 'Tasks',
                        'icon' => 'ListChecks',
                        'to' => '/tasks',
                        'required_permissions' => ['task.read'],
                        'enabled' => true,
                        'order' => 30,
                    ],
                    [
                        'id' => 'sidebar-builder',
                        'type' => 'link',
                        'label' => 'Sidebar Builder',
                        'icon' => 'Bars',
                        'to' => '/admin/sidebar-builder',
                        'required_permissions' => ['admin.sidebar.manage'],
                        'enabled' => true,
                        'order' => 40,
                    ],
                ],
            ],
            'project_manager' => [
                'items' => [
                    [
                        'id' => 'dashboard',
                        'type' => 'link',
                        'label' => 'Dashboard',
                        'icon' => 'TachometerAlt',
                        'to' => '/dashboard',
                        'required_permissions' => [],
                        'enabled' => true,
                        'order' => 10,
                    ],
                    [
                        'id' => 'grp-projects',
                        'type' => 'group',
                        'label' => 'Projects',
                        'children' => [
                            [
                                'id' => 'design-projects',
                                'type' => 'link',
                                'label' => 'Design Projects',
                                'icon' => 'Pencil',
                                'to' => '/projects',
                                'query' => ['type' => 'design'],
                                'required_permissions' => ['project.read'],
                                'enabled' => true,
                                'order' => 10,
                            ],
                            [
                                'id' => 'construction-projects',
                                'type' => 'link',
                                'label' => 'Construction Projects',
                                'icon' => 'Crane',
                                'to' => '/projects',
                                'query' => ['type' => 'construction'],
                                'required_permissions' => ['project.read'],
                                'enabled' => true,
                                'order' => 20,
                            ],
                        ],
                        'enabled' => true,
                        'order' => 20,
                    ],
                    [
                        'id' => 'tasks',
                        'type' => 'link',
                        'label' => 'Tasks',
                        'icon' => 'ListChecks',
                        'to' => '/tasks',
                        'required_permissions' => ['task.read'],
                        'enabled' => true,
                        'order' => 30,
                    ],
                ],
            ],
            'designer' => [
                'items' => [
                    [
                        'id' => 'dashboard',
                        'type' => 'link',
                        'label' => 'Dashboard',
                        'icon' => 'TachometerAlt',
                        'to' => '/dashboard',
                        'required_permissions' => [],
                        'enabled' => true,
                        'order' => 10,
                    ],
                    [
                        'id' => 'design-projects',
                        'type' => 'link',
                        'label' => 'Design Projects',
                        'icon' => 'Pencil',
                        'to' => '/projects',
                        'query' => ['type' => 'design'],
                        'required_permissions' => ['project.read'],
                        'enabled' => true,
                        'order' => 20,
                    ],
                    [
                        'id' => 'tasks',
                        'type' => 'link',
                        'label' => 'Tasks',
                        'icon' => 'ListChecks',
                        'to' => '/tasks',
                        'required_permissions' => ['task.read'],
                        'enabled' => true,
                        'order' => 30,
                    ],
                ],
            ],
            'client' => [
                'items' => [
                    [
                        'id' => 'dashboard',
                        'type' => 'link',
                        'label' => 'Dashboard',
                        'icon' => 'TachometerAlt',
                        'to' => '/dashboard',
                        'required_permissions' => [],
                        'enabled' => true,
                        'order' => 10,
                    ],
                    [
                        'id' => 'shared-projects',
                        'type' => 'link',
                        'label' => 'Shared Projects',
                        'icon' => 'ProjectDiagram',
                        'to' => '/projects',
                        'required_permissions' => ['project.read'],
                        'enabled' => true,
                        'order' => 20,
                    ],
                ],
            ],
            'admin' => [
                'items' => [
                    [
                        'id' => 'dashboard',
                        'type' => 'link',
                        'label' => 'Dashboard',
                        'icon' => 'TachometerAlt',
                        'to' => '/dashboard',
                        'required_permissions' => [],
                        'enabled' => true,
                        'order' => 10,
                    ],
                    [
                        'id' => 'grp-projects',
                        'type' => 'group',
                        'label' => 'Projects',
                        'children' => [
                            [
                                'id' => 'all-projects',
                                'type' => 'link',
                                'label' => 'All Projects',
                                'icon' => 'Building',
                                'to' => '/projects',
                                'required_permissions' => ['project.read'],
                                'enabled' => true,
                                'order' => 10,
                            ],
                            [
                                'id' => 'project-reports',
                                'type' => 'link',
                                'label' => 'Project Reports',
                                'icon' => 'ChartBar',
                                'to' => '/reports/projects',
                                'required_permissions' => ['report.read'],
                                'enabled' => true,
                                'order' => 20,
                            ],
                        ],
                        'enabled' => true,
                        'order' => 20,
                    ],
                    [
                        'id' => 'grp-management',
                        'type' => 'group',
                        'label' => 'Management',
                        'children' => [
                            [
                                'id' => 'users',
                                'type' => 'link',
                                'label' => 'Users',
                                'icon' => 'Users',
                                'to' => '/admin/users',
                                'required_permissions' => ['user.read'],
                                'enabled' => true,
                                'order' => 10,
                            ],
                            [
                                'id' => 'roles',
                                'type' => 'link',
                                'label' => 'Roles & Permissions',
                                'icon' => 'Shield',
                                'to' => '/admin/roles',
                                'required_permissions' => ['role.read'],
                                'enabled' => true,
                                'order' => 20,
                            ],
                        ],
                        'enabled' => true,
                        'order' => 30,
                    ],
                ],
            ],
            'site_engineer' => [
                'items' => [
                    [
                        'id' => 'dashboard',
                        'type' => 'link',
                        'label' => 'Dashboard',
                        'icon' => 'TachometerAlt',
                        'to' => '/dashboard',
                        'required_permissions' => [],
                        'enabled' => true,
                        'order' => 10,
                    ],
                    [
                        'id' => 'construction-projects',
                        'type' => 'link',
                        'label' => 'Construction Projects',
                        'icon' => 'Crane',
                        'to' => '/projects',
                        'query' => ['type' => 'construction'],
                        'required_permissions' => ['project.read'],
                        'enabled' => true,
                        'order' => 20,
                    ],
                    [
                        'id' => 'site-tasks',
                        'type' => 'link',
                        'label' => 'Site Tasks',
                        'icon' => 'HardHat',
                        'to' => '/tasks',
                        'query' => ['type' => 'site'],
                        'required_permissions' => ['task.read'],
                        'enabled' => true,
                        'order' => 30,
                    ],
                    [
                        'id' => 'site-reports',
                        'type' => 'link',
                        'label' => 'Site Reports',
                        'icon' => 'ClipboardCheck',
                        'to' => '/reports/site',
                        'required_permissions' => ['report.read'],
                        'enabled' => true,
                        'order' => 40,
                    ],
                ],
            ],
            'qc_engineer' => [
                'items' => [
                    [
                        'id' => 'dashboard',
                        'type' => 'link',
                        'label' => 'Dashboard',
                        'icon' => 'TachometerAlt',
                        'to' => '/dashboard',
                        'required_permissions' => [],
                        'enabled' => true,
                        'order' => 10,
                    ],
                    [
                        'id' => 'qc-projects',
                        'type' => 'link',
                        'label' => 'QC Projects',
                        'icon' => 'CheckCircle',
                        'to' => '/projects',
                        'query' => ['type' => 'qc'],
                        'required_permissions' => ['project.read'],
                        'enabled' => true,
                        'order' => 20,
                    ],
                    [
                        'id' => 'qc-tasks',
                        'type' => 'link',
                        'label' => 'QC Tasks',
                        'icon' => 'ClipboardList',
                        'to' => '/tasks',
                        'query' => ['type' => 'qc'],
                        'required_permissions' => ['task.read'],
                        'enabled' => true,
                        'order' => 30,
                    ],
                    [
                        'id' => 'quality-reports',
                        'type' => 'link',
                        'label' => 'Quality Reports',
                        'icon' => 'ChartLine',
                        'to' => '/reports/quality',
                        'required_permissions' => ['report.read'],
                        'enabled' => true,
                        'order' => 40,
                    ],
                ],
            ],
            'procurement' => [
                'items' => [
                    [
                        'id' => 'dashboard',
                        'type' => 'link',
                        'label' => 'Dashboard',
                        'icon' => 'TachometerAlt',
                        'to' => '/dashboard',
                        'required_permissions' => [],
                        'enabled' => true,
                        'order' => 10,
                    ],
                    [
                        'id' => 'procurement-projects',
                        'type' => 'link',
                        'label' => 'Procurement Projects',
                        'icon' => 'ShoppingCart',
                        'to' => '/projects',
                        'query' => ['type' => 'procurement'],
                        'required_permissions' => ['project.read'],
                        'enabled' => true,
                        'order' => 20,
                    ],
                    [
                        'id' => 'suppliers',
                        'type' => 'link',
                        'label' => 'Suppliers',
                        'icon' => 'Truck',
                        'to' => '/suppliers',
                        'required_permissions' => ['supplier.read'],
                        'enabled' => true,
                        'order' => 30,
                    ],
                    [
                        'id' => 'purchase-orders',
                        'type' => 'link',
                        'label' => 'Purchase Orders',
                        'icon' => 'FileInvoice',
                        'to' => '/purchase-orders',
                        'required_permissions' => ['purchase_order.read'],
                        'enabled' => true,
                        'order' => 40,
                    ],
                    [
                        'id' => 'procurement-reports',
                        'type' => 'link',
                        'label' => 'Procurement Reports',
                        'icon' => 'ChartPie',
                        'to' => '/reports/procurement',
                        'required_permissions' => ['report.read'],
                        'enabled' => true,
                        'order' => 50,
                    ],
                ],
            ],
            'finance' => [
                'items' => [
                    [
                        'id' => 'dashboard',
                        'type' => 'link',
                        'label' => 'Dashboard',
                        'icon' => 'TachometerAlt',
                        'to' => '/dashboard',
                        'required_permissions' => [],
                        'enabled' => true,
                        'order' => 10,
                    ],
                    [
                        'id' => 'grp-finance',
                        'type' => 'group',
                        'label' => 'Finance',
                        'children' => [
                            [
                                'id' => 'budgets',
                                'type' => 'link',
                                'label' => 'Budgets',
                                'icon' => 'DollarSign',
                                'to' => '/budgets',
                                'required_permissions' => ['budget.read'],
                                'enabled' => true,
                                'order' => 10,
                            ],
                            [
                                'id' => 'invoices',
                                'type' => 'link',
                                'label' => 'Invoices',
                                'icon' => 'FileInvoice',
                                'to' => '/invoices',
                                'required_permissions' => ['invoice.read'],
                                'enabled' => true,
                                'order' => 20,
                            ],
                            [
                                'id' => 'payments',
                                'type' => 'link',
                                'label' => 'Payments',
                                'icon' => 'CreditCard',
                                'to' => '/payments',
                                'required_permissions' => ['payment.read'],
                                'enabled' => true,
                                'order' => 30,
                            ],
                        ],
                        'enabled' => true,
                        'order' => 20,
                    ],
                    [
                        'id' => 'grp-reports',
                        'type' => 'group',
                        'label' => 'Reports',
                        'children' => [
                            [
                                'id' => 'financial-reports',
                                'type' => 'link',
                                'label' => 'Financial Reports',
                                'icon' => 'ChartBar',
                                'to' => '/reports/financial',
                                'required_permissions' => ['report.read'],
                                'enabled' => true,
                                'order' => 10,
                            ],
                            [
                                'id' => 'project-costs',
                                'type' => 'link',
                                'label' => 'Project Costs',
                                'icon' => 'Calculator',
                                'to' => '/reports/project-costs',
                                'required_permissions' => ['report.read'],
                                'enabled' => true,
                                'order' => 20,
                            ],
                        ],
                        'enabled' => true,
                        'order' => 30,
                    ],
                ],
            ],
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->validateConfig();
        });

        static::updating(function ($model) {
            $model->validateConfig();
            $model->version = $model->version + 1;
        });
    }
}
