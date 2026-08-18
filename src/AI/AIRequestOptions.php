<?php

declare(strict_types=1);

namespace AIEA\AI;

final readonly class AIRequestOptions
{
    /** @param array<string, mixed> $responseFormat */
    public function __construct(
        public string $model,
        public float $temperature = 0.2,
        public int $maxTokens = 3000,
        public bool $stream = false,
        public array $responseFormat = [],
    ) {
    }
}
