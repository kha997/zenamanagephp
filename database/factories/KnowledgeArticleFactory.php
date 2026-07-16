<?php

namespace Database\Factories;

use App\Models\KnowledgeArticle;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\KnowledgeArticle> */
class KnowledgeArticleFactory extends Factory
{
    protected $model = KnowledgeArticle::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'type' => KnowledgeArticle::TYPE_SOP,
            'title' => $this->faker->sentence(4),
            'body' => $this->faker->paragraph(),
            'status' => KnowledgeArticle::STATUS_DRAFT,
            'created_by' => User::factory(),
        ];
    }
}
