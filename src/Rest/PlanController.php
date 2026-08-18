<?php

declare(strict_types=1);

namespace AIEA\Rest;

use AIEA\Agent\PlanService;
use WP_REST_Request;

final class PlanController
{
    public function __construct(private readonly PlanService $plans, private readonly RestResponder $response)
    {
    }

    public function create(WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $result = $this->plans->create(
                get_current_user_id(),
                absint($request->get_param('post_id')),
                sanitize_text_field((string) $request->get_param('session_id')),
                sanitize_text_field((string) $request->get_param('context_hash')),
                sanitize_textarea_field((string) $request->get_param('message')),
            );
            return $this->response->success($result, 201);
        } catch (\Throwable $exception) {
            return $this->response->error('aiea_plan_failed', __('The plan could not be created or validated safely.', 'ai-elementor-agent'), 409);
        }
    }

    public function approve(WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $actionIds = $request->get_param('action_ids');
            $idempotency = (string) $request->get_header('Idempotency-Key');
            if ($idempotency === '') {
                $idempotency = sanitize_text_field((string) $request->get_param('idempotency_key'));
            }
            $result = $this->plans->approve(
                get_current_user_id(),
                absint($request->get_param('post_id')),
                sanitize_text_field((string) $request->get_param('plan_id')),
                sanitize_text_field((string) $request->get_param('context_hash')),
                is_array($actionIds) ? $actionIds : [],
                $idempotency,
            );
            return $this->response->success($result, 201);
        } catch (\Throwable $exception) {
            return $this->response->error('aiea_approval_failed', __('The plan approval is no longer valid.', 'ai-elementor-agent'), 409);
        }
    }
}
