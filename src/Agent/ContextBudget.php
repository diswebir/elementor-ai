<?php

declare(strict_types=1);

namespace AIEA\Agent;

final readonly class ContextBudget
{
    public function __construct(public int $maxCharacters = 40000)
    {
    }

    /** @param array<string, mixed> $context
     *  @return array<string, mixed>
     */
    public function trim(array $context): array
    {
        $json = wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || mb_strlen($json) <= $this->maxCharacters) {
            return $context;
        }

        $context['truncated'] = true;
        $context['document']['content'] = array_slice((array) ($context['document']['content'] ?? []), 0, 30);
        return $context;
    }
}
