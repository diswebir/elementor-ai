<?php

declare(strict_types=1);

namespace AIEA\Tools;

final readonly class ToolDefinition
{
    /** @param list<string> $requiredArguments */
    public function __construct(
        public string $name,
        public string $riskLevel,
        public array $requiredArguments,
        public bool $destructive = false,
    ) {
    }
}
