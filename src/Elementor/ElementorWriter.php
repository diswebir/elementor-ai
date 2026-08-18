<?php

declare(strict_types=1);

namespace AIEA\Elementor;

use AIEA\Tools\ToolException;
use AIEA\Tools\ToolResult;

final class ElementorWriter
{
    public function __construct(private readonly CapabilityCatalog $catalog)
    {
    }

    /** @param array<int, array<string, mixed>> $elements
     *  @param array<string, mixed> $args
     */
    public function apply(string $tool, array &$elements, array $args): ToolResult
    {
        return match ($tool) {
            'create_container' => $this->createContainer($elements, $args),
            'create_widget' => $this->createWidget($elements, $args),
            'update_widget', 'set_style', 'set_responsive_style', 'set_spacing', 'set_alignment', 'set_background', 'set_typography' => $this->updateSettings($elements, $args),
            'move_element' => $this->moveElement($elements, $args),
            'delete_element' => $this->deleteElement($elements, $args),
            'save_page' => new ToolResult([], 'Page save confirmed.'),
            'validate_page' => new ToolResult([], 'Validation will run after the transaction.'),
            default => throw new ToolException('Tool is not implemented.', 'tool_not_implemented'),
        };
    }

    /** @param array<int, array<string, mixed>> $elements
     *  @param array<string, mixed> $args
     */
    private function createContainer(array &$elements, array $args): ToolResult
    {
        $container = [
            'id' => $this->id(),
            'elType' => 'container',
            'isInner' => false,
            'settings' => $this->filterSettings('container', is_array($args['settings'] ?? null) ? $args['settings'] : []),
            'elements' => [],
        ];
        $parent = (string) $args['parent'];
        if ($parent === 'root') {
            $elements[] = $container;
        } elseif (!$this->appendToParent($elements, $parent, $container)) {
            throw new ToolException('Container parent does not exist or cannot contain children.', 'invalid_parent');
        }
        return new ToolResult(['element_id' => $container['id']], 'Container created.');
    }

    /** @param array<int, array<string, mixed>> $elements
     *  @param array<string, mixed> $args
     */
    private function createWidget(array &$elements, array $args): ToolResult
    {
        $widgetType = (string) ($args['widget_type'] ?? '');
        if (!$this->catalog->isAllowedWidget($widgetType) || $widgetType === 'container') {
            throw new ToolException('Widget is not supported by the current capability catalog.', 'unsupported_widget');
        }
        $settings = is_array($args['settings'] ?? null) ? $args['settings'] : [];
        $widget = [
            'id' => $this->id(),
            'elType' => 'widget',
            'widgetType' => $widgetType,
            'settings' => $this->filterSettings($widgetType, $settings),
            'elements' => [],
        ];
        if (!$this->appendToParent($elements, (string) $args['parent'], $widget)) {
            throw new ToolException('Widget parent does not exist or cannot contain children.', 'invalid_parent');
        }
        return new ToolResult(['element_id' => $widget['id'], 'widget_type' => $widgetType], 'Widget created.');
    }

    /** @param array<int, array<string, mixed>> $elements
     *  @param array<string, mixed> $args
     */
    private function updateSettings(array &$elements, array $args): ToolResult
    {
        $targetId = (string) $args['target_id'];
        $target =& $this->find($elements, $targetId);
        if ($target === null) {
            throw new ToolException('Target element does not exist.', 'element_not_found');
        }
        $type = (string) ($target['widgetType'] ?? 'container');
        $settings = is_array($args['settings'] ?? null) ? $args['settings'] : [];
        $target['settings'] = array_merge((array) ($target['settings'] ?? []), $this->filterSettings($type, $settings));
        return new ToolResult(['element_id' => $targetId], 'Element settings updated.');
    }

    /** @param array<int, array<string, mixed>> $elements
     *  @param array<string, mixed> $args
     */
    private function deleteElement(array &$elements, array $args): ToolResult
    {
        $id = (string) $args['target_id'];
        if (!$this->remove($elements, $id)) {
            throw new ToolException('Element to delete was not found.', 'element_not_found');
        }
        return new ToolResult(['element_id' => $id], 'Element deleted.');
    }

    /** @param array<int, array<string, mixed>> $elements
     *  @param array<string, mixed> $args
     */
    private function moveElement(array &$elements, array $args): ToolResult
    {
        $id = (string) $args['target_id'];
        $parentId = (string) $args['parent_id'];
        $element = $this->extract($elements, $id);
        if ($element === null) {
            throw new ToolException('Element to move was not found.', 'element_not_found');
        }
        if (!$this->appendToParent($elements, $parentId, $element)) {
            $this->restoreToRoot($elements, $element);
            throw new ToolException('Destination container does not exist or cannot contain children.', 'invalid_parent');
        }
        return new ToolResult(['element_id' => $id, 'parent_id' => $parentId], 'Element moved.');
    }

    /** @param array<int, array<string, mixed>> $elements
     *  @param array<string, mixed> $child
     */
    private function appendToParent(array &$elements, string $parentId, array $child): bool
    {
        $parent =& $this->find($elements, $parentId);
        if ($parent === null || ($parent['elType'] ?? '') !== 'container') {
            return false;
        }
        $parent['elements'] = is_array($parent['elements'] ?? null) ? $parent['elements'] : [];
        $parent['elements'][] = $child;
        return true;
    }

    /** @param array<int, array<string, mixed>> $elements
     *  @return array<string, mixed>|null
     */
    private function extract(array &$elements, string $id): ?array
    {
        foreach ($elements as $index => &$element) {
            if (($element['id'] ?? '') === $id) {
                $found = $element;
                array_splice($elements, $index, 1);
                return $found;
            }
            if (isset($element['elements']) && is_array($element['elements'])) {
                $found = $this->extract($element['elements'], $id);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }

    /** @param array<int, array<string, mixed>> $elements */
    private function remove(array &$elements, string $id): bool
    {
        return $this->extract($elements, $id) !== null;
    }

    /** @param array<int, array<string, mixed>> $elements
     *  @return array<string, mixed>|null
     */
    private function &find(array &$elements, string $id): ?array
    {
        foreach ($elements as &$element) {
            if (($element['id'] ?? '') === $id) {
                return $element;
            }
            if (isset($element['elements']) && is_array($element['elements'])) {
                $found =& $this->find($element['elements'], $id);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        $null = null;
        return $null;
    }

    /** @param array<string, mixed> $settings
     *  @return array<string, mixed>
     */
    private function filterSettings(string $type, array $settings): array
    {
        $allowed = [];
        foreach ($settings as $key => $value) {
            if (is_string($key) && $this->catalog->isAllowedControl($type, $key)) {
                $allowed[$key] = $value;
            }
        }
        return $allowed;
    }

    /** @param array<int, array<string, mixed>> $elements
     *  @param array<string, mixed> $element
     */
    private function restoreToRoot(array &$elements, array $element): void
    {
        $elements[] = $element;
    }

    private function id(): string
    {
        return substr(str_replace('-', '', wp_generate_uuid4()), 0, 8);
    }
}
