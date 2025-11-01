<?php declare(strict_types=1);

namespace App\Services;

use App\Models\SidebarConfig;
use Illuminate\Support\Facades\Auth;

class PresetService
{
    /**
     * Available presets.
     */
    public const PRESETS = [
        'pm_preset' => 'Project Manager Preset',
        'site_preset' => 'Site Engineer Preset',
        'finance_preset' => 'Finance Preset',
        'designer_preset' => 'Designer Preset',
        'qc_preset' => 'QC Preset',
        'procurement_preset' => 'Procurement Preset',
        'client_preset' => 'Client Preset',
        'admin_preset' => 'Admin Preset',
    ];

    /**
     * Get all available presets.
     */
    public function getAvailablePresets(): array
    {
        return self::PRESETS;
    }

    /**
     * Apply a preset to a role.
     */
    public function applyPreset(string $presetName, string $roleName, ?string $tenantId = null): array
    {
        $presetConfig = $this->getPresetConfig($presetName);
        
        if (!$presetConfig) {
            throw new \InvalidArgumentException("Preset '{$presetName}' not found");
        }

        // Check if config already exists
        $existingConfig = SidebarConfig::forRole($roleName)
            ->when($tenantId, function ($query) use ($tenantId) {
                return $query->forTenant($tenantId);
            }, function ($query) {
                return $query->global();
            })
            ->first();

        if ($existingConfig) {
            // Update existing config
            $existingConfig->update([
                'config' => $presetConfig,
                'updated_by' => Auth::id(),
            ]);
            
            return [
                'success' => true,
                'message' => "Preset '{$presetName}' applied to {$roleName} successfully",
                'data' => $existingConfig->load(['tenant', 'updater']),
            ];
        } else {
            // Create new config
            $config = SidebarConfig::create([
                'role_name' => $roleName,
                'config' => $presetConfig,
                'tenant_id' => $tenantId,
                'is_enabled' => true,
                'version' => 1,
                'updated_by' => Auth::id(),
            ]);

            return [
                'success' => true,
                'message' => "Preset '{$presetName}' applied to {$roleName} successfully",
                'data' => $config->load(['tenant', 'updater']),
            ];
        }
    }

    /**
     * Get preset configuration.
     */
    protected function getPresetConfig(string $presetName): ?array
    {
        $presets = [
            'pm_preset' => $this->getProjectManagerPreset(),
            'site_preset' => $this->getSiteEngineerPreset(),
            'finance_preset' => $this->getFinancePreset(),
            'designer_preset' => $this->getDesignerPreset(),
            'qc_preset' => $this->getQCPreset(),
            'procurement_preset' => $this->getProcurementPreset(),
            'client_preset' => $this->getClientPreset(),
            'admin_preset' => $this->getAdminPreset(),
        ];

        return $presets[$presetName] ?? null;
    }

    /**
     * Project Manager Preset.
     */
    protected function getProjectManagerPreset(): array
    {
        return [
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
                    'icon' => 'Building',
                    'required_permissions' => ['project.read'],
                    'enabled' => true,
                    'order' => 20,
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
                ],
                [
                    'id' => 'tasks',
                    'type' => 'link',
                    'label' => 'Tasks',
                    'icon' => 'ListChecks',
                    'to' => '/tasks',
                    'required_permissions' => ['task.read'],
                    'show_badge_from' => '/api/metrics/tasks?status=pending',
                    'enabled' => true,
                    'order' => 30,
                ],
                [
                    'id' => 'team',
                    'type' => 'link',
                    'label' => 'Team',
                    'icon' => 'Users',
                    'to' => '/team',
                    'required_permissions' => ['user.read'],
                    'enabled' => true,
                    'order' => 40,
                ],
                [
                    'id' => 'grp-collab',
                    'type' => 'group',
                    'label' => 'Collaboration',
                    'icon' => 'Handshake',
                    'required_permissions' => [],
                    'enabled' => true,
                    'order' => 50,
                    'children' => [
                        [
                            'id' => 'rfis',
                            'type' => 'link',
                            'label' => 'RFIs',
                            'icon' => 'QuestionCircle',
                            'to' => '/rfis',
                            'required_permissions' => ['rfi.read'],
                            'show_badge_from' => '/api/metrics/rfis?status=pending',
                            'enabled' => true,
                            'order' => 10,
                        ],
                        [
                            'id' => 'submittals',
                            'type' => 'link',
                            'label' => 'Submittals',
                            'icon' => 'FileAlt',
                            'to' => '/submittals',
                            'required_permissions' => ['submittal.read'],
                            'show_badge_from' => '/api/metrics/submittals?status=pending',
                            'enabled' => true,
                            'order' => 20,
                        ],
                        [
                            'id' => 'change-requests',
                            'type' => 'link',
                            'label' => 'Change Requests',
                            'icon' => 'ExchangeAlt',
                            'to' => '/change-requests',
                            'required_permissions' => ['change_request.read'],
                            'show_badge_from' => '/api/metrics/change-requests?status=pending',
                            'enabled' => true,
                            'order' => 30,
                        ],
                    ],
                ],
                [
                    'id' => 'analytics',
                    'type' => 'link',
                    'label' => 'Analytics',
                    'icon' => 'ChartLine',
                    'to' => '/analytics',
                    'required_permissions' => ['analytics.read'],
                    'enabled' => true,
                    'order' => 60,
                ],
            ],
        ];
    }

    /**
     * Site Engineer Preset.
     */
    protected function getSiteEngineerPreset(): array
    {
        return [
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
                    'id' => 'tasks',
                    'type' => 'link',
                    'label' => 'Tasks',
                    'icon' => 'ListChecks',
                    'to' => '/tasks',
                    'required_permissions' => ['task.read'],
                    'show_badge_from' => '/api/metrics/tasks?status=pending',
                    'enabled' => true,
                    'order' => 30,
                ],
                [
                    'id' => 'grp-site',
                    'type' => 'group',
                    'label' => 'Site Operations',
                    'icon' => 'HardHat',
                    'required_permissions' => [],
                    'enabled' => true,
                    'order' => 40,
                    'children' => [
                        [
                            'id' => 'site-diary',
                            'type' => 'link',
                            'label' => 'Site Diary',
                            'icon' => 'Book',
                            'to' => '/site-diary',
                            'required_permissions' => ['site_diary.read'],
                            'enabled' => true,
                            'order' => 10,
                        ],
                        [
                            'id' => 'qc-inspections',
                            'type' => 'link',
                            'label' => 'QC Inspections',
                            'icon' => 'Search',
                            'to' => '/qc/inspections',
                            'required_permissions' => ['qc.read'],
                            'show_badge_from' => '/api/metrics/qc/inspections?status=pending',
                            'enabled' => true,
                            'order' => 20,
                        ],
                        [
                            'id' => 'material-requests',
                            'type' => 'link',
                            'label' => 'Material Requests',
                            'icon' => 'Boxes',
                            'to' => '/materials/requests',
                            'required_permissions' => ['material_request.read'],
                            'show_badge_from' => '/api/metrics/materials/requests?status=pending',
                            'enabled' => true,
                            'order' => 30,
                        ],
                    ],
                ],
                [
                    'id' => 'grp-collab',
                    'type' => 'group',
                    'label' => 'Collaboration',
                    'icon' => 'Handshake',
                    'required_permissions' => [],
                    'enabled' => true,
                    'order' => 50,
                    'children' => [
                        [
                            'id' => 'rfis',
                            'type' => 'link',
                            'label' => 'RFIs',
                            'icon' => 'QuestionCircle',
                            'to' => '/rfis',
                            'required_permissions' => ['rfi.read'],
                            'show_badge_from' => '/api/metrics/rfis?status=pending',
                            'enabled' => true,
                            'order' => 10,
                        ],
                        [
                            'id' => 'submittals',
                            'type' => 'link',
                            'label' => 'Submittals',
                            'icon' => 'FileAlt',
                            'to' => '/submittals',
                            'required_permissions' => ['submittal.read'],
                            'show_badge_from' => '/api/metrics/submittals?status=pending',
                            'enabled' => true,
                            'order' => 20,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Finance Preset.
     */
    protected function getFinancePreset(): array
    {
        return [
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
                    'id' => 'projects',
                    'type' => 'link',
                    'label' => 'Projects',
                    'icon' => 'Building',
                    'to' => '/projects',
                    'required_permissions' => ['project.read'],
                    'enabled' => true,
                    'order' => 20,
                ],
                [
                    'id' => 'grp-finance',
                    'type' => 'group',
                    'label' => 'Finance',
                    'icon' => 'DollarSign',
                    'required_permissions' => [],
                    'enabled' => true,
                    'order' => 30,
                    'children' => [
                        [
                            'id' => 'budget',
                            'type' => 'link',
                            'label' => 'Budget',
                            'icon' => 'Calculator',
                            'to' => '/finance/budget',
                            'required_permissions' => ['budget.read'],
                            'enabled' => true,
                            'order' => 10,
                        ],
                        [
                            'id' => 'invoices',
                            'type' => 'link',
                            'label' => 'Invoices',
                            'icon' => 'FileInvoice',
                            'to' => '/finance/invoices',
                            'required_permissions' => ['invoice.read'],
                            'show_badge_from' => '/api/metrics/invoices?status=pending',
                            'enabled' => true,
                            'order' => 20,
                        ],
                        [
                            'id' => 'bills',
                            'type' => 'link',
                            'label' => 'Bills',
                            'icon' => 'Receipt',
                            'to' => '/finance/bills',
                            'required_permissions' => ['bill.read'],
                            'show_badge_from' => '/api/metrics/bills?status=pending',
                            'enabled' => true,
                            'order' => 30,
                        ],
                        [
                            'id' => 'reports',
                            'type' => 'link',
                            'label' => 'Financial Reports',
                            'icon' => 'ChartBar',
                            'to' => '/finance/reports',
                            'required_permissions' => ['report.read'],
                            'enabled' => true,
                            'order' => 40,
                        ],
                    ],
                ],
                [
                    'id' => 'analytics',
                    'type' => 'link',
                    'label' => 'Analytics',
                    'icon' => 'ChartLine',
                    'to' => '/analytics',
                    'required_permissions' => ['analytics.read'],
                    'enabled' => true,
                    'order' => 40,
                ],
            ],
        ];
    }

    /**
     * Designer Preset.
     */
    protected function getDesignerPreset(): array
    {
        return [
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
                    'show_badge_from' => '/api/metrics/tasks?status=pending',
                    'enabled' => true,
                    'order' => 30,
                ],
                [
                    'id' => 'drawings',
                    'type' => 'link',
                    'label' => 'Drawings',
                    'icon' => 'DraftingCompass',
                    'to' => '/drawings',
                    'required_permissions' => ['drawing.read'],
                    'enabled' => true,
                    'order' => 40,
                ],
                [
                    'id' => 'grp-collab',
                    'type' => 'group',
                    'label' => 'Collaboration',
                    'icon' => 'Handshake',
                    'required_permissions' => [],
                    'enabled' => true,
                    'order' => 50,
                    'children' => [
                        [
                            'id' => 'rfis',
                            'type' => 'link',
                            'label' => 'RFIs',
                            'icon' => 'QuestionCircle',
                            'to' => '/rfis',
                            'required_permissions' => ['rfi.read'],
                            'show_badge_from' => '/api/metrics/rfis?status=pending',
                            'enabled' => true,
                            'order' => 10,
                        ],
                        [
                            'id' => 'submittals',
                            'type' => 'link',
                            'label' => 'Submittals',
                            'icon' => 'FileAlt',
                            'to' => '/submittals',
                            'required_permissions' => ['submittal.read'],
                            'show_badge_from' => '/api/metrics/submittals?status=pending',
                            'enabled' => true,
                            'order' => 20,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * QC Preset.
     */
    protected function getQCPreset(): array
    {
        return [
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
                    'id' => 'projects',
                    'type' => 'link',
                    'label' => 'Projects',
                    'icon' => 'Building',
                    'to' => '/projects',
                    'required_permissions' => ['project.read'],
                    'enabled' => true,
                    'order' => 20,
                ],
                [
                    'id' => 'grp-qc',
                    'type' => 'group',
                    'label' => 'Quality Control',
                    'icon' => 'CheckCircle',
                    'required_permissions' => [],
                    'enabled' => true,
                    'order' => 30,
                    'children' => [
                        [
                            'id' => 'qc-plans',
                            'type' => 'link',
                            'label' => 'QC Plans',
                            'icon' => 'ClipboardList',
                            'to' => '/qc/plans',
                            'required_permissions' => ['qc.read'],
                            'enabled' => true,
                            'order' => 10,
                        ],
                        [
                            'id' => 'inspections',
                            'type' => 'link',
                            'label' => 'Inspections',
                            'icon' => 'Search',
                            'to' => '/qc/inspections',
                            'required_permissions' => ['qc.read'],
                            'show_badge_from' => '/api/metrics/qc/inspections?status=pending',
                            'enabled' => true,
                            'order' => 20,
                        ],
                        [
                            'id' => 'ncrs',
                            'type' => 'link',
                            'label' => 'NCRs',
                            'icon' => 'ExclamationTriangle',
                            'to' => '/qc/ncrs',
                            'required_permissions' => ['ncr.read'],
                            'show_badge_from' => '/api/metrics/ncrs?status=open',
                            'enabled' => true,
                            'order' => 30,
                        ],
                    ],
                ],
                [
                    'id' => 'tasks',
                    'type' => 'link',
                    'label' => 'Tasks',
                    'icon' => 'ListChecks',
                    'to' => '/tasks',
                    'required_permissions' => ['task.read'],
                    'show_badge_from' => '/api/metrics/tasks?status=pending',
                    'enabled' => true,
                    'order' => 40,
                ],
            ],
        ];
    }

    /**
     * Procurement Preset.
     */
    protected function getProcurementPreset(): array
    {
        return [
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
                    'id' => 'projects',
                    'type' => 'link',
                    'label' => 'Projects',
                    'icon' => 'Building',
                    'to' => '/projects',
                    'required_permissions' => ['project.read'],
                    'enabled' => true,
                    'order' => 20,
                ],
                [
                    'id' => 'grp-procurement',
                    'type' => 'group',
                    'label' => 'Procurement',
                    'icon' => 'ShoppingCart',
                    'required_permissions' => [],
                    'enabled' => true,
                    'order' => 30,
                    'children' => [
                        [
                            'id' => 'material-requests',
                            'type' => 'link',
                            'label' => 'Material Requests',
                            'icon' => 'Boxes',
                            'to' => '/materials/requests',
                            'required_permissions' => ['material_request.read'],
                            'show_badge_from' => '/api/metrics/materials/requests?status=pending',
                            'enabled' => true,
                            'order' => 10,
                        ],
                        [
                            'id' => 'purchase-orders',
                            'type' => 'link',
                            'label' => 'Purchase Orders',
                            'icon' => 'FileInvoiceDollar',
                            'to' => '/procurement/orders',
                            'required_permissions' => ['po.read'],
                            'show_badge_from' => '/api/metrics/po?status=pending',
                            'enabled' => true,
                            'order' => 20,
                        ],
                        [
                            'id' => 'vendors',
                            'type' => 'link',
                            'label' => 'Vendors',
                            'icon' => 'Store',
                            'to' => '/vendors',
                            'required_permissions' => ['vendor.read'],
                            'enabled' => true,
                            'order' => 30,
                        ],
                        [
                            'id' => 'reports',
                            'type' => 'link',
                            'label' => 'Procurement Reports',
                            'icon' => 'ChartBar',
                            'to' => '/procurement/reports',
                            'required_permissions' => ['report.read'],
                            'enabled' => true,
                            'order' => 40,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Client Preset.
     */
    protected function getClientPreset(): array
    {
        return [
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
                    'id' => 'projects',
                    'type' => 'link',
                    'label' => 'My Projects',
                    'icon' => 'Building',
                    'to' => '/projects',
                    'required_permissions' => ['project.read'],
                    'enabled' => true,
                    'order' => 20,
                ],
                [
                    'id' => 'grp-collab',
                    'type' => 'group',
                    'label' => 'Collaboration',
                    'icon' => 'Handshake',
                    'required_permissions' => [],
                    'enabled' => true,
                    'order' => 30,
                    'children' => [
                        [
                            'id' => 'rfis',
                            'type' => 'link',
                            'label' => 'RFIs',
                            'icon' => 'QuestionCircle',
                            'to' => '/rfis',
                            'required_permissions' => ['rfi.read'],
                            'show_badge_from' => '/api/metrics/rfis?status=pending',
                            'enabled' => true,
                            'order' => 10,
                        ],
                        [
                            'id' => 'submittals',
                            'type' => 'link',
                            'label' => 'Submittals',
                            'icon' => 'FileAlt',
                            'to' => '/submittals',
                            'required_permissions' => ['submittal.read'],
                            'show_badge_from' => '/api/metrics/submittals?status=pending',
                            'enabled' => true,
                            'order' => 20,
                        ],
                    ],
                ],
                [
                    'id' => 'reports',
                    'type' => 'link',
                    'label' => 'Reports',
                    'icon' => 'ChartBar',
                    'to' => '/reports',
                    'required_permissions' => ['report.read'],
                    'enabled' => true,
                    'order' => 40,
                ],
            ],
        ];
    }

    /**
     * Admin Preset.
     */
    protected function getAdminPreset(): array
    {
        return [
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
                    'icon' => 'Building',
                    'required_permissions' => ['project.read'],
                    'enabled' => true,
                    'order' => 20,
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
                            'id' => 'construction-projects',
                            'type' => 'link',
                            'label' => 'Construction Projects',
                            'icon' => 'Crane',
                            'to' => '/projects',
                            'query' => ['type' => 'construction'],
                            'required_permissions' => ['project.read'],
                            'enabled' => true,
                            'order' => 30,
                        ],
                    ],
                ],
                [
                    'id' => 'tasks',
                    'type' => 'link',
                    'label' => 'Tasks',
                    'icon' => 'ListChecks',
                    'to' => '/tasks',
                    'required_permissions' => ['task.read'],
                    'show_badge_from' => '/api/metrics/tasks?status=pending',
                    'enabled' => true,
                    'order' => 30,
                ],
                [
                    'id' => 'users',
                    'type' => 'link',
                    'label' => 'Users',
                    'icon' => 'Users',
                    'to' => '/users',
                    'required_permissions' => ['user.read'],
                    'enabled' => true,
                    'order' => 40,
                ],
                [
                    'id' => 'grp-admin',
                    'type' => 'group',
                    'label' => 'Administration',
                    'icon' => 'Cog',
                    'required_permissions' => ['admin.access'],
                    'enabled' => true,
                    'order' => 50,
                    'children' => [
                        [
                            'id' => 'sidebar-builder',
                            'type' => 'link',
                            'label' => 'Sidebar Builder',
                            'icon' => 'Bars',
                            'to' => '/admin/sidebar-builder',
                            'required_permissions' => ['admin.sidebar.manage'],
                            'enabled' => true,
                            'order' => 10,
                        ],
                        [
                            'id' => 'system-settings',
                            'type' => 'link',
                            'label' => 'System Settings',
                            'icon' => 'Cog',
                            'to' => '/admin/settings',
                            'required_permissions' => ['admin.settings'],
                            'enabled' => true,
                            'order' => 20,
                        ],
                    ],
                ],
                [
                    'id' => 'analytics',
                    'type' => 'link',
                    'label' => 'Analytics',
                    'icon' => 'ChartLine',
                    'to' => '/analytics',
                    'required_permissions' => ['analytics.read'],
                    'enabled' => true,
                    'order' => 60,
                ],
            ],
        ];
    }
}