<?php

declare(strict_types=1);

namespace AIEA\AI;

final readonly class ProviderHealth
{
    public function __construct(
        public bool $healthy,
        public int $latencyMs,
        public string $message,
        public ?string $requestId = null,
    ) {
    }
}
