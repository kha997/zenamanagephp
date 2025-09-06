<?php declare(strict_types=1);

namespace Database\Seeders;

use Src\Notification\Models\Notification;
use Src\Notification\Models\NotificationRule;
use Src\CoreProject\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Notification Seeder
 * 
 * Tạo dữ liệu mẫu cho notifications và notification rules
 */
class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $projects = Project::all();

        // Tạo notification rules cho users
        foreach ($users as $user) {
            // Global notification rules
            NotificationRule::factory(2)
                ->forUser($user->id)
                ->global()
                ->create();

            // Project-specific notification rules
            foreach ($projects->take(2) as $project) {
                NotificationRule::factory(1)
                    ->forUser($user->id)
                    ->forProject($project->id)
                    ->create();
            }
        }

        // Tạo notifications cho users
        foreach ($users as $user) {
            // Unread notifications
            Notification::factory(5)
                ->forUser($user->id)
                ->unread()
                ->create();

            // Read notifications
            Notification::factory(3)
                ->forUser($user->id)
                ->read()
                ->create();

            // Critical notifications
            Notification::factory(1)
                ->forUser($user->id)
                ->critical()
                ->create();
        }
    }
}