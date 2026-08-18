<?php

declare(strict_types=1);

namespace AIEA\AI;

final class OpenAICompatibleProvider implements ProviderInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly SecretManager $secrets,
        private readonly EndpointValidator $endpointValidator,
    ) {
    }

    public function id(): string
    {
        return (string) ($this->config['provider_type'] ?? 'openai_compatible');
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(true, true, false, true);
    }

    public function send(array $messages, AIRequestOptions $options): AIResponse
    {
        if ($options->stream) {
            throw new ProviderException('Use stream() for streaming requests.', 'invalid_request');
        }

        $response = $this->request($messages, $options, false);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new ProviderException('Provider returned an invalid JSON response.', 'invalid_provider_response');
        }

        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if (!is_string($content)) {
            throw new ProviderException('Provider response did not include assistant content.', 'invalid_provider_response');
        }

        $usage = is_array($decoded['usage'] ?? null) ? $decoded['usage'] : [];
        return new AIResponse($content, $decoded, [
            'input_tokens' => absint($usage['prompt_tokens'] ?? 0),
            'output_tokens' => absint($usage['completion_tokens'] ?? 0),
        ], (string) wp_remote_retrieve_header($response, 'x-request-id'));
    }

    public function stream(array $messages, AIRequestOptions $options, callable $onEvent): void
    {
        // WordPress HTTP API does not expose portable incremental streaming across transports.
        // This safely falls back to a completed response event; execution remains backend-controlled.
        $response = $this->send($messages, new AIRequestOptions($options->model, $options->temperature, $options->maxTokens, false, $options->responseFormat));
        $onEvent(['type' => 'message.completed', 'content' => $response->content, 'request_id' => $response->requestId]);
    }

    public function models(): array
    {
        $model = trim((string) ($this->config['model'] ?? ''));
        if ($model === '') {
            return [];
        }

        return [new ModelDefinition($model, $model, $this->capabilities())];
    }

    public function testConnection(): ProviderHealth
    {
        $startedAt = microtime(true);
        try {
            $this->endpointValidator->assertSafe($this->endpoint());
            $response = wp_remote_get($this->endpointBase(), [
                'timeout' => min(15, max(5, absint($this->config['request_timeout'] ?? 30))),
                'redirection' => 0,
                'reject_unsafe_urls' => true,
                'headers' => $this->headers(),
            ]);
            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            if (is_wp_error($response)) {
                return new ProviderHealth(false, $latency, 'Connection failed. Check the endpoint and key.', null);
            }

            $code = wp_remote_retrieve_response_code($response);
            return new ProviderHealth($code >= 200 && $code < 500, $latency, $code < 500 ? 'Connection reached provider.' : 'Provider returned a server error.', (string) wp_remote_retrieve_header($response, 'x-request-id'));
        } catch (ProviderException $exception) {
            return new ProviderHealth(false, (int) round((microtime(true) - $startedAt) * 1000), $exception->getMessage());
        }
    }

    /**
     * @param list<AIMessage> $messages
     * @return array<string, mixed>
     */
    private function request(array $messages, AIRequestOptions $options, bool $stream): array
    {
        $endpoint = $this->endpoint();
        $this->endpointValidator->assertSafe($endpoint, !empty($this->config['development_mode']));
        $secret = $this->secrets->retrieve();
        if ($secret === null) {
            throw new ProviderException('Provider API key has not been configured.', 'missing_provider_secret');
        }

        $body = [
            'model' => $options->model,
            'messages' => array_map(static fn (AIMessage $message): array => $message->toArray(), $messages),
            'temperature' => $options->temperature,
            'max_tokens' => $options->maxTokens,
            'stream' => $stream,
        ];
        if ($options->responseFormat !== []) {
            $body['response_format'] = $options->responseFormat;
        }

        $response = wp_remote_post($endpoint, [
            'timeout' => min(120, max(5, $options->timeout ?? absint($this->config['request_timeout'] ?? 30))),
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'headers' => $this->headers(),
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            $code = (string) $response->get_error_code();
            $message = $code === 'http_request_failed'
                ? 'Provider request timed out or the connection was interrupted. Retry once or increase the provider timeout.'
                : 'Provider request failed. Please retry later.';
            throw new ProviderException($message, 'provider_network_error');
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code === 429) {
            throw new ProviderException('Provider rate limit reached. Please retry later.', 'provider_rate_limited');
        }
        if ($code < 200 || $code >= 300) {
            throw new ProviderException('Provider rejected the request.', 'provider_http_error');
        }

        return $response;
    }

    private function endpointBase(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/');
    }

    private function endpoint(): string
    {
        return $this->endpointBase() . '/chat/completions';
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        $secret = $this->secrets->retrieve();
        return [
            'Authorization' => 'Bearer ' . ($secret ?? ''),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
}
