<?php

declare(strict_types=1);

namespace AIEA\Rest;

use AIEA\Agent\ContextService;
use AIEA\Database\ConversationRepository;
use WP_REST_Request;

final class SessionController
{
    public function __construct(
        private readonly ContextService $context,
        private readonly ConversationRepository $conversations,
        private readonly RestResponder $response,
    ) {
    }

    public function create(WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $scope = sanitize_key((string) $request->get_param('scope')) ?: 'current';
            if (!in_array($scope, ['current', 'site', 'project'], true)) {
                return $this->response->error('aiea_invalid_scope', __('Invalid context scope.', 'ai-elementor-agent'));
            }
            $postId = absint($request->get_param('post_id'));
            $context = $this->context->collect($postId, $scope, sanitize_text_field((string) $request->get_param('selection_id')) ?: null);
            $session = $this->conversations->create(get_current_user_id(), $postId, $scope, $context['hash'], sanitize_text_field((string) $request->get_param('model')));
            return $this->response->success(['session' => $session, 'context_summary' => $context['data']]);
        } catch (\Throwable $exception) {
            return $this->response->error('aiea_session_creation_failed', __('A secure session could not be created for this page.', 'ai-elementor-agent'), 409);
        }
    }
}
