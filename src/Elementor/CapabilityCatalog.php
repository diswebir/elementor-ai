<?php

declare(strict_types=1);

namespace AIEA\Elementor;

final class CapabilityCatalog
{
    /** @var array<string, array{category:string, controls:list<string>, supports_children:bool, risk:string}> */
    private const CORE_ALLOWLIST = [
        'container' => ['category' => 'layout', 'controls' => ['flex_direction', 'content_width', 'padding', 'margin', 'background_background', 'background_color'], 'supports_children' => true, 'risk' => 'low'],
        'heading' => ['category' => 'basic', 'controls' => ['title', 'header_size', 'align', 'title_color', 'typography_typography', 'margin', 'padding'], 'supports_children' => false, 'risk' => 'low'],
        'text-editor' => ['category' => 'basic', 'controls' => ['editor', 'align', 'text_color', 'typography_typography', 'margin', 'padding'], 'supports_children' => false, 'risk' => 'low'],
        'image' => ['category' => 'basic', 'controls' => ['image', 'image_size', 'align', 'width', 'max_width', 'margin', 'padding'], 'supports_children' => false, 'risk' => 'low'],
        'button' => ['category' => 'basic', 'controls' => ['text', 'link', 'align', 'size', 'button_text_color', 'background_color', 'border_radius', 'padding'], 'supports_children' => false, 'risk' => 'low'],
        'divider' => ['category' => 'basic', 'controls' => ['style', 'weight', 'color', 'width', 'align', 'gap'], 'supports_children' => false, 'risk' => 'low'],
        'spacer' => ['category' => 'basic', 'controls' => ['space'], 'supports_children' => false, 'risk' => 'low'],
    ];

    /** @return array<string, mixed> */
    public function build(): array
    {
        $widgets = [];
        foreach (self::CORE_ALLOWLIST as $type => $definition) {
            $widgets[] = [
                'widget_type' => $type,
                'name' => $type,
                'category' => $definition['category'],
                'provider' => 'elementor-core',
                'controls' => $definition['controls'],
                'required_settings' => in_array($type, ['heading', 'text-editor', 'button'], true) ? [$type === 'heading' ? 'title' : ($type === 'button' ? 'text' : 'editor')] : [],
                'supports_children' => $definition['supports_children'],
                'supports_styles' => true,
                'supports_responsive' => true,
                'capability_level' => 'simple',
                'risk_level' => $definition['risk'],
            ];
        }

        return [
            'catalog_version' => hash('sha256', wp_json_encode($widgets)),
            'elementor_version' => defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : 'unknown',
            'widgets' => $widgets,
            'disabled_widgets' => ['html', 'shortcode', 'form', 'login', 'woocommerce-checkout', 'payment'],
        ];
    }

    public function isAllowedWidget(string $widgetType): bool
    {
        return array_key_exists($widgetType, self::CORE_ALLOWLIST);
    }

    public function isAllowedControl(string $widgetType, string $control): bool
    {
        return isset(self::CORE_ALLOWLIST[$widgetType]) && in_array($control, self::CORE_ALLOWLIST[$widgetType]['controls'], true);
    }

    /** @return array<string, mixed>|null */
    public function definition(string $widgetType): ?array
    {
        return self::CORE_ALLOWLIST[$widgetType] ?? null;
    }
}
