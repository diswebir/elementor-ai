<?php

declare(strict_types=1);

namespace AIEA\Agent;

use DomainException;

final class StateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'idle' => ['analyzing', 'planning', 'cancelled'],
        'analyzing' => ['planning', 'failed', 'cancelled'],
        'planning' => ['waiting_approval', 'failed', 'cancelled'],
        'waiting_approval' => ['executing', 'cancelled', 'needs_review'],
        'executing' => ['validating', 'repairing', 'failed', 'cancelled', 'needs_review'],
        'validating' => ['completed', 'repairing', 'failed', 'needs_review'],
        'repairing' => ['executing', 'failed', 'needs_review', 'cancelled'],
        'completed' => [],
        'failed' => ['repairing', 'cancelled'],
        'cancelled' => [],
        'needs_review' => ['waiting_approval', 'cancelled'],
    ];

    public function assertTransition(AgentState $from, AgentState $to): void
    {
        if (!in_array($to->value, self::TRANSITIONS[$from->value], true)) {
            throw new DomainException(sprintf('Invalid agent state transition: %s → %s.', $from->value, $to->value));
        }
    }
}
