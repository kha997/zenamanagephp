<?php declare(strict_types=1);

namespace App\Services\DocumentContext;

use Illuminate\Database\Eloquent\Model;

interface DocumentContextProvider
{
    public function slug(): string;

    public function label(): string;

    /**
     * @return list<array{key: string, type: string, label: string}>
     */
    public function keys(): array;

    /**
     * @return array<string, mixed>
     */
    public function build(Model $subject): array;

    /**
     * @return array<string, mixed>
     */
    public function sample(): array;
}
