<?php

declare(strict_types=1);

namespace AIEA\Agent;

final class Redactor
{
    /** @param mixed $value */
    public function redact(mixed $value, ?string $key = null): mixed
    {
        $sensitiveKey = $key !== null && preg_match('/(secret|token|password|api[_-]?key|authorization|cookie|nonce)/i', $key) === 1;
        if ($sensitiveKey) {
            return '[REDACTED]';
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $childKey => $childValue) {
                $result[$childKey] = $this->redact($childValue, is_string($childKey) ? $childKey : null);
            }
            return $result;
        }
        if (is_string($value)) {
            $value = preg_replace('/(sk-[A-Za-z0-9_-]{12,}|Bearer\\s+[A-Za-z0-9._-]{12,})/i', '[REDACTED]', $value) ?? $value;
            return mb_substr($value, 0, 12000);
        }
        return $value;
    }
}
