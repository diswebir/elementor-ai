<?php

declare(strict_types=1);

namespace AIEA\Audit;

use AIEA\Database\TableNames;

final class AuditRepository
{
    public function __construct(private readonly ?TableNames $tables = null)
    {
    }

    /** @return list<array<string, mixed>> */
    public function recentProviderTests(int $limit = 10): array
    {
        global $wpdb;

        $table = ($this->tables ?? new TableNames())->get('audit_log');
        $limit = min(25, max(1, $limit));
        $sql = $wpdb->prepare(
            "SELECT id, status, result_summary, duration_ms, error_code, created_at
            FROM {$table}
            WHERE event = %s
            ORDER BY id DESC
            LIMIT %d",
            'provider_test',
            $limit,
        );
        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }
}
