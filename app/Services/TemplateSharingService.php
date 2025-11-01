<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * TemplateSharingService
 * 
 * Service class để xử lý template sharing features
 * Bao gồm public/private sharing, sharing permissions, và sharing analytics
 */
class TemplateSharingService
{
    /**
     * Make template public
     */
    public function makePublic(Template $template, string $userId): Template
    {
        DB::beginTransaction();
        
        try {
            // Check if user has permission to make template public
            if ($template->created_by !== $userId) {
                throw new \Exception('Only template creator can make template public');
            }

            $template->update([
                'is_public' => true,
                'status' => Template::STATUS_PUBLISHED,
                'updated_by' => $userId
            ]);

            DB::commit();

            Log::info('Template made public', [
                'template_id' => $template->id,
                'user_id' => $userId,
                'tenant_id' => $template->tenant_id
            ]);

            return $template->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to make template public', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            throw $e;
        }
    }

    /**
     * Make template private
     */
    public function makePrivate(Template $template, string $userId): Template
    {
        DB::beginTransaction();
        
        try {
            // Check if user has permission to make template private
            if ($template->created_by !== $userId) {
                throw new \Exception('Only template creator can make template private');
            }

            $template->update([
                'is_public' => false,
                'updated_by' => $userId
            ]);

            DB::commit();

            Log::info('Template made private', [
                'template_id' => $template->id,
                'user_id' => $userId,
                'tenant_id' => $template->tenant_id
            ]);

            return $template->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to make template private', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            throw $e;
        }
    }

    /**
     * Share template with specific users
     */
    public function shareWithUsers(Template $template, array $userIds, string $sharerId): array
    {
        DB::beginTransaction();
        
        try {
            $sharedTemplates = [];
            
            foreach ($userIds as $userId) {
                // Create a copy of the template for the shared user
                $sharedTemplate = $template->replicate();
                $sharedTemplate->id = Str::ulid();
                $sharedTemplate->name = $template->name . ' (Shared)';
                $sharedTemplate->is_public = false;
                $sharedTemplate->status = Template::STATUS_DRAFT;
                $sharedTemplate->created_by = $userId;
                $sharedTemplate->updated_by = $userId;
                $sharedTemplate->usage_count = 0;
                $sharedTemplate->metadata = array_merge($template->metadata ?? [], [
                    'shared_by' => $sharerId,
                    'shared_at' => now()->toISOString(),
                    'original_template_id' => $template->id
                ]);
                $sharedTemplate->save();
                
                $sharedTemplates[] = $sharedTemplate;
            }

            DB::commit();

            Log::info('Template shared with users', [
                'template_id' => $template->id,
                'sharer_id' => $sharerId,
                'shared_with_count' => count($userIds),
                'tenant_id' => $template->tenant_id
            ]);

            return $sharedTemplates;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to share template with users', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'sharer_id' => $sharerId
            ]);
            throw $e;
        }
    }

    /**
     * Get shared templates for a user
     */
    public function getSharedTemplates(string $userId, string $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return Template::byTenant($tenantId)
            ->whereJsonContains('metadata->shared_by', $userId)
            ->orWhere(function ($query) use ($userId) {
                $query->where('created_by', $userId)
                      ->whereJsonContains('metadata->shared_by', '!=', null);
            })
            ->with(['creator', 'updater'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get templates shared by a user
     */
    public function getTemplatesSharedByUser(string $userId, string $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return Template::byTenant($tenantId)
            ->whereJsonContains('metadata->shared_by', $userId)
            ->with(['creator', 'updater'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get sharing analytics for a template
     */
    public function getTemplateSharingAnalytics(Template $template): array
    {
        $sharedTemplates = Template::byTenant($template->tenant_id)
            ->whereJsonContains('metadata->original_template_id', $template->id)
            ->get();

        $sharingStats = [
            'total_shares' => $sharedTemplates->count(),
            'unique_recipients' => $sharedTemplates->pluck('created_by')->unique()->count(),
            'shares_by_user' => $sharedTemplates->groupBy('created_by')->map->count(),
            'shares_over_time' => $sharedTemplates->groupBy(function ($template) {
                return $template->created_at->format('Y-m');
            })->map->count(),
            'most_recent_share' => $sharedTemplates->sortByDesc('created_at')->first(),
            'total_usage_from_shares' => $sharedTemplates->sum('usage_count')
        ];

        return $sharingStats;
    }

    /**
     * Get sharing analytics for a user
     */
    public function getUserSharingAnalytics(string $userId, string $tenantId): array
    {
        $userTemplates = Template::byTenant($tenantId)
            ->where('created_by', $userId)
            ->get();

        $sharedTemplates = $userTemplates->filter(function ($template) {
            return isset($template->metadata['shared_by']);
        });

        $templatesSharedByUser = $this->getTemplatesSharedByUser($userId, $tenantId);

        return [
            'total_templates' => $userTemplates->count(),
            'public_templates' => $userTemplates->where('is_public', true)->count(),
            'private_templates' => $userTemplates->where('is_public', false)->count(),
            'shared_templates' => $sharedTemplates->count(),
            'templates_shared_by_user' => $templatesSharedByUser->count(),
            'total_shares_made' => $templatesSharedByUser->sum(function ($template) {
                return Template::byTenant($tenantId)
                    ->whereJsonContains('metadata->original_template_id', $template->id)
                    ->count();
            }),
            'total_usage_from_shares' => $templatesSharedByUser->sum('usage_count'),
            'sharing_activity' => $templatesSharedByUser->groupBy(function ($template) {
                return $template->created_at->format('Y-m');
            })->map->count()
        ];
    }

    /**
     * Revoke sharing for a template
     */
    public function revokeSharing(Template $template, string $userId): bool
    {
        DB::beginTransaction();
        
        try {
            // Check if user has permission to revoke sharing
            if ($template->created_by !== $userId) {
                throw new \Exception('Only template creator can revoke sharing');
            }

            // Make template private
            $template->update([
                'is_public' => false,
                'updated_by' => $userId
            ]);

            // Remove sharing metadata from all shared copies
            Template::byTenant($template->tenant_id)
                ->whereJsonContains('metadata->original_template_id', $template->id)
                ->update([
                    'metadata' => DB::raw("JSON_REMOVE(metadata, '$.shared_by', '$.shared_at', '$.original_template_id')")
                ]);

            DB::commit();

            Log::info('Template sharing revoked', [
                'template_id' => $template->id,
                'user_id' => $userId,
                'tenant_id' => $template->tenant_id
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to revoke template sharing', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            throw $e;
        }
    }

    /**
     * Get public templates from other tenants (for discovery)
     */
    public function getPublicTemplatesFromOtherTenants(string $currentTenantId, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Template::where('tenant_id', '!=', $currentTenantId)
            ->where('is_public', true)
            ->where('status', Template::STATUS_PUBLISHED)
            ->where('is_active', true)
            ->with(['creator', 'tenant']);

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['tags'])) {
            $query->whereJsonContains('tags', $filters['tags']);
        }

        return $query->orderBy('usage_count', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->get();
    }
}
