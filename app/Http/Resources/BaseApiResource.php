<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base API Resource với các helper methods chung
 */
abstract class BaseApiResource extends JsonResource
{
    /**
     * Format ULID for API response
     *
     * @param string|null $ulid
     * @return string|null
     */
    protected function formatUlid(?string $ulid): ?string
    {
        return $ulid;
    }

    /**
     * Format datetime for API response
     *
     * @param mixed $datetime
     * @return string|null
     */
    protected function formatDateTime($datetime): ?string
    {
        if (!$datetime) {
            return null;
        }
        
        return $datetime->toISOString();
    }

    /**
     * Format date for API response
     *
     * @param mixed $date
     * @return string|null
     */
    protected function formatDate($date): ?string
    {
        if (!$date) {
            return null;
        }
        
        return $date->format('Y-m-d');
    }

    /**
     * Format decimal for API response
     *
     * @param mixed $decimal
     * @param int $precision
     * @return float|null
     */
    protected function formatDecimal($decimal, int $precision = 2): ?float
    {
        if ($decimal === null) {
            return null;
        }
        
        return round((float) $decimal, $precision);
    }

    /**
     * Include relationship data conditionally
     *
     * @param string $relationship
     * @param string $resourceClass
     * @return mixed
     */
    protected function includeRelationship(string $relationship, string $resourceClass)
    {
        return $this->when(
            $this->relationLoaded($relationship),
            function () use ($relationship, $resourceClass) {
                $relation = $this->$relationship;
                
                if ($relation === null) {
                    return null;
                }
                
                if ($relation instanceof \Illuminate\Database\Eloquent\Collection) {
                    return $resourceClass::collection($relation);
                }
                
                return new $resourceClass($relation);
            }
        );
    }
}
