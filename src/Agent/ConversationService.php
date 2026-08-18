<?php

declare(strict_types=1);

namespace AIEA\Agent;

use AIEA\AI\AIManager;
use AIEA\AI\AIRequestOptions;
use AIEA\Admin\Settings;
use AIEA\Database\ConversationRepository;
use RuntimeException;

final class ConversationService
{
    public function __construct(
        private readonly AIManager $ai,
        private readonly Settings $settings,
        private readonly ContextService $context,
        private readonly ConversationRepository $conversations,
        private readonly PromptBuilder $prompts,
    ) {
    }

    public function ask(int $userId, int $postId, string $sessionId, string $contextHash, string $request): string
    {
        $conversation = $this->conversations->findOwned($sessionId, $userId);
        if ($conversation === null || absint($conversation['page_id']) !== $postId) {
            throw new RuntimeException('Conversation is unavailable.');
        }
        $context = $this->context->collect($postId, (string) $conversation['context_scope']);
        if (!hash_equals($context['hash'], $contextHash)) {
            throw new RuntimeException('Page context changed. Refresh context before asking.');
        }
        $model = trim((string) $conversation['model_id']);
        if ($model === '') {
            $model = trim((string) ($this->settings->all()['model'] ?? ''));
        }
        if ($model === '') {
            throw new RuntimeException('No provider model is configured. Save a model in the plugin settings first.');
        }

        $response = $this->ai->requestStructured(
            $this->prompts->askMessages($request, $context['data']),
            new AIRequestOptions($model, 0.3, 1200),
        );
        return $response->content;
    }
}
