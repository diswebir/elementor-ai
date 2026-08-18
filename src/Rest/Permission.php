<?php

declare(strict_types=1);

namespace AIEA\Rest;

use AIEA\Support\PluginPermissions;
use WP_Error;
use WP_REST_Request;

final class Permission
{
    public function __construct(private readonly PluginPermissions $permissions)
    {
    }

    public function usePage(WP_REST_Request $request): bool|WP_Error
    {
        $nonce = $this->verifyNonce($request);
        if ($nonce instanceof WP_Error) {
            return $nonce;
        }
        $postId = absint($request->get_param('post_id'));
        if ($postId === 0 || !$this->permissions->canUseForPost($postId)) {
            return new WP_Error('aiea_forbidden', __('You cannot access AI context for this page.', 'ai-elementor-ag'), ['status' => 403]);
        }
        return true;
    }

    public function executePage(WP_REST_Request $request): bool|WP_Error
    {
        $nonce = $this->verifyNonce($request);
        if ($nonce instanceof WP_Error) {
            return $nonce;
        }
        $postId = absint($request->get_param('post_id'));
        $post = $postId > 0 ? get_post($postId) : null;
        if ($post === null || $post->post_status !== 'draft' || !$this->permissions->canExecuteForPost($postId)) {
            return new WP_Error('aiea_execute_forbidden', __('AI actions are only allowed on a draft page that you can edit.', 'ai-elementor-ag'), ['status' => 403]);
        }
        return true;
    }

    public function manage(WP_REST_Request $request): bool|WP_Error
    {
        $nonce = $this->verifyNonce($request);
        if ($nonce instanceof WP_Error) {
            return $nonce;
        }
        if (!$this->permissions->canManage()) {
            return new WP_Error('aiea_forbidden', __('You cannot manage AI provider settings.', 'ai-elementor-ag'), ['status' => 403]);
        }
        return true;
    }

    private function verifyNonce(WP_REST_Request $request): true|WP_Error
    {
        $nonce = (string) $request->get_header('X-WP-Nonce');
        if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('aiea_invalid_nonce', __('The AI editor request expired. Refresh the editor and try again.', 'ai-elementor-ag'), ['status' => 403]);
        }
        return true;
    }
}
