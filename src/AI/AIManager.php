<?php

declare(strict_types=1);

namespace AIEA\AI;

final class AIManager
{
    public function __construct(private readonly ProviderRegistry $providers)
    {
    }

    /** @param list<AIMessage> $messages */
    public function requestStructured(array $messages, AIRequestOptions $options): AIResponse
    {
        $provider = $this->providers->current();
        if (!$provider->capabilities()->structuredOutput) {
            throw new ProviderException('Selected provider does not support structured output.', 'structured_output_unavailable');
        }

        return $provider->send($messages, $options);
    }

    public function testCurrentProvider(): ProviderHealth
    {
        return $this->providers->current()->testConnection();
    }
}
