<?php

declare(strict_types=1);

namespace AIEA\Rest;

use WP_REST_Server;

final class Routes
{
    public function __construct(
        private readonly Permission $permission,
        private readonly ContextController $context,
        private readonly SessionController $sessions,
        private readonly ProviderController $providers,
        private readonly ChatController $chat,
        private readonly PlanController $plans,
        private readonly ExecutionController $execution,
    ) {
    }

    public function register(): void
    {
        register_rest_route('ai-elementor/v1', '/context', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this->context, 'get'],
            'permission_callback' => [$this->permission, 'usePage'],
            'args' => $this->pageArguments(),
        ]);
        register_rest_route('ai-elementor/v1', '/sessions', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this->sessions, 'create'],
            'permission_callback' => [$this->permission, 'usePage'],
            'args' => $this->pageArguments(),
        ]);
        register_rest_route('ai-elementor/v1', '/chat', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this->chat, 'send'],
            'permission_callback' => [$this->permission, 'usePage'],
            'args' => array_merge($this->pageArguments(), $this->messageArguments()),
        ]);
        register_rest_route('ai-elementor/v1', '/plans', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this->plans, 'create'],
            'permission_callback' => [$this->permission, 'usePage'],
            'args' => array_merge($this->pageArguments(), $this->messageArguments()),
        ]);
        register_rest_route('ai-elementor/v1', '/plans/(?P<plan_id>[a-f0-9-]{36})/approve', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this->plans, 'approve'],
            'permission_callback' => [$this->permission, 'executePage'],
            'args' => array_merge($this->pageArguments(), [
                'plan_id' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'context_hash' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'action_ids' => ['required' => true, 'validate_callback' => static fn (mixed $value): bool => is_array($value)],
                'idempotency_key' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
            ]),
        ]);
        register_rest_route('ai-elementor/v1', '/jobs/(?P<job_id>[a-f0-9-]{36})', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this->execution, 'status'],
            'permission_callback' => [$this->permission, 'usePage'],
            'args' => array_merge($this->pageArguments(), ['job_id' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field']]),
        ]);
        register_rest_route('ai-elementor/v1', '/jobs/(?P<job_id>[a-f0-9-]{36})/next', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this->execution, 'next'],
            'permission_callback' => [$this->permission, 'executePage'],
            'args' => array_merge($this->pageArguments(), ['job_id' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field']]),
        ]);
        register_rest_route('ai-elementor/v1', '/jobs/(?P<job_id>[a-f0-9-]{36})/rollback', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this->execution, 'rollback'],
            'permission_callback' => [$this->permission, 'executePage'],
            'args' => array_merge($this->pageArguments(), [
                'job_id' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'snapshot_id' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            ]),
        ]);
        register_rest_route('ai-elementor/v1', '/providers/test', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this->providers, 'test'],
            'permission_callback' => [$this->permission, 'manage'],
            'args' => [
                'message' => [
                    'required' => false,
                    'validate_callback' => static fn (mixed $value): bool => is_string($value) && mb_strlen($value) <= 1000,
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
            ],
        ]);
        register_rest_route('ai-elementor/v1', '/providers/test/logs', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this->providers, 'logs'],
            'permission_callback' => [$this->permission, 'manage'],
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    private function pageArguments(): array
    {
        return [
            'post_id' => ['required' => true, 'validate_callback' => static fn (mixed $value): bool => absint($value) > 0, 'sanitize_callback' => 'absint'],
            'scope' => ['required' => false, 'sanitize_callback' => 'sanitize_key'],
            'selection_id' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
            'model' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function messageArguments(): array
    {
        return [
            'session_id' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'context_hash' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'message' => ['required' => true, 'validate_callback' => static fn (mixed $value): bool => is_string($value) && mb_strlen($value) <= 8000, 'sanitize_callback' => 'sanitize_textarea_field'],
        ];
    }
}
