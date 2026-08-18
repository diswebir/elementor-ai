<?php

declare(strict_types=1);

namespace AIEA\AI;

interface ProviderInterface
{
    public function id(): string;

    public function capabilities(): ProviderCapabilities;

    /** @param list<AIMessage> $messages */
    public function send(array $messages, AIRequestOptions $options): AIResponse;

    /** @param list<AIMessage> $messages */
    public function stream(array $messages, AIRequestOptions $options, callable $onEvent): void;

    /** @return list<ModelDefinition> */
    public function models(): array;

    public function testConnection(): ProviderHealth;
}
