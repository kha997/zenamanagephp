<?php declare(strict_types=1);

namespace App\Services\DocumentContext;

use InvalidArgumentException;

class DocumentContextRegistry
{
    /** @var array<string, DocumentContextProvider> */
    private array $providers;

    /**
     * @param list<DocumentContextProvider> $providers
     */
    public function __construct(array $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->slug()] = $provider;
        }
    }

    public function get(string $slug): DocumentContextProvider
    {
        if (!isset($this->providers[$slug])) {
            throw new InvalidArgumentException("Unknown document context: {$slug}");
        }

        return $this->providers[$slug];
    }

    /**
     * @return array<string, DocumentContextProvider>
     */
    public function all(): array
    {
        return $this->providers;
    }
}
