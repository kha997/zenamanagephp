<?php

namespace App\Services\Dashboard;

final readonly class WidgetDataResult
{
    private function __construct(
        public string $state,
        public ?array $data,
        public ?array $error,
    ) {
    }

    public static function ready(array $data): self
    {
        return new self('ready', $data, null);
    }

    public static function unsupported(): self
    {
        return self::degraded('DASHBOARD.WIDGET_UNSUPPORTED', 'Widget data is not supported.', false);
    }

    public static function degraded(string $code, string $message, bool $retryable = false): self
    {
        return new self('degraded', null, [
            'code' => $code,
            'message' => $message,
            'retryable' => $retryable,
        ]);
    }
}
