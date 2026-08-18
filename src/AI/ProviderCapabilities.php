<?php

declare(strict_types=1);

namespace AIEA\AI;

final readonly class ProviderCapabilities
{
    public function __construct(
        public bool $tools,
        public bool $streaming,
        public bool $vision,
        public bool $structuredOutput,
    ) {
    }

    /** @return array<string, bool> */
    public function toArray(): array
    {
        return [
            'tools' => $this->tools,
            'streaming' => $this->streaming,
            'vision' => $this->vision,
            'structured_output' => $this->structuredOutput,
        ];
    }
}
