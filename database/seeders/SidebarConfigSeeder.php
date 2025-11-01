<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SidebarConfig;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SidebarConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding default sidebar configurations...');

        // Get default configs from model
        $defaultConfigs = SidebarConfig::getDefaultConfigs();

        foreach ($defaultConfigs as $roleName => $config) {
            // Check if config already exists
            $existingConfig = SidebarConfig::forRole($roleName)->global()->first();
            
            if (!$existingConfig) {
                SidebarConfig::create([
                    'role_name' => $roleName,
                    'config' => $config,
                    'tenant_id' => null, // Global config
                    'is_enabled' => true,
                    'version' => 1,
                    'updated_by' => null, // System seeder
                ]);
                
                $this->command->info("✓ Created default config for role: {$roleName}");
            } else {
                $this->command->warn("⚠ Config already exists for role: {$roleName}");
            }
        }

        $this->command->info('✅ Sidebar configuration seeding completed!');
    }
}
