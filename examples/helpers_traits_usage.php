<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Foundation/Helpers/ArrayHelper.php';
require_once __DIR__ . '/../src/Foundation/Helpers/StringHelper.php';
require_once __DIR__ . '/../src/Foundation/Helpers/ValidationHelper.php';

use zenamanage\Foundation\Helpers\ArrayHelper;
use zenamanage\Foundation\Helpers\StringHelper;
use zenamanage\Foundation\Helpers\ValidationHelper;

echo "=== Helper Functions Usage Examples ===\n\n";

// ArrayHelper examples
echo "--- ArrayHelper ---\n";
$data = [
    'user' => [
        'profile' => [
            'name' => 'Nguyễn Văn A',
            'email' => 'nguyenvana@example.com'
        ]
    ]
];

echo "Get nested value: " . ArrayHelper::get($data, 'user.profile.name') . "\n";
echo "Get with default: " . ArrayHelper::get($data, 'user.profile.phone', 'N/A') . "\n";

$filtered = ArrayHelper::only($data['user']['profile'], ['name']);
echo "Filtered array: " . json_encode($filtered) . "\n\n";

// StringHelper examples
echo "--- StringHelper ---\n";
echo "Slug: " . StringHelper::slug('Dự án Xây dựng Nhà ở') . "\n";
echo "Remove accents: " . StringHelper::removeVietnameseAccents('Tiếng Việt có dấu') . "\n";
echo "Truncate: " . StringHelper::truncate('This is a very long text that needs to be truncated', 20) . "\n";
echo "CamelCase: " . StringHelper::camelCase('user_profile_name') . "\n";
echo "SnakeCase: " . StringHelper::snakeCase('UserProfileName') . "\n";
echo "Random password: " . StringHelper::randomPassword(12) . "\n\n";

// ValidationHelper examples
echo "--- ValidationHelper ---\n";
echo "Valid email: " . (ValidationHelper::isValidEmail('test@example.com') ? 'Yes' : 'No') . "\n";
echo "Valid VN phone: " . (ValidationHelper::isValidVietnamesePhone('0901234567') ? 'Yes' : 'No') . "\n";

$passwordCheck = ValidationHelper::validatePasswordStrength('MyPassword123!');
echo "Password strength: " . $passwordCheck['strength'] . "/100\n";
echo "Password valid: " . ($passwordCheck['valid'] ? 'Yes' : 'No') . "\n";
if (!$passwordCheck['valid']) {
    echo "Errors: " . implode(', ', $passwordCheck['errors']) . "\n";
}

echo "Valid ULID: " . (ValidationHelper::isValidULID('01ARZ3NDEKTSV4RRFFQ69G5FAV') ? 'Yes' : 'No') . "\n";
echo "Valid ISO8601: " . (ValidationHelper::isValidISO8601('2023-12-25T10:30:00.000Z') ? 'Yes' : 'No') . "\n\n";

echo "=== Model Traits Usage (Conceptual) ===\n";
echo "\n";
echo "// Example Model using traits:\n";
echo "class Project extends Model {\n";
echo "    use HasULID, HasTimestamps, HasOwnership, HasTags, HasVisibility, HasAuditLog;\n";
echo "    \n";
echo "    // Model sẽ tự động có:\n";
echo "    // - ULID primary key\n";
echo "    // - ISO 8601 timestamps\n";
echo "    // - Ownership tracking (tenant_id, project_id, created_by, updated_by)\n";
echo "    // - Tag management (tag_path)\n";
echo "    // - Visibility control (visibility, client_approved)\n";
echo "    // - Audit logging (tự động ghi log khi thay đổi)\n";
echo "}\n";
echo "\n";
echo "// Usage:\n";
echo "\$project = new Project();\n";
echo "\$project->name = 'Dự án mới';\n";
echo "\$project->addTag('Construction');\n";
echo "\$project->addTag('Residential');\n";
echo "\$project->approveForClient();\n";
echo "\$project->save(); // Tự động tạo ULID, timestamps, ownership, audit log\n";