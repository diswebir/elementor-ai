<?php

declare(strict_types=1);

namespace AIEA\Elementor;

final class ElementorReader
{
    public function __construct(private readonly ElementorGuard $guard)
    {
    }

    /** @return array<string, mixed> */
    public function readDocument(int $postId): array
    {
        $this->guard->assertEditableDocument($postId);
        $post = get_post($postId);
        $elements = $this->readElements($postId);
        $settings = $this->readDocumentSettings($postId);

        return [
            'page_id' => $postId,
            'page_title' => $post?->post_title ?? '',
            'post_status' => $post?->post_status ?? '',
            'content' => $this->normalizeElements($elements, null),
            'page_settings' => $settings,
        ];
    }

    /** @return array<string, mixed>|null */
    public function findElement(int $postId, string $elementId): ?array
    {
        $document = $this->readDocument($postId);
        $stack = $document['content'];
        while ($stack !== []) {
            $element = array_pop($stack);
            if (!is_array($element)) {
                continue;
            }
            if (($element['id'] ?? '') === $elementId) {
                return $element;
            }
            foreach (($element['children'] ?? []) as $child) {
                $stack[] = $child;
            }
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    private function readElements(int $postId): array
    {
        $document = \Elementor\Plugin::$instance->documents->get($postId);
        if (is_object($document) && method_exists($document, 'get_elements_data')) {
            $elements = $document->get_elements_data();
            return is_array($elements) ? $elements : [];
        }

        $raw = get_post_meta($postId, '_elementor_data', true);
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, mixed> */
    private function readDocumentSettings(int $postId): array
    {
        $document = \Elementor\Plugin::$instance->documents->get($postId);
        if (is_object($document) && method_exists($document, 'get_settings')) {
            $settings = $document->get_settings();
            return is_array($settings) ? $settings : [];
        }

        return [];
    }

    /** @param array<int, array<string, mixed>> $elements
     *  @return array<int, array<string, mixed>>
     */
    private function normalizeElements(array $elements, ?string $parentId): array
    {
        $normalized = [];
        foreach ($elements as $element) {
            if (!is_array($element) || empty($element['id'])) {
                continue;
            }
            $rawType = (string) ($element['elType'] ?? '');
            $widgetType = (string) ($element['widgetType'] ?? '');
            $type = $rawType === 'widget' ? 'widget' : 'container';
            $children = is_array($element['elements'] ?? null) ? $element['elements'] : [];
            $normalized[] = [
                'id' => (string) $element['id'],
                'element_type' => $type,
                'widget_type' => $widgetType,
                'parent_id' => $parentId,
                'settings' => is_array($element['settings'] ?? null) ? $element['settings'] : [],
                'styles' => [],
                'responsive' => [],
                'children' => $this->normalizeElements($children, (string) $element['id']),
            ];
        }
        return $normalized;
    }
}
