<?php

declare(strict_types=1);

namespace AIEA\AI;

use RuntimeException;

final class ProviderException extends RuntimeException
{
    public function __construct(string $message, public readonly string $safeCode = 'provider_error')
    {
        parent::__construct($message);
    }
}
