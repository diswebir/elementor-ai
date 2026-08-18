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

    public function sendTestMessage(string $message): AIResponse
    {
        $provider = $this->providers->current();
        $models = $provider->models();
        $model = $models[0]->id ?? '';
        if ($model === '') {
            throw new ProviderException('Provider model has not been configured.', 'missing_provider_model');
        }

        return $provider->send(
            [
                new AIMessage('system', 'You are a provider connectivity test. Answer the user message briefly and do not call tools.'),
                new AIMessage('user', $message),
            ],
            new AIRequestOptions($model, 0.0, 256, false),
        );
    }
}
