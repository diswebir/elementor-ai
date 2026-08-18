<?php

declare(strict_types=1);

namespace AIEA\Audit;

use AIEA\Database\TableNames;

final class AuditLogger
{
    public function __construct(private readonly ?TableNames $tables = null)
    {
    }

    /** @param array<string, mixed> $context */
    public function log(string $event, string $status, array $context = []): void
    {
        global $wpdb;

        $table = ($this->tables ?? new TableNames())->get('audit_log');
        $wpdb->insert(
            $table,
            [
                'actor_id' => isset($context['actor_id']) ? absint($context['actor_id']) : get_current_user_id(),
                'page_id' => isset($context['page_id']) ? absint($context['page_id']) : null,
                'conversation_id' => isset($context['conversation_id']) ? sanitize_text_field((string) $context['conversation_id']) : null,
                'job_id' => isset($context['job_id']) ? sanitize_text_field((string) $context['job_id']) : null,
                'task_id' => isset($context['task_id']) ? sanitize_text_field((string) $context['task_id']) : null,
                'event' => sanitize_key($event),
                'arguments_hash' => isset($context['arguments_hash']) ? hash('sha256', (string) $context['arguments_hash']) : null,
                'result_summary' => isset($context['result_summary']) ? sanitize_text_field((string) $context['result_summary']) : null,
                'status' => sanitize_key($status),
                'duration_ms' => isset($context['duration_ms']) ? absint($context['duration_ms']) : null,
                'error_code' => isset($context['error_code']) ? sanitize_key((string) $context['error_code']) : null,
                'created_at' => current_time('mysql', true),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'],
        );
    }
}
