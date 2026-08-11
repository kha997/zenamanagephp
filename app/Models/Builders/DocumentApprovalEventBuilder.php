<?php declare(strict_types=1);

namespace App\Models\Builders;

use App\Models\DocumentApprovalEvent;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/** @extends Builder<DocumentApprovalEvent> */
final class DocumentApprovalEventBuilder extends Builder
{
    private const FORBIDDEN_FORWARDED_WRITES = [
        'decrementeach',
        'incrementeach',
        'insert',
        'insertgetid',
        'insertorignore',
        'insertorignoreusing',
        'insertusing',
        'truncate',
        'updatefrom',
        'updateorinsert',
    ];

    /** @param array<string, mixed> $values */
    public function update(array $values)
    {
        $this->rejectWrite();
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     */
    public function updateOrCreate(array $attributes, array $values = [])
    {
        $this->rejectWrite();
    }

    /**
     * @param array<int, array<string, mixed>>|array<string, mixed> $values
     * @param array<int, string>|string $uniqueBy
     * @param array<int, string>|null $update
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        $this->rejectWrite();
    }

    /** @param array<int, string>|string|null $column */
    public function touch($column = null)
    {
        $this->rejectWrite();
    }

    /** @param array<string, mixed> $extra */
    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->rejectWrite();
    }

    /** @param array<string, mixed> $extra */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->rejectWrite();
    }

    public function delete()
    {
        $this->rejectWrite();
    }

    public function forceDelete()
    {
        $this->rejectWrite();
    }

    /** @param array<int, mixed> $parameters */
    public function __call($method, $parameters)
    {
        $normalizedMethod = strtolower((string) $method);

        if (in_array($normalizedMethod, self::FORBIDDEN_FORWARDED_WRITES, true)) {
            if ($normalizedMethod === 'insert' && $this->getModel()->allowsValidatedApprovalEventInsert()) {
                return parent::__call($method, $parameters);
            }

            $this->rejectWrite();
        }

        return parent::__call($method, $parameters);
    }

    private function rejectWrite(): never
    {
        throw new LogicException('Document approval events may only be created through validated model creation.');
    }
}
