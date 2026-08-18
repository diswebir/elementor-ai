<?php

declare(strict_types=1);

namespace AIEA\Admin;

final class Capabilities
{
    public const MANAGE = 'manage_ai_elementor';
    public const USE = 'use_ai_elementor';
    public const EXECUTE = 'execute_ai_actions';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::MANAGE, self::USE, self::EXECUTE];
    }
}
