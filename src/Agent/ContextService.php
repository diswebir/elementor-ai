<?php

declare(strict_types=1);

namespace AIEA\Agent;

use AIEA\Elementor\ElementorContext;

final class ContextService
{
    public function __construct(
        private readonly ElementorContext $elementor,
        private readonly Redactor $redactor,
        private readonly ContextBudget $budget,
    ) {
    }

    /** @return array{data:array<string,mixed>,hash:string} */
    public function collect(int $postId, string $scope, ?string $selectionId = null): array
    {
        $raw = $this->elementor->collect($postId, $selectionId);
        if ($scope === 'current') {
            unset($raw['globals']);
        }
        $redacted = $this->redactor->redact($raw);
        $data = $this->budget->trim(is_array($redacted) ? $redacted : []);
        $json = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return ['data' => $data, 'hash' => hash('sha256', is_string($json) ? $json : '')];
    }
}
