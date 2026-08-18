<?php

declare(strict_types=1);

namespace AIEA\Jobs;

use AIEA\Database\TableNames;
use RuntimeException;

final class JobRepository
{
    public function __construct(private readonly TableNames $tables)
    {
    }

    /** @param array<string, mixed> $plan
     *  @return array<string, mixed>
     */
    public function create(string $planId, int $pageId, int $actorId, string $documentHash, string $idempotencyKey, array $plan): array
    {
        global $wpdb;
        $table = $this->tables->get('jobs');
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE idempotency_key = %s", $idempotencyKey), ARRAY_A);
        if (is_array($existing)) {
            return $existing;
        }
        $id = wp_generate_uuid4();
        $now = current_time('mysql', true);
        $wpdb->insert($table, [
            'id' => $id, 'plan_id' => $planId, 'page_id' => $pageId, 'actor_id' => $actorId,
            'state' => 'waiting_approval', 'cursor' => 0, 'document_hash' => $documentHash,
            'idempotency_key' => $idempotencyKey, 'created_at' => $now, 'updated_at' => $now,
        ]);
        foreach ($plan['actions'] as $index => $action) {
            $args = wp_json_encode($action['args'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $wpdb->insert($this->tables->get('tasks'), [
                'id' => wp_generate_uuid4(), 'job_id' => $id, 'action_id' => $action['id'],
                'step_number' => $index + 1, 'tool_name' => $action['tool'],
                'arguments_hash' => hash('sha256', (string) $args), 'arguments_json' => $args,
                'state' => 'pending', 'created_at' => $now,
            ]);
        }
        return $this->findOwned($id, $actorId) ?? throw new RuntimeException('Job was not created.');
    }

    /** @return array<string,mixed>|null */
    public function findOwned(string $jobId, int $actorId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tables->get('jobs')} WHERE id = %s AND actor_id = %d", $jobId, $actorId), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function lock(string $jobId, int $actorId): ?array
    {
        global $wpdb;
        $token = wp_generate_uuid4();
        $until = gmdate('Y-m-d H:i:s', time() + 60);
        $table = $this->tables->get('jobs');
        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET lock_token = %s, locked_until = %s, updated_at = UTC_TIMESTAMP() WHERE id = %s AND actor_id = %d AND (locked_until IS NULL OR locked_until < UTC_TIMESTAMP())",
            $token,
            $until,
            $jobId,
            $actorId,
        ));
        if ($affected !== 1) {
            return null;
        }
        return $this->findOwned($jobId, $actorId);
    }

    /** @return array<string,mixed>|null */
    public function nextTask(string $jobId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tables->get('tasks')} WHERE job_id = %s AND state = 'pending' ORDER BY step_number ASC LIMIT 1", $jobId), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $result */
    public function completeTask(string $taskId, array $result): void
    {
        global $wpdb;
        $wpdb->update($this->tables->get('tasks'), ['state' => 'completed', 'result_json' => wp_json_encode($result), 'completed_at' => current_time('mysql', true)], ['id' => $taskId]);
    }

    public function failTask(string $taskId, string $errorCode): void
    {
        global $wpdb;
        $wpdb->update($this->tables->get('tasks'), ['state' => 'failed', 'error_code' => sanitize_key($errorCode), 'completed_at' => current_time('mysql', true)], ['id' => $taskId]);
    }

    public function updateJob(string $jobId, string $state, string $documentHash, ?string $errorCode = null): void
    {
        global $wpdb;
        $wpdb->update($this->tables->get('jobs'), [
            'state' => sanitize_key($state), 'document_hash' => $documentHash,
            'last_error_code' => $errorCode ? sanitize_key($errorCode) : null,
            'locked_until' => null, 'lock_token' => null, 'updated_at' => current_time('mysql', true),
        ], ['id' => $jobId]);
    }

    /** @return list<array<string,mixed>> */
    public function tasks(string $jobId): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->tables->get('tasks')} WHERE job_id = %s ORDER BY step_number ASC", $jobId), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }
}
