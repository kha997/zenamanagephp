<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UniqueRule implements Rule
{
    protected $table;
    protected $column;
    protected $ignoreId;
    protected $ignoreColumn;

    /**
     * Create a new rule instance.
     */
    public function __construct(string $table, string $column = 'id', $ignoreId = null, string $ignoreColumn = 'id')
    {
        $this->table = $table;
        $this->column = $column;
        $this->ignoreId = $ignoreId;
        $this->ignoreColumn = $ignoreColumn;
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value): bool
    {
        $query = DB::table($this->table)->where($this->column, $value);

        if ($this->ignoreId !== null) {
            $query->where($this->ignoreColumn, '!=', $this->ignoreId);
        }

        return $query->count() === 0;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'The :attribute has already been taken.';
    }
}
