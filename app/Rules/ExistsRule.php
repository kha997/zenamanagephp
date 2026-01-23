<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class ExistsRule implements Rule
{
    protected $table;
    protected $column;
    protected $whereColumn;
    protected $whereValue;

    /**
     * Create a new rule instance.
     */
    public function __construct(string $table, string $column = 'id', string $whereColumn = null, $whereValue = null)
    {
        $this->table = $table;
        $this->column = $column;
        $this->whereColumn = $whereColumn;
        $this->whereValue = $whereValue;
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value): bool
    {
        if ($value === '' || $value === null) {
            return true;
        }

        $query = DB::table($this->table)->where($this->column, $value);

        if ($this->whereColumn && $this->whereValue !== null) {
            $query->where($this->whereColumn, $this->whereValue);
        }

        return $query->exists();
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'The selected :attribute is invalid.';
    }
}
