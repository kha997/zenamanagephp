<?php declare(strict_types=1);

namespace Src\WorkTemplate\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

/**
 * Template Version API Resource
 * 
 * Transform TemplateVersion model thành JSON response
 * Dùng cho version history và rollback functionality
 */
class TemplateVersionResource extends JsonResource
{
    /**
     * Transform resource thành array
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'template_id' => $this->template_id,
            'version' => $this->version,
            'note' => $this->note,
            
            // Version structure (có thể ẩn để giảm payload)
            'json_body' => $this->when(
                $request->get('include_body', false),
                $this->json_body
            ),
            
            // Audit fields
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            
            // Template relationship
            'template' => new TemplateResource(
                $this->whenLoaded('template')
            )
        ];
    }
}