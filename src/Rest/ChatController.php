<?php

declare(strict_types=1);

namespace AIEA\Rest;

use AIEA\Agent\ConversationService;
use WP_REST_Request;

final class ChatController
{
    public function __construct(private readonly ConversationService $chat, private readonly RestResponder $response)
    {
    }

    public function send(WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $content = $this->chat->ask(
                get_current_user_id(),
                absint($request->get_param('post_id')),
                sanitize_text_field((string) $request->get_param('session_id')),
                sanitize_text_field((string) $request->get_param('context_hash')),
                sanitize_textarea_field((string) $request->get_param('message')),
            );
            return $this->response->success(['message' => $content]);
        } catch (\Throwable $exception) {
            return $this->response->error('aiea_chat_failed', __('The assistant could not answer safely.', 'ai-elementor-ag'), 409);
        }
    }
}
