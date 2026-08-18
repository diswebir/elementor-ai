<?php

declare(strict_types=1);

namespace AIEA\Audit;

final class RetentionCleanup
{
    public function __construct(private readonly SnapshotRepository $snapshots)
    {
    }

    public function register(): void
    {
        add_action('aiea_cleanup_retention', [$this, 'run']);
    }

    public function run(): void
    {
        $this->snapshots->deleteExpired();
    }
}
