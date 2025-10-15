<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TemplateVersion Model
 * 
 * Quản lý các phiên bản của templates để theo dõi lịch sử thay đổi
 * 
 * @property string $id ULID primary key
 * @property string $template_id Template ID
 * @property int $version Version number
 * @property string $name Version name
 * @property string $description Version description
 * @property array $template_data Template data at this version
 * @property array $changes Changes made in this version
 * @property string $created_by Creator user ID
 * @property boolean $is_active Active version flag
 */
class TemplateVersion extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'template_versions';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'template_id',
        'version',
        'name',
        'description',
        'template_data',
        'changes',
        'created_by',
        'is_active'
    ];

    protected $casts = [
        'version' => 'integer',
        'template_data' => 'array',
        'changes' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $attributes = [
        'is_active' => false
    ];

    /**
     * Relationships
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scopes
     */
    public function scopeByTemplate($query, string $templateId)
    {
        return $query->where('template_id', $templateId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('version', 'desc');
    }

    /**
     * Version operations
     */
    public function activate(): bool
    {
        // Deactivate all other versions of this template
        self::where('template_id', $this->template_id)
            ->where('id', '!=', $this->id)
            ->update(['is_active' => false]);

        // Activate this version
        $this->is_active = true;
        return $this->save();
    }

    public function getVersionName(): string
    {
        return $this->name ?: "Version {$this->version}";
    }
}