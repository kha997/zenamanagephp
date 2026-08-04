<?php declare(strict_types=1);

namespace App\Enums;

enum DocumentWorkflowStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /**
     * Chỉ DocumentWorkflowService được ghi 3 giá trị này. store()/update()/
     * createVersion() phải chặn mọi request có status đích nằm trong danh sách này.
     *
     * @return self[]
     */
    public static function reserved(): array
    {
        return [self::SUBMITTED, self::APPROVED, self::REJECTED];
    }

    /** @return string[] */
    public static function reservedValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::reserved());
    }

    public static function isReserved(string $value): bool
    {
        return in_array($value, self::reservedValues(), true);
    }
}
