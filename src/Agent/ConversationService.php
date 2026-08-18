<?php

declare(strict_types=1);

namespace AIEA\Agent;

use AIEA\AI\AIManager;
use AIEA\AI\AIRequestOptions;
use AIEA\Database\ConversationRepository;
use RuntimeException;

final class ConversationService
{
    public function __construct(
        private readonly AIManager $ai,
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
        $response = $this->ai->requestStructured(
            $this->prompts->askMessages($request, $context['data']),
            new AIRequestOptions((string) $conversation['model_id'], 0.3, 1200),
        );
        return $response->content;
    }
}
