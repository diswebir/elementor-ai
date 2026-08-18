<?php

declare(strict_types=1);

namespace AIEA\Core;

final class CompatibilityGuard
{
    public const MINIMUM_PHP = '8.1.0';

    public function isCompatible(): bool
    {
        return version_compare(PHP_VERSION, self::MINIMUM_PHP, '>=') && did_action('elementor/loaded') > 0;
    }

    /** @return list<string> */
    public function issues(): array
    {
        $issues = [];

        if (version_compare(PHP_VERSION, self::MINIMUM_PHP, '<')) {
            $issues[] = sprintf('PHP %s or newer is required.', self::MINIMUM_PHP);
        }

        if (did_action('elementor/loaded') === 0) {
            $issues[] = 'Elementor must be installed and active.';
        }

        return $issues;
    }
}
