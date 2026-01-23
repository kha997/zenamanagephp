<?php declare(strict_types=1);

namespace App\Cache;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\TagSet;

class TrackingArrayStore extends ArrayStore
{
    /**
     * Begin executing a new tags operation.
     */
    public function tags($names)
    {
        $tagNames = is_array($names) ? $names : func_get_args();
        return new TrackingTaggedCache($this, new TagSet($this, $tagNames));
    }
}
