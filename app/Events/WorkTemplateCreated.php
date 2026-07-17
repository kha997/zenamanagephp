<?php declare(strict_types=1);

namespace App\Events;

use App\Models\WorkTemplate;
use App\Models\WorkTemplateVersion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkTemplateCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WorkTemplate $template,
        public ?WorkTemplateVersion $version = null
    ) {
    }
}
