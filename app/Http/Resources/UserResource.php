<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform User model into JSON response
 * 
 * @property \App\Models\User $resource
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'tenant_id' => $this->tenant_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Security - chỉ hiển thị thông tin nhạy cảm cho chính user đó hoặc admin
            $this->mergeWhen(
                $request->user()?->id === $this->id || $request->user()?->can('view-user-details'),
                [
                    'email_verified_at' => $this->email_verified_at?->toISOString(),
                    'last_login_at' => $this->last_login_at?->toISOString(),
                ]
            ),
            
            // Conditional relationships
            'tenant' => $this->whenLoaded('tenant', function () {
                return [
                    'id' => $this->tenant->id,
                    'name' => $this->tenant->name,
                ];
            }),
            
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'scope' => $role->scope,
                    ];
                });
            }),
        ];
    }
}