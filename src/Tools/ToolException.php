<?php

declare(strict_types=1);

namespace AIEA\Tools;

use RuntimeException;

final class ToolException extends RuntimeException
{
    public function __construct(string $message, public readonly string $safeCode = 'tool_failed')
    {
        parent::__construct($message);
    }
}
