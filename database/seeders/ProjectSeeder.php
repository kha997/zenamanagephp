<?php declare(strict_types=1);

namespace Database\Seeders;

use Src\CoreProject\Models\Project;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Project Seeder
 * 
 * Tạo dữ liệu mẫu cho projects
 * Sử dụng ULID và liên kết với tenant qua ULID
 */
class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();
        
        foreach ($tenants as $tenant) {
            // Tạo 1 project đang planning
            Project::factory()
                ->forTenant($tenant->id)
                ->planning()
                ->create([
                    'name' => "Dự án Planning - {$tenant->name}"
                ]);

            // Tạo 2 project đang active
            Project::factory(2)
                ->forTenant($tenant->id)
                ->active()
                ->create();

            // Tạo 1 project đã completed
            Project::factory()
                ->forTenant($tenant->id)
                ->completed()
                ->create([
                    'name' => "Dự án Hoàn thành - {$tenant->name}"
                ]);
        }
    }
}