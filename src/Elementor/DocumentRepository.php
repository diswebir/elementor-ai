<?php

declare(strict_types=1);

namespace AIEA\Elementor;

use RuntimeException;

final class DocumentRepository
{
    public function __construct(private readonly ElementorGuard $guard)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function getElements(int $postId): array
    {
        $this->guard->assertEditableDocument($postId);
        $document = \Elementor\Plugin::$instance->documents->get($postId);
        if (!is_object($document) || !method_exists($document, 'get_elements_data')) {
            throw new ElementorException('Elementor document cannot be read.');
        }
        $elements = $document->get_elements_data();
        return is_array($elements) ? $elements : [];
    }

    /** @param array<int, array<string, mixed>> $elements */
    public function saveElements(int $postId, array $elements): void
    {
        $this->guard->assertEditableDocument($postId);
        $document = \Elementor\Plugin::$instance->documents->get($postId);
        if (!is_object($document) || !method_exists($document, 'save')) {
            throw new ElementorException('Elementor document cannot be saved.');
        }
        $result = $document->save(['elements' => $elements]);
        if (is_wp_error($result)) {
            throw new ElementorException('Elementor rejected document changes.');
        }
        if (method_exists(\Elementor\Plugin::$instance->files_manager, 'clear_cache')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
    }

    /** @param array<int, array<string, mixed>> $elements */
    public function hash(array $elements): string
    {
        $json = wp_json_encode($elements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash('sha256', is_string($json) ? $json : '');
    }
}
