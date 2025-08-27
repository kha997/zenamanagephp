<?php
declare(strict_types=1);

namespace Src\Foundation\Traits;

use Src\Foundation\Foundation;

/**
 * Trait để quản lý tag đa cấp
 */
trait HasTags {
    /**
     * Lấy tags dạng array
     * 
     * @return array
     */
    public function getTagsArrayAttribute(): array {
        if (empty($this->tag_path)) {
            return [];
        }
        
        return Foundation::parseTagPath($this->tag_path);
    }
    
    /**
     * Set tags từ array
     * 
     * @param array $tags
     * @return void
     */
    public function setTagsArray(array $tags): void {
        $this->tag_path = Foundation::createTagPath($tags);
    }
    
    /**
     * Thêm tag
     * 
     * @param string $tag
     * @return void
     */
    public function addTag(string $tag): void {
        $tags = $this->getTagsArrayAttribute();
        
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->setTagsArray($tags);
        }
    }
    
    /**
     * Xóa tag
     * 
     * @param string $tag
     * @return void
     */
    public function removeTag(string $tag): void {
        $tags = $this->getTagsArrayAttribute();
        $tags = array_filter($tags, fn($t) => $t !== $tag);
        $this->setTagsArray(array_values($tags));
    }
    
    /**
     * Kiểm tra có tag không
     * 
     * @param string $tag
     * @return bool
     */
    public function hasTag(string $tag): bool {
        return in_array($tag, $this->getTagsArrayAttribute());
    }
    
    /**
     * Scope để tìm theo tag
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $tag
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithTag($query, string $tag) {
        return $query->where('tag_path', 'LIKE', '%' . $tag . '%');
    }
    
    /**
     * Scope để tìm theo tag path
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $tagPath
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithTagPath($query, string $tagPath) {
        return $query->where('tag_path', $tagPath);
    }
}