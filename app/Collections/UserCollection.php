<?php declare(strict_types=1);

namespace App\Collections;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class UserCollection extends Collection
{
    /**
     * Determine if a user exists in the collection using identity.
     */
    public function contains($key, $operator = null, $value = null)
    {
        if (func_num_args() === 1 && $key instanceof Model) {
            $keyValue = (string) $key->getKey();

            return $this->first(fn (Model $item) => (string) $item->getKey() === $keyValue && $item->getTable() === $key->getTable()) !== null;
        }

        return parent::contains($key, $operator, $value);
    }
}
