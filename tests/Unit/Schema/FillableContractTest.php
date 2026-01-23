<?php

namespace Tests\Unit\Schema;

use App\Models\ChangeRequest;
use App\Models\DashboardAlert;
use App\Models\DashboardMetric;
use App\Models\DashboardWidget;
use App\Models\Document;
use App\Models\Notification;
use App\Models\QcPlan;
use App\Models\Rfi;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\UserRole;
use App\Models\UserRoleProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FillableContractTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function fillable_attributes_are_backed_by_database_columns()
    {
        $models = [
            QcPlan::class => [],
            ChangeRequest::class => [],
            Rfi::class => [],
            Document::class => [],
            Notification::class => [],
            DashboardWidget::class => [],
            DashboardMetric::class => [],
            DashboardAlert::class => [],
            Tenant::class => [],
            Team::class => [],
            UserRole::class => [],
            UserRoleProject::class => [],
        ];

        foreach ($models as $modelClass => $config) {
            $model = new $modelClass();
            $table = $model->getTable();
            $columns = Schema::getColumnListing($table);

            $this->assertNotEmpty($columns, sprintf('Table %s must expose columns via Schema', $table));

            $aliases = $config['aliases'] ?? [];

            foreach ($model->getFillable() as $fillable) {
                $this->assertTrue(
                    in_array($fillable, $columns, true) || array_key_exists($fillable, $aliases),
                    sprintf('%s declares fillable "%s" but table %s does not have that column', $modelClass, $fillable, $table)
                );
            }
        }
    }
}
