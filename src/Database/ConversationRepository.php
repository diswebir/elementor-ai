<?php

declare(strict_types=1);

namespace AIEA\Database;

final class ConversationRepository
{
    public function __construct(private readonly TableNames $tables)
    {
    }

    /** @return array<string, mixed> */
    public function create(int $userId, int $pageId, string $scope, string $contextHash, string $model = ''): array
    {
        global $wpdb;
        $id = wp_generate_uuid4();
        $now = current_time('mysql', true);
        $wpdb->insert(
            $this->tables->get('conversations'),
            [
                'id' => $id,
                'user_id' => $userId,
                'page_id' => $pageId,
                'provider_id' => null,
                'model_id' => sanitize_text_field($model),
                'context_scope' => sanitize_key($scope),
                'context_hash' => $contextHash,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        return ['id' => $id, 'page_id' => $pageId, 'context_scope' => $scope, 'context_hash' => $contextHash, 'status' => 'active'];
    }

    /** @return array<string, mixed>|null */
    public function findOwned(string $id, int $userId): ?array
    {
        global $wpdb;
        $table = $this->tables->get('conversations');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %s AND user_id = %d", $id, $userId), ARRAY_A);
        return is_array($row) ? $row : null;
    }
}
