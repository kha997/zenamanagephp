<?php declare(strict_types=1);

namespace Src\CoreProject\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * User API Resource
 * Transform User model data for API responses
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->id, // Thêm trường ulid để nhất quán với các Resource khác
            'name' => $this->name,
            'email' => $this->email,
            'tenant_id' => $this->tenant_id,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}