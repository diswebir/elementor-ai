<?php

declare(strict_types=1);

namespace AIEA\AI;

final readonly class ModelDefinition
{
    public function __construct(
        public string $id,
        public string $displayName,
        public ProviderCapabilities $capabilities,
        public ?int $contextWindow = null,
    ) {
    }
}
