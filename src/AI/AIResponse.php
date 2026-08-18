<?php

declare(strict_types=1);

namespace AIEA\AI;

final readonly class AIResponse
{
    /** @param array<string, mixed> $raw
     *  @param array<string, int> $usage
     */
    public function __construct(
        public string $content,
        public array $raw,
        public array $usage,
        public string $requestId,
    ) {
    }
}
