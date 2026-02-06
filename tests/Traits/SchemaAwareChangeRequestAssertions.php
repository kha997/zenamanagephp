<?php declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;

/**
 * Trait SchemaAwareChangeRequestAssertions
 *
 * Reuses the schema-aware response assertions shared across change request suites.
 */
trait SchemaAwareChangeRequestAssertions
{
    protected ?array $changeRequestColumns = null;

    protected function assertChangeRequestResponse(TestResponse $response, array $maybeFields = [], bool $expectSuccessInPayload = true): void
    {
        $structure = [];

        if ($expectSuccessInPayload) {
            $structure[] = 'success';
        }

        $structure[] = 'status';
        $structure['data'] = $this->buildChangeRequestDataStructure($maybeFields);

        $response->assertJsonStructure($structure);
    }

    protected function buildChangeRequestDataStructure(array $maybeFields): array
    {
        $fields = ['id', 'status'];

        foreach ($maybeFields as $field) {
            if ($this->hasChangeRequestColumn($field) && !in_array($field, $fields, true)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    protected function hasChangeRequestColumn(string $column): bool
    {
        return in_array($column, $this->getChangeRequestColumns(), true);
    }

    protected function getChangeRequestColumns(): array
    {
        return $this->changeRequestColumns ??= Schema::getColumnListing('change_requests');
    }
}
