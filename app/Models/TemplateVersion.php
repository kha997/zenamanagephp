<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TemplateVersion Model
 * 
 * Lưu trữ lịch sử versions của templates
 * Đảm bảo không mất dữ liệu khi template được cập nhật
 * 
 * @property string $id
 * @property string $template_id
 * @property int $version
 * @property array $json_body
 * @property string|null $note
 * @property string|null $created_by
 */
class TemplateVersion extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'template_versions';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Các trường có thể mass assignment
     */
    protected $fillable = [
        'template_id',
        'version',
        'json_body',
        'note',
        'created_by'
    ];

    /**
     * Các trường cần cast kiểu dữ liệu
     */
    protected $casts = [
        'json_body' => 'array',
        'version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relationship với template chính
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    /**
     * Scope để lấy version mới nhất
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('version', 'desc');
    }

    /**
     * Scope để lấy version cụ thể
     */
    public function scopeByVersion($query, int $version)
    {
        return $query->where('version', $version);
    }

    /**
     * Kiểm tra xem có phải version mới nhất không
     */
    public function isLatestVersion(): bool
    {
        return $this->version === $this->template->version;
    }

    /**
     * So sánh với version khác để xem có thay đổi gì
     */
    public function compareWith(TemplateVersion $otherVersion): array
    {
        $changes = [];
        
        // So sánh json_body
        if ($this->json_body !== $otherVersion->json_body) {
            $changes['json_body'] = [
                'from' => $otherVersion->json_body,
                'to' => $this->json_body
            ];
        }
        
        return $changes;
    }

    /**
     * Tạo factory instance mới cho model này
     */
    protected static function newFactory()
    {
        return \Database\Factories\Src\WorkTemplate\Models\TemplateVersionFactory::new();
    }
}