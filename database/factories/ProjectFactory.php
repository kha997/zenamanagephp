<?php declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Src\CoreProject\Models\Project;
use App\Models\Tenant;
use Carbon\Carbon;

/**
 * Factory cho Project model
 * 
 * Tạo dữ liệu giả cho testing Project
 * Hỗ trợ các trạng thái và cấu hình khác nhau
 */
class ProjectFactory extends Factory
{
    /**
     * Model tương ứng với factory này
     */
    protected $model = Project::class;

    /**
     * Định nghĩa trạng thái mặc định của model
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-6 months', '+1 month');
        $endDate = $this->faker->dateTimeBetween($startDate, '+1 year');
        
        return [
            // Xóa dòng này: 'id' => Str::ulid()->toString(),
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(2),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $this->faker->randomElement(Project::VALID_STATUSES),
            'progress' => $this->faker->randomFloat(2, 0, 100),
            'actual_cost' => $this->faker->randomFloat(2, 0, 1000000),
        ];
    }

    /**
     * State cho project đang trong giai đoạn planning
     */
    public function planning(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Project::STATUS_PLANNING,
            'progress' => 0.0,
            'actual_cost' => 0.0,
        ]);
    }

    /**
     * State cho project đang active
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Project::STATUS_ACTIVE,
            'progress' => $this->faker->randomFloat(2, 10, 80),
        ]);
    }

    /**
     * State cho project đã completed
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Project::STATUS_COMPLETED,
            'progress' => 100.0,
            'end_date' => $this->faker->dateTimeBetween('-3 months', 'now'),
        ]);
    }

    /**
     * State cho project bị on hold
     */
    public function onHold(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Project::STATUS_ON_HOLD,
        ]);
    }

    /**
     * State cho project bị cancelled
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Project::STATUS_CANCELLED,
        ]);
    }

    /**
     * State cho project với tenant cụ thể (nhận ULID string)
     */
    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * State cho project với thời gian cụ thể
     */
    public function withDates(Carbon $startDate, Carbon $endDate): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * State cho project với progress cụ thể
     */
    public function withProgress(float $progress): static
    {
        return $this->state(fn (array $attributes) => [
            'progress' => $progress,
        ]);
    }

    /**
     * State cho project với cost cụ thể
     */
    public function withCost(float $actualCost): static
    {
        return $this->state(fn (array $attributes) => [
            'actual_cost' => $actualCost,
        ]);
    }
}