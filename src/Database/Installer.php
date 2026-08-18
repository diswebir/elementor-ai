<?php

declare(strict_types=1);

namespace AIEA\Database;

use AIEA\Admin\Capabilities;

final class Installer
{
    private const SCHEMA_VERSION = '1.0.0';
    private const OPTION = 'aiea_schema_version';

    public static function activate(): void
    {
        $installer = new self();
        $installer->migrate();
        $installer->grantCapabilities();
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('aiea_cleanup_retention');
    }

    public function maybeMigrate(): void
    {
        if (get_option(self::OPTION) !== self::SCHEMA_VERSION) {
            $this->migrate();
        }

        if (!wp_next_scheduled('aiea_cleanup_retention')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'aiea_cleanup_retention');
        }
    }

    private function grantCapabilities(): void
    {
        $administrator = get_role('administrator');
        if ($administrator === null) {
            return;
        }

        foreach (Capabilities::all() as $capability) {
            $administrator->add_cap($capability);
        }
    }

    private function migrate(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'ai_elementor_';

        $queries = [
            "CREATE TABLE {$prefix}providers (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                provider_type varchar(40) NOT NULL,
                provider_alias varchar(120) NOT NULL,
                base_url varchar(500) NOT NULL,
                encrypted_secret longtext NULL,
                secret_reference varchar(190) NULL,
                config_json longtext NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'active',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY status (status)
            ) $charset;",
            "CREATE TABLE {$prefix}models (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                provider_id bigint(20) unsigned NOT NULL,
                model_id varchar(190) NOT NULL,
                display_name varchar(190) NOT NULL,
                capabilities_json longtext NOT NULL,
                is_enabled tinyint(1) NOT NULL DEFAULT 1,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY provider_model (provider_id, model_id),
                KEY provider_id (provider_id)
            ) $charset;",
            "CREATE TABLE {$prefix}conversations (
                id char(36) NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                page_id bigint(20) unsigned NOT NULL,
                provider_id bigint(20) unsigned NULL,
                model_id varchar(190) NOT NULL,
                context_scope varchar(20) NOT NULL,
                context_hash char(64) NOT NULL,
                status varchar(30) NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY user_page (user_id, page_id),
                KEY status (status)
            ) $charset;",
            "CREATE TABLE {$prefix}messages (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                conversation_id char(36) NOT NULL,
                role varchar(20) NOT NULL,
                content_redacted longtext NOT NULL,
                tool_name varchar(100) NULL,
                tool_call_id varchar(100) NULL,
                metadata_json longtext NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY conversation_id (conversation_id)
            ) $charset;",
            "CREATE TABLE {$prefix}plans (
                id char(36) NOT NULL,
                conversation_id char(36) NOT NULL,
                version int unsigned NOT NULL DEFAULT 1,
                plan_json longtext NOT NULL,
                plan_hash char(64) NOT NULL,
                risk_level varchar(20) NOT NULL,
                approval_state varchar(30) NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY conversation_id (conversation_id),
                KEY approval_state (approval_state)
            ) $charset;",
            "CREATE TABLE {$prefix}jobs (
                id char(36) NOT NULL,
                plan_id char(36) NOT NULL,
                page_id bigint(20) unsigned NOT NULL,
                actor_id bigint(20) unsigned NOT NULL,
                state varchar(30) NOT NULL,
                cursor int unsigned NOT NULL DEFAULT 0,
                document_hash char(64) NOT NULL,
                idempotency_key varchar(100) NOT NULL,
                locked_until datetime NULL,
                lock_token varchar(100) NULL,
                last_error_code varchar(100) NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY idempotency_key (idempotency_key),
                KEY page_state (page_id, state),
                KEY lock_state (locked_until, state)
            ) $charset;",
            "CREATE TABLE {$prefix}tasks (
                id char(36) NOT NULL,
                job_id char(36) NOT NULL,
                action_id varchar(100) NOT NULL,
                step_number int unsigned NOT NULL,
                tool_name varchar(100) NOT NULL,
                arguments_hash char(64) NOT NULL,
                arguments_json longtext NOT NULL,
                state varchar(30) NOT NULL,
                retries tinyint unsigned NOT NULL DEFAULT 0,
                result_json longtext NULL,
                error_code varchar(100) NULL,
                started_at datetime NULL,
                completed_at datetime NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY job_action (job_id, action_id),
                KEY job_state (job_id, state)
            ) $charset;",
            "CREATE TABLE {$prefix}task_logs (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                task_id char(36) NOT NULL,
                event varchar(100) NOT NULL,
                metadata_json longtext NULL,
                duration_ms int unsigned NULL,
                error_code varchar(100) NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY task_id (task_id),
                KEY event_created (event, created_at)
            ) $charset;",
            "CREATE TABLE {$prefix}snapshots (
                id char(36) NOT NULL,
                page_id bigint(20) unsigned NOT NULL,
                source varchar(30) NOT NULL,
                document_hash char(64) NOT NULL,
                document_compressed longtext NOT NULL,
                size_bytes int unsigned NOT NULL,
                expires_at datetime NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY page_created (page_id, created_at),
                KEY expires_at (expires_at)
            ) $charset;",
            "CREATE TABLE {$prefix}audit_log (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                actor_id bigint(20) unsigned NULL,
                page_id bigint(20) unsigned NULL,
                conversation_id char(36) NULL,
                job_id char(36) NULL,
                task_id char(36) NULL,
                event varchar(100) NOT NULL,
                arguments_hash char(64) NULL,
                result_summary varchar(500) NULL,
                status varchar(30) NOT NULL,
                duration_ms int unsigned NULL,
                error_code varchar(100) NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY page_created (page_id, created_at),
                KEY job_id (job_id),
                KEY event_created (event, created_at)
            ) $charset;",
            "CREATE TABLE {$prefix}usage (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                actor_id bigint(20) unsigned NULL,
                provider_id bigint(20) unsigned NULL,
                model_id varchar(190) NULL,
                request_id varchar(100) NOT NULL,
                input_tokens int unsigned NOT NULL DEFAULT 0,
                output_tokens int unsigned NOT NULL DEFAULT 0,
                action_count int unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY actor_created (actor_id, created_at)
            ) $charset;",
            "CREATE TABLE {$prefix}memory (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                scope varchar(30) NOT NULL,
                scope_id bigint(20) unsigned NULL,
                memory_key varchar(190) NOT NULL,
                value_redacted longtext NOT NULL,
                expires_at datetime NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY scope_memory (scope, scope_id, memory_key),
                KEY expires_at (expires_at)
            ) $charset;",
        ];

        foreach ($queries as $query) {
            dbDelta($query);
        }

        update_option(self::OPTION, self::SCHEMA_VERSION, false);
    }
}
