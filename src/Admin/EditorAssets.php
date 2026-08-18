<?php

declare(strict_types=1);

namespace AIEA\Admin;

final class EditorAssets
{
    private const EDITOR_HANDLE = 'aiea-editor';
    private const ADMIN_HANDLE = 'aiea-admin';

    public function enqueueEditorAssets(): void
    {
        $this->enqueue(self::EDITOR_HANDLE, 'editor');
        add_action('elementor/editor/footer', [$this, 'renderEditorRoot']);
    }

    public function enqueueAdminAssets(string $hook): void
    {
        if ($hook !== 'toplevel_page_ai-elementor-agent') {
            return;
        }

        $this->enqueue(self::ADMIN_HANDLE, 'admin');
    }

    public function renderEditorRoot(): void
    {
        echo '<div id="aiea-editor-root" data-aiea-editor-root="1"></div>';
    }

    private function enqueue(string $handle, string $entry): void
    {
        $manifestPath = AIEA_DIR . 'assets/build/.vite/manifest.json';
        if (!is_readable($manifestPath)) {
            return;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            return;
        }

        $manifestEntry = $manifest['assets/src/' . $entry . '/main.tsx'] ?? null;
        if (!is_array($manifestEntry) || empty($manifestEntry['file'])) {
            return;
        }

        $styles = $manifestEntry['css'] ?? [];
        foreach (is_array($styles) ? $styles : [] as $index => $style) {
            wp_enqueue_style($handle . '-' . $index, AIEA_URL . 'assets/build/' . ltrim((string) $style, '/'), [], AIEA_VERSION);
        }

        wp_enqueue_script($handle, AIEA_URL . 'assets/build/' . ltrim((string) $manifestEntry['file'], '/'), [], AIEA_VERSION, true);
        wp_script_add_data($handle, 'type', 'module');
        if ($handle === self::EDITOR_HANDLE) {
            $postId = isset($_GET['post']) ? absint($_GET['post']) : 0;
            $settings = (new Settings())->all();
            wp_add_inline_script($handle, 'window.AIEA_CONFIG = ' . wp_json_encode([
                'restUrl' => esc_url_raw(rest_url('ai-elementor/v1/')),
                'nonce' => wp_create_nonce('wp_rest'),
                'postId' => $postId,
                'canUse' => $postId > 0 && current_user_can(Capabilities::USE) && current_user_can('edit_post', $postId),
                'canExecute' => $postId > 0 && current_user_can(Capabilities::EXECUTE) && current_user_can('edit_post', $postId) && !current_user_can('publish_post', $postId),
                'providerConfigured' => (new \AIEA\AI\SecretManager())->hasSecret(),
                'defaultScope' => $settings['context_scope'] ?? 'current',
            ]) . ';', 'before');
        }
    }
}
