<?php

declare(strict_types=1);

namespace AIEA\Elementor;

final class ElementorContext
{
    public function __construct(
        private readonly ElementorReader $reader,
        private readonly CapabilityCatalog $catalog,
    ) {
    }

    /** @return array<string, mixed> */
    public function collect(int $postId, ?string $selectionId = null): array
    {
        $document = $this->reader->readDocument($postId);
        $selected = $selectionId !== null ? $this->reader->findElement($postId, $selectionId) : null;

        return [
            'document' => $document,
            'selection' => $selected,
            'catalog' => $this->catalog->build(),
            'globals' => [
                'colors' => $this->globalColors(),
                'fonts' => $this->globalFonts(),
            ],
        ];
    }

    /** @return array<int, mixed> */
    private function globalColors(): array
    {
        $kitId = get_option('elementor_active_kit');
        if (!is_numeric($kitId)) {
            return [];
        }
        $kit = \Elementor\Plugin::$instance->documents->get((int) $kitId);
        if (!is_object($kit) || !method_exists($kit, 'get_settings')) {
            return [];
        }
        $settings = $kit->get_settings('system_colors');
        return is_array($settings) ? $settings : [];
    }

    /** @return array<int, mixed> */
    private function globalFonts(): array
    {
        $kitId = get_option('elementor_active_kit');
        if (!is_numeric($kitId)) {
            return [];
        }
        $kit = \Elementor\Plugin::$instance->documents->get((int) $kitId);
        if (!is_object($kit) || !method_exists($kit, 'get_settings')) {
            return [];
        }
        $settings = $kit->get_settings('system_typography');
        return is_array($settings) ? $settings : [];
    }
}
