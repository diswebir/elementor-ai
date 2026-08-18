<?php

declare(strict_types=1);

namespace AIEA\Jobs;

use AIEA\Audit\AuditLogger;
use AIEA\Tools\ToolRegistry;
use RuntimeException;

final class JobRunner
{
    public function __construct(
        private readonly JobRepository $jobs,
        private readonly DocumentTransaction $transaction,
        private readonly ToolRegistry $tools,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return array<string,mixed> */
    public function runNext(string $jobId, int $actorId): array
    {
        $job = $this->jobs->lock($jobId, $actorId);
        if ($job === null) {
            throw new RuntimeException('Job is unavailable or already running.');
        }
        $task = $this->jobs->nextTask($jobId);
        if ($task === null) {
            $this->jobs->updateJob($jobId, 'completed', (string) $job['document_hash']);
            return ['job' => $this->jobs->findOwned($jobId, $actorId), 'task' => null, 'completed' => true];
        }
        $args = json_decode((string) $task['arguments_json'], true);
        if (!is_array($args)) {
            $this->jobs->failTask((string) $task['id'], 'invalid_task_payload');
            $this->jobs->updateJob($jobId, 'failed', (string) $job['document_hash'], 'invalid_task_payload');
            throw new RuntimeException('Task payload is invalid.');
        }
        try {
            $this->tools->assertInvocation((string) $task['tool_name'], $args);
            $this->audit->log('task.started', 'running', ['page_id' => $job['page_id'], 'job_id' => $jobId, 'task_id' => $task['id'], 'arguments_hash' => $task['arguments_hash']]);
            $receipt = $this->transaction->apply((int) $job['page_id'], (string) $job['document_hash'], (string) $task['tool_name'], $args);
            $this->jobs->completeTask((string) $task['id'], $receipt);
            $remaining = $this->jobs->nextTask($jobId);
            $this->jobs->updateJob($jobId, $remaining === null ? 'completed' : 'waiting_approval', (string) $receipt['after_hash']);
            $this->audit->log('task.completed', 'completed', ['page_id' => $job['page_id'], 'job_id' => $jobId, 'task_id' => $task['id'], 'arguments_hash' => $task['arguments_hash'], 'result_summary' => $receipt['summary']]);
            return ['job' => $this->jobs->findOwned($jobId, $actorId), 'task' => $task, 'receipt' => $receipt, 'completed' => $remaining === null];
        } catch (\Throwable $exception) {
            $code = $exception instanceof \AIEA\Tools\ToolException ? $exception->safeCode : 'execution_failed';
            $this->jobs->failTask((string) $task['id'], $code);
            $this->jobs->updateJob($jobId, 'needs_review', (string) $job['document_hash'], $code);
            $this->audit->log('task.failed', 'failed', ['page_id' => $job['page_id'], 'job_id' => $jobId, 'task_id' => $task['id'], 'arguments_hash' => $task['arguments_hash'], 'error_code' => $code]);
            throw new RuntimeException('Action stopped safely and needs review.');
        }
    }
}
