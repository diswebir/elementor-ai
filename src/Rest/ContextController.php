<?php

declare(strict_types=1);

namespace AIEA\Rest;

use AIEA\Agent\ContextService;
use WP_REST_Request;

final class ContextController
{
    public function __construct(private readonly ContextService $context, private readonly RestResponder $response)
    {
    }

    public function get(WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $scope = sanitize_key((string) $request->get_param('scope')) ?: 'current';
            if (!in_array($scope, ['current', 'site', 'project'], true)) {
                return $this->response->error('aiea_invalid_scope', __('Invalid context scope.', 'ai-elementor-agent'));
            }
            $snapshot = $this->context->collect(absint($request->get_param('post_id')), $scope, sanitize_text_field((string) $request->get_param('selection_id')) ?: null);
            return $this->response->success($snapshot);
        } catch (\Throwable $exception) {
            return $this->response->error('aiea_context_unavailable', __('Page context could not be collected safely.', 'ai-elementor-agent'), 409);
        }
    }
}
