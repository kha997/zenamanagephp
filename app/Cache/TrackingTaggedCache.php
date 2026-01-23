<?php declare(strict_types=1);

namespace App\Cache;

use Illuminate\Cache\TaggedCache;
use Illuminate\Support\Facades\Cache as CacheFacade;

class TrackingTaggedCache extends TaggedCache
{
    protected static array $tagKeyIndex = [];

    public function put($key, $value, $ttl = null)
    {
        $result = parent::put($key, $value, $ttl);
        $this->storeBaseKey($key, $value, $ttl);
        $this->recordTagForKey($key);

        return $result;
    }

    public function forever($key, $value)
    {
        $result = parent::forever($key, $value);
        $this->storeBaseKey($key, $value, null);
        $this->recordTagForKey($key);

        return $result;
    }

    public function putMany(array $values, $ttl = null)
    {
        $result = parent::putMany($values, $ttl);

        foreach ($values as $key => $value) {
            $this->storeBaseKey($key, $value, $ttl);
            $this->recordTagForKey($key);
        }

        return $result;
    }

    protected function storeBaseKey($key, $value, $ttl): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        $cache = CacheFacade::getFacadeRoot();
        $cache->put($key, $value, $ttl);
    }

    protected function recordTagForKey(string $key): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        $tags = $this->getTags()->getNames();

        foreach ($tags as $tag) {
            static::$tagKeyIndex[$tag][$key] = true;
        }
    }

    public static function collectKeysForTags(array $tags): array
    {
        if (empty($tags)) {
            return [];
        }

        $keys = [];

        foreach ($tags as $tag) {
            $tagKeys = static::$tagKeyIndex[$tag] ?? [];

            foreach ($tagKeys as $key => $_) {
                $keys[$key] = true;
            }

            unset(static::$tagKeyIndex[$tag]);
        }

        return array_keys($keys);
    }
}
