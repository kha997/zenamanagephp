<?php declare(strict_types=1);

namespace App\Http\Resources;

use Src\CoreProject\Resources\WorkTemplateResource as SrcWorkTemplateResource;

/**
 * Wrapper resource để giữ nguyên behavior từ Src\CoreProject\Resources\WorkTemplateResource
 * nhưng cho phép Controllers dùng App\Http\Resources\WorkTemplateResource.
 */
class WorkTemplateResource extends SrcWorkTemplateResource
{
    // Intentionally empty: inherit everything from source resource.
}
