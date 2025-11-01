<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProjectTemplate;

class ProjectTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Residential Design Template',
                'category' => 'residential',
                'description' => 'Complete template for residential design projects.',
                'is_default' => true,
                'phases' => [
                    'architectural' => [
                        'name' => 'Architectural Design',
                        'color' => 'blue',
                        'tasks' => [
                            ['name' => 'Site Analysis', 'duration' => 5, 'priority' => 'high'],
                            ['name' => 'Concept Design', 'duration' => 10, 'priority' => 'high'],
                            ['name' => 'Schematic Design', 'duration' => 15, 'priority' => 'high'],
                            ['name' => 'Design Development', 'duration' => 20, 'priority' => 'medium'],
                            ['name' => 'Construction Documents', 'duration' => 25, 'priority' => 'high']
                        ]
                    ],
                    'structural' => [
                        'name' => 'Structural Design',
                        'color' => 'green',
                        'tasks' => [
                            ['name' => 'Load Analysis', 'duration' => 8, 'priority' => 'high'],
                            ['name' => 'Foundation Design', 'duration' => 12, 'priority' => 'high'],
                            ['name' => 'Frame Design', 'duration' => 15, 'priority' => 'high'],
                            ['name' => 'Detail Drawings', 'duration' => 10, 'priority' => 'medium']
                        ]
                    ],
                    'mep' => [
                        'name' => 'MEP Design',
                        'color' => 'orange',
                        'tasks' => [
                            ['name' => 'Electrical Design', 'duration' => 12, 'priority' => 'high'],
                            ['name' => 'Mechanical Design', 'duration' => 15, 'priority' => 'high'],
                            ['name' => 'Plumbing Design', 'duration' => 10, 'priority' => 'medium'],
                            ['name' => 'Fire Protection', 'duration' => 8, 'priority' => 'high']
                        ]
                    ],
                    'landscape' => [
                        'name' => 'Landscape Design',
                        'color' => 'purple',
                        'tasks' => [
                            ['name' => 'Site Analysis', 'duration' => 5, 'priority' => 'medium'],
                            ['name' => 'Master Planning', 'duration' => 10, 'priority' => 'medium'],
                            ['name' => 'Planting Design', 'duration' => 8, 'priority' => 'medium'],
                            ['name' => 'Hardscape Design', 'duration' => 6, 'priority' => 'low']
                        ]
                    ]
                ],
                'default_settings' => [
                    'project_type' => 'residential',
                    'timeline_months' => 12,
                    'team_size' => 8
                ]
            ],
            [
                'name' => 'Residential Construction Template',
                'category' => 'construction',
                'description' => 'Complete template for residential construction projects with detailed phases.',
                'is_default' => false,
                'phases' => [
                    'pre_construction' => [
                        'name' => 'Pre-Construction',
                        'color' => 'gray',
                        'tasks' => [
                            ['name' => 'Site Preparation', 'duration' => 5, 'priority' => 'high'],
                            ['name' => 'Permit Acquisition', 'duration' => 10, 'priority' => 'critical'],
                            ['name' => 'Material Procurement', 'duration' => 8, 'priority' => 'high'],
                            ['name' => 'Equipment Setup', 'duration' => 3, 'priority' => 'medium'],
                            ['name' => 'Safety Planning', 'duration' => 2, 'priority' => 'critical']
                        ]
                    ],
                    'foundation' => [
                        'name' => 'Foundation & Structure',
                        'color' => 'brown',
                        'tasks' => [
                            ['name' => 'Excavation', 'duration' => 7, 'priority' => 'high'],
                            ['name' => 'Foundation Pouring', 'duration' => 5, 'priority' => 'critical'],
                            ['name' => 'Foundation Curing', 'duration' => 14, 'priority' => 'high'],
                            ['name' => 'Structural Frame', 'duration' => 21, 'priority' => 'critical'],
                            ['name' => 'Roof Structure', 'duration' => 10, 'priority' => 'high']
                        ]
                    ],
                    'mep_installation' => [
                        'name' => 'MEP Installation',
                        'color' => 'blue',
                        'tasks' => [
                            ['name' => 'Electrical Rough-in', 'duration' => 12, 'priority' => 'high'],
                            ['name' => 'Plumbing Rough-in', 'duration' => 10, 'priority' => 'high'],
                            ['name' => 'HVAC Installation', 'duration' => 15, 'priority' => 'high'],
                            ['name' => 'Fire Protection System', 'duration' => 8, 'priority' => 'critical'],
                            ['name' => 'MEP Testing', 'duration' => 5, 'priority' => 'high']
                        ]
                    ],
                    'interior' => [
                        'name' => 'Interior Finishing',
                        'color' => 'orange',
                        'tasks' => [
                            ['name' => 'Drywall Installation', 'duration' => 8, 'priority' => 'medium'],
                            ['name' => 'Flooring Installation', 'duration' => 12, 'priority' => 'medium'],
                            ['name' => 'Paint & Finishes', 'duration' => 6, 'priority' => 'medium'],
                            ['name' => 'Kitchen Installation', 'duration' => 10, 'priority' => 'high'],
                            ['name' => 'Bathroom Installation', 'duration' => 8, 'priority' => 'high']
                        ]
                    ],
                    'exterior' => [
                        'name' => 'Exterior & Landscaping',
                        'color' => 'green',
                        'tasks' => [
                            ['name' => 'Exterior Siding', 'duration' => 10, 'priority' => 'medium'],
                            ['name' => 'Roofing Installation', 'duration' => 7, 'priority' => 'high'],
                            ['name' => 'Windows & Doors', 'duration' => 5, 'priority' => 'medium'],
                            ['name' => 'Landscaping', 'duration' => 8, 'priority' => 'low'],
                            ['name' => 'Driveway & Walkways', 'duration' => 6, 'priority' => 'medium']
                        ]
                    ],
                    'final_inspection' => [
                        'name' => 'Final Inspection & Handover',
                        'color' => 'purple',
                        'tasks' => [
                            ['name' => 'Final Inspection', 'duration' => 3, 'priority' => 'critical'],
                            ['name' => 'Punch List Completion', 'duration' => 5, 'priority' => 'high'],
                            ['name' => 'Cleaning & Preparation', 'duration' => 3, 'priority' => 'medium'],
                            ['name' => 'Documentation', 'duration' => 2, 'priority' => 'medium'],
                            ['name' => 'Client Handover', 'duration' => 1, 'priority' => 'critical']
                        ]
                    ]
                ],
                'default_settings' => [
                    'project_type' => 'construction',
                    'timeline_months' => 18,
                    'team_size' => 12
                ]
            ],
            [
                'name' => 'Commercial Construction Template',
                'category' => 'construction',
                'description' => 'Comprehensive template for commercial construction projects.',
                'is_default' => false,
                'phases' => [
                    'pre_construction' => [
                        'name' => 'Pre-Construction',
                        'color' => 'gray',
                        'tasks' => [
                            ['name' => 'Site Survey & Analysis', 'duration' => 7, 'priority' => 'high'],
                            ['name' => 'Permit & Approval Process', 'duration' => 15, 'priority' => 'critical'],
                            ['name' => 'Material Procurement', 'duration' => 12, 'priority' => 'high'],
                            ['name' => 'Equipment Mobilization', 'duration' => 5, 'priority' => 'medium'],
                            ['name' => 'Safety & Security Setup', 'duration' => 3, 'priority' => 'critical']
                        ]
                    ],
                    'foundation' => [
                        'name' => 'Foundation & Structure',
                        'color' => 'brown',
                        'tasks' => [
                            ['name' => 'Site Excavation', 'duration' => 10, 'priority' => 'high'],
                            ['name' => 'Foundation Construction', 'duration' => 14, 'priority' => 'critical'],
                            ['name' => 'Structural Steel Frame', 'duration' => 28, 'priority' => 'critical'],
                            ['name' => 'Concrete Work', 'duration' => 21, 'priority' => 'high'],
                            ['name' => 'Roof Structure', 'duration' => 14, 'priority' => 'high']
                        ]
                    ],
                    'mep_installation' => [
                        'name' => 'MEP Installation',
                        'color' => 'blue',
                        'tasks' => [
                            ['name' => 'Electrical Systems', 'duration' => 21, 'priority' => 'high'],
                            ['name' => 'Plumbing Systems', 'duration' => 18, 'priority' => 'high'],
                            ['name' => 'HVAC Systems', 'duration' => 25, 'priority' => 'high'],
                            ['name' => 'Fire Protection', 'duration' => 12, 'priority' => 'critical'],
                            ['name' => 'Security Systems', 'duration' => 10, 'priority' => 'medium']
                        ]
                    ],
                    'interior' => [
                        'name' => 'Interior Finishing',
                        'color' => 'orange',
                        'tasks' => [
                            ['name' => 'Drywall & Ceilings', 'duration' => 14, 'priority' => 'medium'],
                            ['name' => 'Flooring Installation', 'duration' => 18, 'priority' => 'medium'],
                            ['name' => 'Paint & Finishes', 'duration' => 10, 'priority' => 'medium'],
                            ['name' => 'Office Fit-out', 'duration' => 21, 'priority' => 'high'],
                            ['name' => 'Restroom Installation', 'duration' => 12, 'priority' => 'high']
                        ]
                    ],
                    'exterior' => [
                        'name' => 'Exterior & Site Work',
                        'color' => 'green',
                        'tasks' => [
                            ['name' => 'Exterior Cladding', 'duration' => 18, 'priority' => 'medium'],
                            ['name' => 'Roofing & Waterproofing', 'duration' => 12, 'priority' => 'high'],
                            ['name' => 'Windows & Doors', 'duration' => 10, 'priority' => 'medium'],
                            ['name' => 'Parking & Landscaping', 'duration' => 14, 'priority' => 'medium'],
                            ['name' => 'Signage & Branding', 'duration' => 7, 'priority' => 'low']
                        ]
                    ],
                    'final_inspection' => [
                        'name' => 'Final Inspection & Handover',
                        'color' => 'purple',
                        'tasks' => [
                            ['name' => 'Building Inspection', 'duration' => 5, 'priority' => 'critical'],
                            ['name' => 'System Testing', 'duration' => 7, 'priority' => 'critical'],
                            ['name' => 'Punch List Completion', 'duration' => 10, 'priority' => 'high'],
                            ['name' => 'Final Cleaning', 'duration' => 5, 'priority' => 'medium'],
                            ['name' => 'Client Training & Handover', 'duration' => 3, 'priority' => 'critical']
                        ]
                    ]
                ],
                'default_settings' => [
                    'project_type' => 'construction',
                    'timeline_months' => 24,
                    'team_size' => 20
                ]
            ]
        ];

        foreach ($templates as $template) {
            ProjectTemplate::create($template);
        }
    }
}