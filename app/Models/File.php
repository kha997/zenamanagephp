<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'name',
        'original_name',
        'path',
        'disk',
        'mime_type',
        'extension',
        'size',
        'hash',
        'type',
        'category',
        'project_id',
        'task_id',
        'metadata',
        'tags',
        'is_public',
        'is_active',
        'download_count',
        'last_accessed_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'tags' => 'array',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'download_count' => 'integer',
        'last_accessed_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function versions()
    {
        return $this->hasMany(FileVersion::class);
    }

    public function currentVersion()
    {
        return $this->hasOne(FileVersion::class)->where('is_current', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeByProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeByTask($query, int $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    public function getFormattedSizeAttribute(): string
    {
        return $this->formatBytes($this->size);
    }

    public function getFileIconAttribute(): string
    {
        return $this->getFileIcon($this->extension);
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('files.download', $this->id);
    }

    public function getPreviewUrlAttribute(): string
    {
        if ($this->isPreviewable()) {
            return route('files.preview', $this->id);
        }
        return $this->download_url;
    }

    public function incrementDownloadCount(): void
    {
        $this->increment('download_count');
        $this->update(['last_accessed_at' => now()]);
    }

    public function isPreviewable(): bool
    {
        $previewableTypes = ['image', 'pdf', 'text'];
        $previewableExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'md'];
        
        return in_array($this->type, $previewableTypes) || 
               in_array(strtolower($this->extension), $previewableExtensions);
    }

    public function isImage(): bool
    {
        return $this->type === 'image' || 
               in_array(strtolower($this->extension), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
    }

    public function isDocument(): bool
    {
        return $this->type === 'document' || 
               in_array(strtolower($this->extension), ['pdf', 'doc', 'docx', 'txt', 'md', 'rtf']);
    }

    public function isVideo(): bool
    {
        return $this->type === 'video' || 
               in_array(strtolower($this->extension), ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm']);
    }

    public function isAudio(): bool
    {
        return $this->type === 'audio' || 
               in_array(strtolower($this->extension), ['mp3', 'wav', 'flac', 'aac', 'ogg']);
    }

    public function isArchive(): bool
    {
        return $this->type === 'archive' || 
               in_array(strtolower($this->extension), ['zip', 'rar', '7z', 'tar', 'gz']);
    }

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    protected function getFileIcon(string $extension): string
    {
        $iconMap = [
            // Documents
            'pdf' => 'fas fa-file-pdf text-red-500',
            'doc' => 'fas fa-file-word text-blue-500',
            'docx' => 'fas fa-file-word text-blue-500',
            'txt' => 'fas fa-file-alt text-gray-500',
            'md' => 'fas fa-file-alt text-gray-500',
            'rtf' => 'fas fa-file-alt text-gray-500',
            
            // Spreadsheets
            'xls' => 'fas fa-file-excel text-green-500',
            'xlsx' => 'fas fa-file-excel text-green-500',
            'csv' => 'fas fa-file-csv text-green-500',
            
            // Presentations
            'ppt' => 'fas fa-file-powerpoint text-orange-500',
            'pptx' => 'fas fa-file-powerpoint text-orange-500',
            
            // Images
            'jpg' => 'fas fa-file-image text-purple-500',
            'jpeg' => 'fas fa-file-image text-purple-500',
            'png' => 'fas fa-file-image text-purple-500',
            'gif' => 'fas fa-file-image text-purple-500',
            'webp' => 'fas fa-file-image text-purple-500',
            'svg' => 'fas fa-file-image text-purple-500',
            
            // Videos
            'mp4' => 'fas fa-file-video text-red-600',
            'avi' => 'fas fa-file-video text-red-600',
            'mov' => 'fas fa-file-video text-red-600',
            'wmv' => 'fas fa-file-video text-red-600',
            'flv' => 'fas fa-file-video text-red-600',
            'webm' => 'fas fa-file-video text-red-600',
            
            // Audio
            'mp3' => 'fas fa-file-audio text-yellow-500',
            'wav' => 'fas fa-file-audio text-yellow-500',
            'flac' => 'fas fa-file-audio text-yellow-500',
            'aac' => 'fas fa-file-audio text-yellow-500',
            'ogg' => 'fas fa-file-audio text-yellow-500',
            
            // Archives
            'zip' => 'fas fa-file-archive text-gray-600',
            'rar' => 'fas fa-file-archive text-gray-600',
            '7z' => 'fas fa-file-archive text-gray-600',
            'tar' => 'fas fa-file-archive text-gray-600',
            'gz' => 'fas fa-file-archive text-gray-600',
            
            // Code
            'js' => 'fas fa-file-code text-yellow-400',
            'php' => 'fas fa-file-code text-purple-400',
            'html' => 'fas fa-file-code text-orange-400',
            'css' => 'fas fa-file-code text-blue-400',
            'json' => 'fas fa-file-code text-green-400',
            'xml' => 'fas fa-file-code text-red-400',
        ];
        
        return $iconMap[strtolower($extension)] ?? 'fas fa-file text-gray-500';
    }
}
