<?php

declare(strict_types=1);

namespace AIEA\Jobs;

use AIEA\Audit\DiffService;
use AIEA\Audit\SnapshotRepository;
use AIEA\Elementor\DocumentRepository;
use AIEA\Elementor\DocumentValidator;
use AIEA\Elementor\ElementorWriter;
use AIEA\Tools\ToolResult;
use RuntimeException;

final class DocumentTransaction
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly ElementorWriter $writer,
        private readonly DocumentValidator $validator,
        private readonly SnapshotRepository $snapshots,
        private readonly DiffService $diff,
    ) {
    }

    /** @param array<string,mixed> $args
     *  @return array<string,mixed>
     */
    public function apply(int $pageId, string $expectedHash, string $tool, array $args): array
    {
        $before = $this->documents->getElements($pageId);
        $beforeHash = $this->documents->hash($before);
        if (!hash_equals($expectedHash, $beforeHash)) {
            throw new RuntimeException('Document conflict detected.');
        }
        $snapshotId = $this->snapshots->create($pageId, 'task', ['elements' => $before], gmdate('Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS));
        try {
            $working = $before;
            /** @var ToolResult $result */
            $result = $this->writer->apply($tool, $working, $args);
            $validation = $this->validator->validate($working);
            if (!$validation['valid']) {
                throw new RuntimeException('Document validation failed after mutation.');
            }
            $this->documents->saveElements($pageId, $working);
            return [
                'snapshot_id' => $snapshotId,
                'before_hash' => $beforeHash,
                'after_hash' => $this->documents->hash($working),
                'tool' => $result->data,
                'summary' => $result->summary,
                'validation' => $validation,
                'diff' => $this->diff->compare(['content' => $before], ['content' => $working]),
            ];
        } catch (\Throwable $exception) {
            throw $exception;
        }
    }

    /** @return array<string,mixed> */
    public function rollback(int $pageId, string $snapshotId): array
    {
        $snapshot = $this->snapshots->getDocument($snapshotId, $pageId);
        $elements = $snapshot['elements'] ?? null;
        if (!is_array($elements)) {
            throw new RuntimeException('Snapshot does not contain document elements.');
        }
        $validation = $this->validator->validate($elements);
        if (!$validation['valid']) {
            throw new RuntimeException('Snapshot is not valid for restoration.');
        }
        $this->documents->saveElements($pageId, $elements);
        return ['snapshot_id' => $snapshotId, 'after_hash' => $this->documents->hash($elements), 'validation' => $validation];
    }
}
