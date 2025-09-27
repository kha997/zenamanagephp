<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'name',
        'description',
        'type',
        'format',
        'layout',
        'sections',
        'filters',
        'styling',
        'is_public',
        'is_active',
        'usage_count'
    ];

    protected $casts = [
        'layout' => 'array',
        'sections' => 'array',
        'filters' => 'array',
        'styling' => 'array',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'usage_count' => 'integer'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function incrementUsage()
    {
        $this->increment('usage_count');
    }

    public function getDefaultLayout(): array
    {
        return [
            'header' => [
                'show_logo' => true,
                'show_title' => true,
                'show_date' => true,
                'show_page_numbers' => true
            ],
            'footer' => [
                'show_company_info' => true,
                'show_generated_by' => true,
                'show_generation_time' => true
            ],
            'page' => [
                'orientation' => 'portrait',
                'margins' => [
                    'top' => 20,
                    'right' => 20,
                    'bottom' => 20,
                    'left' => 20
                ]
            ]
        ];
    }

    public function getDefaultSections(): array
    {
        return [
            'summary' => [
                'enabled' => true,
                'title' => 'Executive Summary',
                'position' => 1
            ],
            'metrics' => [
                'enabled' => true,
                'title' => 'Key Metrics',
                'position' => 2
            ],
            'charts' => [
                'enabled' => true,
                'title' => 'Charts & Visualizations',
                'position' => 3
            ],
            'details' => [
                'enabled' => true,
                'title' => 'Detailed Data',
                'position' => 4
            ]
        ];
    }

    public function getDefaultStyling(): array
    {
        return [
            'colors' => [
                'primary' => '#3B82F6',
                'secondary' => '#6B7280',
                'success' => '#10B981',
                'warning' => '#F59E0B',
                'danger' => '#EF4444'
            ],
            'fonts' => [
                'header' => 'Arial',
                'body' => 'Arial',
                'size' => [
                    'header' => 16,
                    'subheader' => 14,
                    'body' => 12
                ]
            ],
            'spacing' => [
                'section_margin' => 20,
                'paragraph_margin' => 10,
                'line_height' => 1.4
            ]
        ];
    }

    public function getTemplateData(): array
    {
        return [
            'layout' => $this->layout ?: $this->getDefaultLayout(),
            'sections' => $this->sections ?: $this->getDefaultSections(),
            'styling' => $this->styling ?: $this->getDefaultStyling(),
            'filters' => $this->filters ?: []
        ];
    }
}
