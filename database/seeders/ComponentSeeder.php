<?php declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Component;

/**
 * Seeder cho Component model
 * 
 * Tạo dữ liệu mẫu cho components với cấu trúc phân cấp
 */
class ComponentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lấy tất cả projects để tạo components
        $projects = Project::all();
        
        foreach ($projects as $project) {
            $this->createComponentsForProject($project);
        }
    }

    /**
     * Tạo components cho một project
     */
    private function createComponentsForProject(Project $project): void
    {
        // Tạo 2-4 root components cho mỗi project
        $rootComponentsCount = fake()->numberBetween(2, 4);
        
        for ($i = 1; $i <= $rootComponentsCount; $i++) {
            $rootComponent = Component::factory()
                ->forProject($project)
                ->create([
                    'name' => $this->getRootComponentName($i),
                    'planned_cost' => fake()->randomFloat(2, 100000, 1000000),
                ]);
            
            // Tạo 2-5 sub-components cho mỗi root component
            $subComponentsCount = fake()->numberBetween(2, 5);
            
            for ($j = 1; $j <= $subComponentsCount; $j++) {
                $subComponent = Component::factory()
                    ->withParent($rootComponent)
                    ->create([
                        'name' => $this->getSubComponentName($rootComponent->name, $j),
                    ]);
                
                // 30% chance tạo sub-sub-components
                if (fake()->boolean(30)) {
                    $subSubComponentsCount = fake()->numberBetween(1, 3);
                    
                    for ($k = 1; $k <= $subSubComponentsCount; $k++) {
                        Component::factory()
                            ->withParent($subComponent)
                            ->create([
                                'name' => $this->getSubSubComponentName($subComponent->name, $k),
                                'planned_cost' => fake()->randomFloat(2, 5000, 50000),
                            ]);
                    }
                }
            }
        }
    }

    /**
     * Lấy tên cho root component
     */
    private function getRootComponentName(int $index): string
    {
        $names = [
            'Thiết kế & Lập kế hoạch',
            'Xây dựng cơ sở hạ tầng', 
            'Hoàn thiện nội thất',
            'Kiểm tra & Nghiệm thu',
            'Bảo trì & Vận hành'
        ];
        
        return $names[$index - 1] ?? "Component chính #{$index}";
    }

    /**
     * Lấy tên cho sub component
     */
    private function getSubComponentName(string $parentName, int $index): string
    {
        $subNames = [
            'Thiết kế & Lập kế hoạch' => [
                'Khảo sát địa hình',
                'Thiết kế kiến trúc',
                'Thiết kế kết cấu',
                'Lập hồ sơ pháp lý',
                'Lập kế hoạch thi công'
            ],
            'Xây dựng cơ sở hạ tầng' => [
                'Đào móng',
                'Đổ bê tông móng',
                'Xây tường',
                'Lắp đặt mái',
                'Hệ thống điện nước'
            ],
            'Hoàn thiện nội thất' => [
                'Sơn tường',
                'Lắp đặt sàn',
                'Lắp đặt cửa',
                'Trang trí nội thất',
                'Vệ sinh tổng thể'
            ]
        ];
        
        if (isset($subNames[$parentName][$index - 1])) {
            return $subNames[$parentName][$index - 1];
        }
        
        return "{$parentName} - Phần {$index}";
    }

    /**
     * Lấy tên cho sub-sub component
     */
    private function getSubSubComponentName(string $parentName, int $index): string
    {
        return "{$parentName} - Chi tiết {$index}";
    }
}