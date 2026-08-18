<?php

declare(strict_types=1);

namespace AIEA\Admin;

final class Settings
{
    public const OPTION = 'aiea_settings';

    /** @return array<string, mixed> */
    public function all(): array
    {
        $saved = get_option(self::OPTION, []);

        return wp_parse_args(is_array($saved) ? $saved : [], $this->defaults());
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'provider_type' => 'openai_compatible',
            'provider_alias' => 'Default provider',
            'base_url' => '',
            'model' => '',
            'temperature' => '0.2',
            'max_tokens' => 3000,
            'request_timeout' => 30,
            'monthly_action_limit' => 200,
            'context_scope' => 'current',
            'retention_days' => 30,
            'development_mode' => false,
            'allow_auto_mode' => false,
            'simple_editor_mode' => true,
        ];
    }
}
