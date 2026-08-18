<?php

declare(strict_types=1);

namespace AIEA\Elementor;

final class ElementorGuard
{
    public function assertAvailable(): void
    {
        if (did_action('elementor/loaded') === 0 || !class_exists('\\Elementor\\Plugin')) {
            throw new ElementorException('Elementor is not available.');
        }
    }

    public function assertEditableDocument(int $postId): void
    {
        $this->assertAvailable();
        $post = get_post($postId);
        if ($post === null || !current_user_can('edit_post', $postId)) {
            throw new ElementorException('The selected page cannot be edited.');
        }
    }
}
