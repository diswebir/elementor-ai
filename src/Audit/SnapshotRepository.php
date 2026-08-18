<?php

declare(strict_types=1);

namespace AIEA\Audit;

use AIEA\Database\TableNames;
use RuntimeException;

final class SnapshotRepository
{
    private const MAX_SNAPSHOT_BYTES = 5_000_000;

    public function __construct(private readonly ?TableNames $tables = null)
    {
    }

    /** @param array<string, mixed> $document */
    public function create(int $pageId, string $source, array $document, ?string $expiresAt = null): string
    {
        global $wpdb;

        $json = wp_json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('Unable to encode snapshot document.');
        }

        $compressed = gzencode($json, 6);
        if (!is_string($compressed) || strlen($compressed) > self::MAX_SNAPSHOT_BYTES) {
            throw new RuntimeException('Snapshot is too large to store safely.');
        }

        $id = wp_generate_uuid4();
        $wpdb->insert(
            ($this->tables ?? new TableNames())->get('snapshots'),
            [
                'id' => $id,
                'page_id' => $pageId,
                'source' => sanitize_key($source),
                'document_hash' => hash('sha256', $json),
                'document_compressed' => base64_encode($compressed),
                'size_bytes' => strlen($compressed),
                'expires_at' => $expiresAt,
                'created_at' => current_time('mysql', true),
            ],
        );

        return $id;
    }

    /** @return array<string, mixed> */
    public function getDocument(string $snapshotId, int $pageId): array
    {
        global $wpdb;

        $table = ($this->tables ?? new TableNames())->get('snapshots');
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT document_compressed, expires_at FROM {$table} WHERE id = %s AND page_id = %d", $snapshotId, $pageId),
            ARRAY_A,
        );

        if (!is_array($row) || ($row['expires_at'] !== null && strtotime((string) $row['expires_at']) < time())) {
            throw new RuntimeException('Snapshot is unavailable or expired.');
        }

        $compressed = base64_decode((string) $row['document_compressed'], true);
        $json = is_string($compressed) ? gzdecode($compressed) : false;
        $document = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($document)) {
            throw new RuntimeException('Snapshot integrity check failed.');
        }

        return $document;
    }

    public function deleteExpired(): int
    {
        global $wpdb;
        return (int) $wpdb->query(
            "DELETE FROM " . ($this->tables ?? new TableNames())->get('snapshots') . " WHERE expires_at IS NOT NULL AND expires_at < UTC_TIMESTAMP()"
        );
    }
}
