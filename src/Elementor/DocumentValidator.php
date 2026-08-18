<?php

declare(strict_types=1);

namespace AIEA\Elementor;

final class DocumentValidator
{
    public function __construct(private readonly CapabilityCatalog $catalog)
    {
    }

    /** @param array<int, array<string, mixed>> $elements
     *  @return array{valid:bool,errors:list<string>,summary:array<string,int>}
     */
    public function validate(array $elements): array
    {
        $errors = [];
        $summary = ['containers' => 0, 'widgets' => 0, 'h1' => 0];
        $walk = function (array $items, bool $parentIsContainer = true) use (&$walk, &$errors, &$summary): void {
            foreach ($items as $element) {
                if (!is_array($element) || empty($element['id']) || empty($element['elType'])) {
                    $errors[] = 'Element structure is incomplete.';
                    continue;
                }
                if ($element['elType'] === 'container') {
                    ++$summary['containers'];
                    $walk(is_array($element['elements'] ?? null) ? $element['elements'] : [], true);
                    continue;
                }
                if ($element['elType'] !== 'widget' || !$parentIsContainer) {
                    $errors[] = 'Widget has an invalid parent or element type.';
                    continue;
                }
                ++$summary['widgets'];
                $widgetType = (string) ($element['widgetType'] ?? '');
                if (!$this->catalog->isAllowedWidget($widgetType) || $widgetType === 'container') {
                    $errors[] = 'Document contains a widget outside the agent allowlist.';
                }
                if ($widgetType === 'heading' && (($element['settings']['header_size'] ?? '') === 'h1')) {
                    ++$summary['h1'];
                }
            }
        };
        $walk($elements);
        return ['valid' => $errors === [], 'errors' => array_values(array_unique($errors)), 'summary' => $summary];
    }
}
