<?php

declare(strict_types=1);

namespace AIEA\Database;

final class TableNames
{
    public function get(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ai_elementor_' . $name;
    }
}
