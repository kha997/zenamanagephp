<?php declare(strict_types=1);

namespace Tests\Traits;

trait RouteNameTrait
{
    protected function v1(string $name, array $params = [], array $query = []): string
    {
        return $this->namedRoute("api.v1.{$name}", $params, $query);
    }

    protected function zena(string $name, array $params = [], array $query = []): string
    {
        return $this->namedRoute("api.zena.{$name}", $params, $query);
    }

    protected function namedRoute(string $name, array $params = [], array $query = []): string
    {
        $url = route($name, $params, false);
        if ($query === []) {
            return $url;
        }

        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        if ($queryString === '') {
            return $url;
        }

        return "{$url}?{$queryString}";
    }
}
