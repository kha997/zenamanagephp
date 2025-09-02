<?php declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\CoreProject\Models\WorkTemplate;

/**
 * Seeder cho WorkTemplate model
 * 
 * Tạo dữ liệu mẫu cho work templates
 */
class WorkTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createDesignTemplates();
        $this->createConstructionTemplates();
        $this->createQcTemplates();
        $this->createInspectionTemplates();
    }

    /**
     * Tạo design templates
     */
    private function createDesignTemplates(): void
    {
        WorkTemplate::create([
            'name' => 'Thiết kế Kiến trúc Cơ bản',
            'description' => 'Template cho thiết kế kiến trúc các công trình dân dụng',
            'category' => 'design',
            'template_data' => [
                'tasks' => [
                    [
                        'name' => 'Khảo sát hiện trạng',
                        'description' => 'Khảo sát địa hình, hiện trạng công trình',
                        'estimated_hours' => 16,
                        'priority' => 'high',
                        'tags' => ['survey', 'site-analysis']
                    ],
                    [
                        'name' => 'Phác thảo ý tưởng thiết kế',
                        'description' => 'Tạo các phác thảo ban đầu cho thiết kế',
                        'estimated_hours' => 24,
                        'priority' => 'normal',
                        'tags' => ['concept', 'sketching']
                    ],
                    [
                        'name' => 'Thiết kế sơ bộ',
                        'description' => 'Hoàn thiện thiết kế sơ bộ với các bản vẽ cơ bản',
                        'estimated_hours' => 40,
                        'priority' => 'high',
                        'tags' => ['preliminary-design', 'drawings']
                    ],
                    [
                        'name' => 'Thiết kế chi tiết',
                        'description' => 'Hoàn thiện thiết kế chi tiết và bản vẽ thi công',
                        'estimated_hours' => 80,
                        'priority' => 'high',
                        'tags' => ['detailed-design', 'construction-drawings']
                    ]
                ]
            ],
            'version' => 1,
            'is_active' => true,
        ]);

        WorkTemplate::create([
            'name' => 'Thiết kế Nội thất',
            'description' => 'Template cho thiết kế nội thất',
            'category' => 'design',
            'template_data' => [
                'tasks' => [
                    [
                        'name' => 'Tư vấn phong cách',
                        'description' => 'Tư vấn và xác định phong cách thiết kế',
                        'estimated_hours' => 8,
                        'priority' => 'normal',
                        'tags' => ['consultation', 'style']
                    ],
                    [
                        'name' => 'Thiết kế layout',
                        'description' => 'Thiết kế bố trí không gian nội thất',
                        'estimated_hours' => 20,
                        'priority' => 'high',
                        'tags' => ['layout', 'space-planning']
                    ],
                    [
                        'name' => 'Chọn vật liệu và màu sắc',
                        'description' => 'Lựa chọn vật liệu, màu sắc cho nội thất',
                        'estimated_hours' => 12,
                        'priority' => 'normal',
                        'tags' => ['materials', 'colors']
                    ],
                    [
                        'name' => 'Render 3D',
                        'description' => 'Tạo hình ảnh 3D cho thiết kế',
                        'estimated_hours' => 32,
                        'priority' => 'normal',
                        'tags' => ['3d-rendering', 'visualization'],
                        'conditional_tag' => 'premium_package'
                    ]
                ]
            ],
            'version' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * Tạo construction templates
     */
    private function createConstructionTemplates(): void
    {
        WorkTemplate::create([
            'name' => 'Xây dựng Nhà ở',
            'description' => 'Template cho thi công xây dựng nhà ở',
            'category' => 'construction',
            'template_data' => [
                'tasks' => [
                    [
                        'name' => 'Chuẩn bị mặt bằng',
                        'description' => 'San lấp, chuẩn bị mặt bằng thi công',
                        'estimated_hours' => 40,
                        'priority' => 'high',
                        'tags' => ['site-preparation', 'earthwork']
                    ],
                    [
                        'name' => 'Đào móng',
                        'description' => 'Đào hố móng theo thiết kế',
                        'estimated_hours' => 32,
                        'priority' => 'high',
                        'tags' => ['foundation', 'excavation']
                    ],
                    [
                        'name' => 'Đổ bê tông móng',
                        'description' => 'Thi công bê tông móng',
                        'estimated_hours' => 24,
                        'priority' => 'critical',
                        'tags' => ['foundation', 'concrete']
                    ],
                    [
                        'name' => 'Xây tường',
                        'description' => 'Thi công xây tường gạch',
                        'estimated_hours' => 80,
                        'priority' => 'high',
                        'tags' => ['walls', 'masonry']
                    ],
                    [
                        'name' => 'Lắp đặt mái',
                        'description' => 'Thi công kết cấu mái và lợp mái',
                        'estimated_hours' => 48,
                        'priority' => 'high',
                        'tags' => ['roof', 'structure']
                    ]
                ]
            ],
            'version' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * Tạo QC templates
     */
    private function createQcTemplates(): void
    {
        WorkTemplate::create([
            'name' => 'Kiểm soát Chất lượng Xây dựng',
            'description' => 'Template cho kiểm soát chất lượng thi công',
            'category' => 'qc',
            'template_data' => [
                'tasks' => [
                    [
                        'name' => 'Kiểm tra vật liệu đầu vào',
                        'description' => 'Kiểm tra chất lượng vật liệu trước khi sử dụng',
                        'estimated_hours' => 8,
                        'priority' => 'high',
                        'tags' => ['material-inspection', 'quality-control']
                    ],
                    [
                        'name' => 'Kiểm tra quá trình thi công',
                        'description' => 'Giám sát và kiểm tra trong quá trình thi công',
                        'estimated_hours' => 40,
                        'priority' => 'critical',
                        'tags' => ['process-inspection', 'supervision']
                    ],
                    [
                        'name' => 'Kiểm tra sản phẩm hoàn thành',
                        'description' => 'Kiểm tra chất lượng sản phẩm sau khi hoàn thành',
                        'estimated_hours' => 16,
                        'priority' => 'high',
                        'tags' => ['final-inspection', 'quality-assurance']
                    ],
                    [
                        'name' => 'Lập báo cáo chất lượng',
                        'description' => 'Tổng hợp và lập báo cáo chất lượng',
                        'estimated_hours' => 8,
                        'priority' => 'normal',
                        'tags' => ['reporting', 'documentation']
                    ]
                ]
            ],
            'version' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * Tạo inspection templates
     */
    private function createInspectionTemplates(): void
    {
        WorkTemplate::create([
            'name' => 'Nghiệm thu Công trình',
            'description' => 'Template cho nghiệm thu công trình xây dựng',
            'category' => 'inspection',
            'template_data' => [
                'tasks' => [
                    [
                        'name' => 'Chuẩn bị hồ sơ nghiệm thu',
                        'description' => 'Chuẩn bị đầy đủ hồ sơ cho quá trình nghiệm thu',
                        'estimated_hours' => 16,
                        'priority' => 'high',
                        'tags' => ['documentation', 'preparation']
                    ],
                    [
                        'name' => 'Nghiệm thu từng hạng mục',
                        'description' => 'Nghiệm thu chi tiết từng hạng mục công trình',
                        'estimated_hours' => 32,
                        'priority' => 'critical',
                        'tags' => ['detailed-inspection', 'item-by-item']
                    ],
                    [
                        'name' => 'Nghiệm thu tổng thể',
                        'description' => 'Nghiệm thu tổng thể toàn bộ công trình',
                        'estimated_hours' => 24,
                        'priority' => 'critical',
                        'tags' => ['overall-inspection', 'final-acceptance']
                    ],
                    [
                        'name' => 'Bàn giao công trình',
                        'description' => 'Thực hiện bàn giao công trình cho chủ đầu tư',
                        'estimated_hours' => 8,
                        'priority' => 'high',
                        'tags' => ['handover', 'delivery']
                    ]
                ]
            ],
            'version' => 1,
            'is_active' => true,
        ]);
    }
}