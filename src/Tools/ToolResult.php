<?php

declare(strict_types=1);

namespace AIEA\Tools;

final readonly class ToolResult
{
    /** @param array<string, mixed> $data */
    public function __construct(public array $data, public string $summary)
    {
    }
}
