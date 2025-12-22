<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'address' => $this->address,
            'city' => $this->city ?? null,
            'state' => $this->state ?? null,
            'country' => $this->country ?? null,
            'postal_code' => $this->postal_code ?? null,
            'lifecycle_stage' => $this->lifecycle_stage,
            'notes' => $this->notes,
            'tags' => $this->tags ?? [],
            'is_vip' => $this->is_vip ?? false,
            'credit_limit' => $this->credit_limit ?? 0,
            'payment_terms' => $this->payment_terms ?? null,
            'custom_fields' => $this->custom_fields ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'quotes' => $this->whenLoaded('quotes', function () {
                return $this->quotes->map(function ($quote) {
                    return [
                        'id' => $quote->id,
                        'title' => $quote->title,
                        'status' => $quote->status,
                        'total_amount' => $quote->total_amount,
                        'valid_until' => $quote->valid_until?->toDateString(),
                        'created_at' => $quote->created_at?->toISOString(),
                    ];
                });
            }),
            'projects' => $this->whenLoaded('projects', function () {
                return $this->projects->map(function ($project) {
                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'status' => $project->status,
                        'progress_percent' => $project->progress_pct, // Standardize: progress_pct → progress_percent
                        'budget_total' => $project->budget_total,
                        'start_date' => $project->start_date?->toDateString(),
                        'end_date' => $project->end_date?->toDateString(),
                    ];
                });
            }),
            'active_quotes' => $this->whenLoaded('activeQuotes', function () {
                return $this->activeQuotes->map(function ($quote) {
                    return [
                        'id' => $quote->id,
                        'title' => $quote->title,
                        'status' => $quote->status,
                        'total_amount' => $quote->total_amount,
                        'valid_until' => $quote->valid_until?->toDateString(),
                    ];
                });
            }),
        ];
    }
}
