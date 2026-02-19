<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\ZenaRfi;

/**
 * Legacy alias factory that reuses the unified {@see RfiFactory}.
 *
 * Because this factory still supports the deprecated {@see \App\Models\ZenaRfi} alias,
 * it mirrors the {@see \App\Models\Rfi} unified schema (tenant_id, project_id, subject,
 * question, status, asked_by, assigned_to, due_date, attachments, timestamps, etc.).
 *
 * @deprecated Use {@see \Database\Factories\RfiFactory} with {@see \App\Models\Rfi} directly going forward.
 */
class ZenaRfiFactory extends RfiFactory
{
    protected $model = ZenaRfi::class;
}
