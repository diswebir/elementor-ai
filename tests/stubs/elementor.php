<?php
declare(strict_types=1);

namespace Elementor;

final class Plugin
{
    public static ?self $instance = null;

    public DocumentManager $documents;

    public FilesManager $files_manager;
}

final class DocumentManager
{
    public function get(int $postId): Document
    {
        return new Document();
    }
}

class Document
{
    /** @return array<int, array<string, mixed>> */
    public function get_elements_data(): array
    {
        return [];
    }

    /** @param array<string, mixed> $data */
    public function save(array $data): mixed
    {
        return true;
    }

    /** @return array<string, mixed>|array<int, mixed>|mixed */
    public function get_settings(?string $key = null): mixed
    {
        return [];
    }
}

final class FilesManager
{
    public function clear_cache(): void
    {
    }
}
