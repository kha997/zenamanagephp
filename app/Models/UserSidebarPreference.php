<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;

class UserSidebarPreference extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'user_sidebar_preferences';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'preferences',
        'is_enabled',
        'version',
    ];

    protected $casts = [
        'preferences' => 'array',
        'is_enabled' => 'boolean',
        'version' => 'integer',
    ];

    /**
     * Get the user that owns the preference.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Validate preferences structure.
     */
    public function validatePreferences(): bool
    {
        $validator = Validator::make($this->preferences, [
            'pinned_items' => 'array',
            'pinned_items.*' => 'string',
            'hidden_items' => 'array',
            'hidden_items.*' => 'string',
            'custom_order' => 'array',
            'custom_order.*' => 'string',
            'theme' => 'string|in:light,dark,auto',
            'compact_mode' => 'boolean',
            'show_badges' => 'boolean',
            'auto_expand_groups' => 'boolean',
        ]);

        return !$validator->fails();
    }

    /**
     * Get default preferences structure.
     */
    public static function getDefaultPreferences(): array
    {
        return [
            'pinned_items' => [],
            'hidden_items' => [],
            'custom_order' => [],
            'theme' => 'auto',
            'compact_mode' => false,
            'show_badges' => true,
            'auto_expand_groups' => false,
        ];
    }

    /**
     * Pin an item.
     */
    public function pinItem(string $itemId): void
    {
        $preferences = $this->preferences ?? self::getDefaultPreferences();
        
        if (!in_array($itemId, $preferences['pinned_items'])) {
            $preferences['pinned_items'][] = $itemId;
        }
        
        // Remove from hidden items if it was hidden
        $preferences['hidden_items'] = array_filter(
            $preferences['hidden_items'],
            fn($id) => $id !== $itemId
        );
        
        $this->update(['preferences' => $preferences]);
    }

    /**
     * Unpin an item.
     */
    public function unpinItem(string $itemId): void
    {
        $preferences = $this->preferences ?? self::getDefaultPreferences();
        
        $preferences['pinned_items'] = array_filter(
            $preferences['pinned_items'],
            fn($id) => $id !== $itemId
        );
        
        $this->update(['preferences' => $preferences]);
    }

    /**
     * Hide an item.
     */
    public function hideItem(string $itemId): void
    {
        $preferences = $this->preferences ?? self::getDefaultPreferences();
        
        if (!in_array($itemId, $preferences['hidden_items'])) {
            $preferences['hidden_items'][] = $itemId;
        }
        
        // Remove from pinned items if it was pinned
        $preferences['pinned_items'] = array_filter(
            $preferences['pinned_items'],
            fn($id) => $id !== $itemId
        );
        
        $this->update(['preferences' => $preferences]);
    }

    /**
     * Show an item.
     */
    public function showItem(string $itemId): void
    {
        $preferences = $this->preferences ?? self::getDefaultPreferences();
        
        $preferences['hidden_items'] = array_filter(
            $preferences['hidden_items'],
            fn($id) => $id !== $itemId
        );
        
        $this->update(['preferences' => $preferences]);
    }

    /**
     * Set custom order for items.
     */
    public function setCustomOrder(array $itemIds): void
    {
        $preferences = $this->preferences ?? self::getDefaultPreferences();
        $preferences['custom_order'] = $itemIds;
        
        $this->update(['preferences' => $preferences]);
    }

    /**
     * Update theme preference.
     */
    public function setTheme(string $theme): void
    {
        $preferences = $this->preferences ?? self::getDefaultPreferences();
        $preferences['theme'] = $theme;
        
        $this->update(['preferences' => $preferences]);
    }

    /**
     * Toggle compact mode.
     */
    public function toggleCompactMode(): void
    {
        $preferences = $this->preferences ?? self::getDefaultPreferences();
        $preferences['compact_mode'] = !$preferences['compact_mode'];
        
        $this->update(['preferences' => $preferences]);
    }

    /**
     * Toggle badges display.
     */
    public function toggleBadges(): void
    {
        $preferences = $this->preferences ?? self::getDefaultPreferences();
        $preferences['show_badges'] = !$preferences['show_badges'];
        
        $this->update(['preferences' => $preferences]);
    }

    /**
     * Toggle auto expand groups.
     */
    public function toggleAutoExpandGroups(): void
    {
        $preferences = $this->preferences ?? self::getDefaultPreferences();
        $preferences['auto_expand_groups'] = !$preferences['auto_expand_groups'];
        
        $this->update(['preferences' => $preferences]);
    }

    /**
     * Check if item is pinned.
     */
    public function isItemPinned(string $itemId): bool
    {
        $preferences = $this->preferences ?? self::getDefaultPreferences();
        return in_array($itemId, $preferences['pinned_items'] ?? []);
    }

    /**
     * Check if item is hidden.
     */
    public function isItemHidden(string $itemId): bool
    {
        $preferences = $this->preferences ?? self::getDefaultPreferences();
        return in_array($itemId, $preferences['hidden_items'] ?? []);
    }

    /**
     * Get custom order.
     */
    public function getCustomOrder(): array
    {
        $preferences = $this->preferences ?? self::getDefaultPreferences();
        return $preferences['custom_order'] ?? [];
    }

    /**
     * Get theme preference.
     */
    public function getTheme(): string
    {
        $preferences = $this->preferences ?? self::getDefaultPreferences();
        return $preferences['theme'] ?? 'auto';
    }

    /**
     * Check if compact mode is enabled.
     */
    public function isCompactMode(): bool
    {
        $preferences = $this->preferences ?? self::getDefaultPreferences();
        return $preferences['compact_mode'] ?? false;
    }

    /**
     * Check if badges are shown.
     */
    public function shouldShowBadges(): bool
    {
        $preferences = $this->preferences ?? self::getDefaultPreferences();
        return $preferences['show_badges'] ?? true;
    }

    /**
     * Check if groups should auto expand.
     */
    public function shouldAutoExpandGroups(): bool
    {
        $preferences = $this->preferences ?? self::getDefaultPreferences();
        return $preferences['auto_expand_groups'] ?? false;
    }

    /**
     * Reset preferences to default.
     */
    public function resetToDefault(): void
    {
        $this->update(['preferences' => self::getDefaultPreferences()]);
    }

    /**
     * Boot method to validate preferences on save.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->validatePreferences()) {
                throw new \InvalidArgumentException('Invalid preferences structure');
            }
            $model->version = ($model->version ?? 0) + 1;
        });

        static::updating(function ($model) {
            if (!$model->validatePreferences()) {
                throw new \InvalidArgumentException('Invalid preferences structure');
            }
            $model->version = ($model->version ?? 0) + 1;
        });
    }
}