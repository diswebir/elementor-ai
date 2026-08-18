<?php

declare(strict_types=1);

namespace AIEA\Agent;

enum AgentState: string
{
    case Idle = 'idle';
    case Analyzing = 'analyzing';
    case Planning = 'planning';
    case WaitingApproval = 'waiting_approval';
    case Executing = 'executing';
    case Validating = 'validating';
    case Repairing = 'repairing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case NeedsReview = 'needs_review';
}
