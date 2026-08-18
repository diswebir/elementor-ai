<?php

declare(strict_types=1);

namespace AIEA\Support;

use AIEA\Admin\Capabilities;

final class PluginPermissions
{
    public function canUseForPost(int $postId): bool
    {
        return current_user_can(Capabilities::USE) && current_user_can('edit_post', $postId);
    }

    public function canExecuteForPost(int $postId): bool
    {
        return current_user_can(Capabilities::EXECUTE)
            && current_user_can('edit_post', $postId);
    }

    public function canManage(): bool
    {
        return current_user_can(Capabilities::MANAGE);
    }
}
