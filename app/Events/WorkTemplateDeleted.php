<?php declare(strict_types=1);

namespace App\Events;

use App\Models\WorkTemplate;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkTemplateDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public WorkTemplate $template)
    {
    }
}
