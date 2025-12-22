<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_preferences';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'preferences',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'preferences' => 'array',
    ];

    /**
     * Get the user that owns the preferences.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get a specific preference value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getPreference(string $key, $default = null)
    {
        return data_get($this->preferences, $key, $default);
    }

    /**
     * Set a specific preference value
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function setPreference(string $key, $value): bool
    {
        $preferences = $this->preferences ?? [];
        data_set($preferences, $key, $value);
        
        return $this->update(['preferences' => $preferences]);
    }

    /**
     * Check if focus mode is enabled for this user
     *
     * @return bool
     */
    public function isFocusModeEnabled(): bool
    {
        return $this->getPreference('feature_flags.ui.enable_focus_mode', false);
    }

    /**
     * Set focus mode preference
     *
     * @param bool $enabled
     * @return bool
     */
    public function setFocusMode(bool $enabled): bool
    {
        return $this->setPreference('feature_flags.ui.enable_focus_mode', $enabled);
    }

    /**
     * Check if rewards are enabled for this user
     *
     * @return bool
     */
    public function isRewardsEnabled(): bool
    {
        return $this->getPreference('feature_flags.ui.enable_rewards', false);
    }

    /**
     * Set rewards preference
     *
     * @param bool $enabled
     * @return bool
     */
    public function setRewards(bool $enabled): bool
    {
        return $this->setPreference('feature_flags.ui.enable_rewards', $enabled);
    }

    /**
     * Get UI theme preference
     *
     * @return string
     */
    public function getTheme(): string
    {
        return $this->getPreference('ui.theme', 'light');
    }

    /**
     * Set UI theme preference
     *
     * @param string $theme
     * @return bool
     */
    public function setTheme(string $theme): bool
    {
        return $this->setPreference('ui.theme', $theme);
    }

    /**
     * Get sidebar preference
     *
     * @return string
     */
    public function getSidebarState(): string
    {
        return $this->getPreference('ui.sidebar_state', 'expanded');
    }

    /**
     * Set sidebar preference
     *
     * @param string $state
     * @return bool
     */
    public function setSidebarState(string $state): bool
    {
        return $this->setPreference('ui.sidebar_state', $state);
    }
}
