<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FileVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_id',
        'user_id',
        'version_number',
        'path',
        'disk',
        'size',
        'hash',
        'change_description',
        'metadata',
        'is_current'
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_current' => 'boolean'
    ];

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function getFormattedSizeAttribute(): string
    {
        return $this->formatBytes($this->size);
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('files.version.download', [$this->file_id, $this->id]);
    }

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
