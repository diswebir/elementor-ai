<?php

declare(strict_types=1);

namespace AIEA\Database;

final class PlanRepository
{
    public function __construct(private readonly TableNames $tables)
    {
    }

    /** @param array<string, mixed> $plan */
    public function create(string $conversationId, array $plan): string
    {
        global $wpdb;
        $id = wp_generate_uuid4();
        $json = wp_json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $now = current_time('mysql', true);
        $wpdb->insert(
            $this->tables->get('plans'),
            [
                'id' => $id,
                'conversation_id' => $conversationId,
                'version' => 1,
                'plan_json' => $json,
                'plan_hash' => hash('sha256', (string) $json),
                'risk_level' => $plan['risk_level'],
                'approval_state' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        return $id;
    }

    /** @return array<string, mixed>|null */
    public function findForUser(string $planId, int $userId): ?array
    {
        global $wpdb;
        $plans = $this->tables->get('plans');
        $conversations = $this->tables->get('conversations');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, c.page_id, c.user_id, c.context_hash, c.context_scope FROM {$plans} p INNER JOIN {$conversations} c ON c.id = p.conversation_id WHERE p.id = %s AND c.user_id = %d",
            $planId,
            $userId,
        ), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /** @param list<string> $actionIds */
    public function approve(string $planId, array $actionIds): void
    {
        global $wpdb;
        $wpdb->update(
            $this->tables->get('plans'),
            [
                'approval_state' => 'approved',
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $planId],
        );
    }
}
