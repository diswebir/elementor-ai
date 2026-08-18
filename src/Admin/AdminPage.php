<?php

declare(strict_types=1);

namespace AIEA\Admin;

use AIEA\Core\CompatibilityGuard;
use AIEA\AI\SecretManager;
use AIEA\Support\Temperature;

final class AdminPage
{
    private const PAGE_SLUG = 'ai-elementor-ag';

    public function __construct(
        private readonly ?CompatibilityGuard $guard = null,
        private readonly Settings $settings = new Settings(),
    ) {
    }

    public function registerCapabilities(): void
    {
        $administrator = get_role('administrator');
        if ($administrator === null) {
            return;
        }

        foreach (Capabilities::all() as $capability) {
            $administrator->add_cap($capability);
        }
    }

    public function registerPage(): void
    {
        add_menu_page(
            __('AI Elementor AG', 'ai-elementor-ag'),
            __('AI Elementor AG', 'ai-elementor-ag'),
            Capabilities::MANAGE,
            self::PAGE_SLUG,
            [$this, 'render'],
            'dashicons-superhero-alt',
            58,
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            'aiea_settings_group',
            Settings::OPTION,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeSettings'],
                'default' => $this->settings->defaults(),
            ],
        );

        add_settings_section(
            'aiea_provider_section',
            __('Provider and execution policy', 'ai-elementor-ag'),
            static function (): void {
                echo '<p>' . esc_html__('API keys are stored separately and never returned to the browser.', 'ai-elementor-ag') . '</p>';
            },
            self::PAGE_SLUG,
        );

        foreach ($this->fieldDefinitions() as $field => $definition) {
            add_settings_field(
                'aiea_' . $field,
                $definition['label'],
                [$this, 'renderField'],
                self::PAGE_SLUG,
                'aiea_provider_section',
                ['field' => $field, 'definition' => $definition],
            );
        }
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function sanitizeSettings(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $current = $this->settings->all();

        $providerType = isset($input['provider_type']) ? sanitize_key((string) $input['provider_type']) : $current['provider_type'];
        $allowedProviders = ['openai_compatible', 'openai', 'anthropic', 'gemini'];
        if (!in_array($providerType, $allowedProviders, true)) {
            add_settings_error(Settings::OPTION, 'invalid_provider', __('Provider type is not supported.', 'ai-elementor-ag'));
            $providerType = $current['provider_type'];
        }

        $baseUrl = isset($input['base_url']) ? esc_url_raw(trim((string) $input['base_url'])) : '';
        if ($baseUrl !== '' && wp_parse_url($baseUrl, PHP_URL_SCHEME) !== 'https') {
            add_settings_error(Settings::OPTION, 'invalid_endpoint', __('Only HTTPS provider endpoints are accepted.', 'ai-elementor-ag'));
            $baseUrl = (string) $current['base_url'];
        }

        $scope = isset($input['context_scope']) ? sanitize_key((string) $input['context_scope']) : 'current';
        if (!in_array($scope, ['current', 'site', 'project'], true)) {
            $scope = 'current';
        }

        if (!empty($input['api_key'])) {
            (new SecretManager())->store((string) $input['api_key']);
        }
        if (!empty($input['clear_api_key'])) {
            (new SecretManager())->forget();
        }

        return [
            'provider_type' => $providerType,
            'provider_alias' => sanitize_text_field((string) ($input['provider_alias'] ?? $current['provider_alias'])),
            'base_url' => $baseUrl,
            'model' => sanitize_text_field((string) ($input['model'] ?? $current['model'])),
            'temperature' => Temperature::normalize($input['temperature'] ?? $current['temperature'], (string) $current['temperature']),
            'max_tokens' => min(16000, max(256, absint($input['max_tokens'] ?? $current['max_tokens']))),
            'request_timeout' => min(120, max(5, absint($input['request_timeout'] ?? $current['request_timeout']))),
            'monthly_action_limit' => min(100000, max(1, absint($input['monthly_action_limit'] ?? $current['monthly_action_limit']))),
            'context_scope' => $scope,
            'retention_days' => min(365, max(1, absint($input['retention_days'] ?? $current['retention_days']))),
            'development_mode' => !empty($input['development_mode']),
            'allow_auto_mode' => !empty($input['allow_auto_mode']),
        ];
    }

    /** @param array{field: string, definition: array<string, mixed>} $args */
    public function renderField(array $args): void
    {
        $settings = $this->settings->all();
        $field = $args['field'];
        $definition = $args['definition'];
        $value = $settings[$field] ?? '';
        $name = Settings::OPTION . '[' . $field . ']';

        if (($definition['type'] ?? '') === 'select') {
            echo '<select name="' . esc_attr($name) . '">';
            foreach ($definition['options'] as $option => $label) {
                echo '<option value="' . esc_attr((string) $option) . '" ' . selected($value, $option, false) . '>' . esc_html((string) $label) . '</option>';
            }
            echo '</select>';
            return;
        }

        if ($field === 'api_key') {
            $masked = (new SecretManager())->masked();
            echo '<input class="regular-text" autocomplete="new-password" type="password" name="' . esc_attr($name) . '" value="" placeholder="' . esc_attr($masked ?? __('Not configured', 'ai-elementor-ag')) . '">';
            echo '<label><input type="checkbox" name="' . esc_attr(Settings::OPTION . '[clear_api_key]') . '" value="1"> ' . esc_html__('Delete the currently stored key', 'ai-elementor-ag') . '</label>';
            return;
        }

        if (($definition['type'] ?? '') === 'checkbox') {
            echo '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked((bool) $value, true, false) . '> ' . esc_html($definition['help']) . '</label>';
            return;
        }

        echo '<input class="regular-text" type="' . esc_attr((string) $definition['type']) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" min="' . esc_attr((string) ($definition['min'] ?? '')) . '" max="' . esc_attr((string) ($definition['max'] ?? '')) . '" step="' . esc_attr((string) ($definition['step'] ?? '1')) . '" inputmode="' . esc_attr((string) ($definition['inputmode'] ?? 'text')) . '">';
        if (!empty($definition['help'])) {
            echo '<p class="description">' . esc_html((string) $definition['help']) . '</p>';
        }
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE)) {
            wp_die(esc_html__('You are not allowed to manage this plugin.', 'ai-elementor-ag'));
        }

        $issues = $this->guard?->issues() ?? [];
        $adminConfig = wp_json_encode([
            'restUrl' => esc_url_raw(rest_url('ai-elementor/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'providerConfigured' => (new SecretManager())->hasSecret(),
        ]) ?: '{}';
        echo '<div class="wrap" dir="rtl"><h1>' . esc_html__('AI Elementor AG', 'ai-elementor-ag') . '</h1>';
        if ($issues !== []) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html(implode(' ', $issues)) . '</p></div>';
        }
        echo '<p>' . esc_html__('تنظیمات اتصال و سیاست‌های عامل در این صفحه مدیریت می‌شود. کلید API در هیچ پاسخ یا گزارش مرورگر نمایش داده نمی‌شود.', 'ai-elementor-ag') . '</p>';
        if (!empty($this->settings->all()['simple_editor_mode'])) {
            echo '<div class="notice notice-info inline"><p>' . esc_html__('حالت سادهٔ Editor فعال است: گفت‌وگو، تحلیل، Plan، Job و اجرای خودکار موقتاً غیرفعال‌اند. پنل Elementor فقط افزودن یک عنوان را انجام می‌دهد.', 'ai-elementor-ag') . '</p></div>';
        }
        echo '<form action="options.php" method="post">';
        settings_fields('aiea_settings_group');
        do_settings_sections(self::PAGE_SLUG);
        submit_button();
        echo '</form><div id="aiea-admin-app" data-aiea-admin-app="1" data-aiea-admin-config="' . esc_attr($adminConfig) . '"></div></div>';
    }

    /** @return array<string, array<string, mixed>> */
    private function fieldDefinitions(): array
    {
        return [
            'provider_type' => ['label' => __('Provider', 'ai-elementor-ag'), 'type' => 'select', 'options' => ['openai_compatible' => 'OpenAI-compatible', 'openai' => 'OpenAI', 'anthropic' => 'Anthropic', 'gemini' => 'Google Gemini']],
            'provider_alias' => ['label' => __('Provider alias', 'ai-elementor-ag'), 'type' => 'text', 'help' => __('A safe, human-readable name; do not include credentials.', 'ai-elementor-ag')],
            'api_key' => ['label' => __('API key', 'ai-elementor-ag'), 'type' => 'password', 'help' => __('Leave blank to retain the existing key. It is encrypted before storage and never shown again.', 'ai-elementor-ag')],
            'base_url' => ['label' => __('Base URL', 'ai-elementor-ag'), 'type' => 'url', 'help' => __('HTTPS only. Private and loopback addresses are rejected by the request guard.', 'ai-elementor-ag')],
            'model' => ['label' => __('Default model', 'ai-elementor-ag'), 'type' => 'text', 'help' => __('The model must support structured output for Build mode.', 'ai-elementor-ag')],
            'temperature' => ['label' => __('Temperature', 'ai-elementor-ag'), 'type' => 'number', 'min' => '0', 'max' => '2', 'step' => '0.01', 'inputmode' => 'decimal', 'help' => __('A value from 0 to 2; decimals such as 0.2 or 0.75 are supported.', 'ai-elementor-ag')],
            'max_tokens' => ['label' => __('Maximum tokens', 'ai-elementor-ag'), 'type' => 'number', 'min' => '256', 'max' => '16000'],
            'request_timeout' => ['label' => __('Request timeout', 'ai-elementor-ag'), 'type' => 'number', 'min' => '5', 'max' => '120'],
            'monthly_action_limit' => ['label' => __('Monthly action limit', 'ai-elementor-ag'), 'type' => 'number', 'min' => '1', 'max' => '100000'],
            'context_scope' => ['label' => __('Default context scope', 'ai-elementor-ag'), 'type' => 'select', 'options' => ['current' => __('Current page only', 'ai-elementor-ag'), 'site' => __('Selected site design data', 'ai-elementor-ag'), 'project' => __('Project instructions', 'ai-elementor-ag')]],
            'retention_days' => ['label' => __('Retention days', 'ai-elementor-ag'), 'type' => 'number', 'min' => '1', 'max' => '365'],
            'development_mode' => ['label' => __('Development diagnostics', 'ai-elementor-ag'), 'type' => 'checkbox', 'help' => __('Store diagnostic metadata without secrets or raw prompts.', 'ai-elementor-ag')],
            'allow_auto_mode' => ['label' => __('Allow Auto mode', 'ai-elementor-ag'), 'type' => 'checkbox', 'help' => __('Auto mode remains Draft-only and excludes high-risk actions.', 'ai-elementor-ag')],
        ];
    }
}
