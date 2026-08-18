<?php

declare(strict_types=1);

namespace AIEA\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EditorIntegrationContractTest extends TestCase
{
    public function testPluginRegistersEditorRootBeforeAssetEnqueueing(): void
    {
        $plugin = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Core/Plugin.php');

        self::assertStringContainsString("add_action('elementor/editor/footer'", $plugin);
        self::assertStringContainsString("add_action('elementor/editor/after_enqueue_scripts'", $plugin);
    }

    public function testEditorAssetsEmitTheViteBundleAsAnEsModule(): void
    {
        $assets = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Admin/EditorAssets.php');

        self::assertStringContainsString("add_filter('script_loader_tag'", $assets);
        self::assertStringContainsString('<script type="module"', $assets);
    }

    public function testEditorConfigurationHasADomFallbackAndBlocksEmptyNonceRequests(): void
    {
        $assets = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Admin/EditorAssets.php');
        $bundle = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/src/editor/main.tsx');

        self::assertStringContainsString('data-aiea-editor-config=', $assets);
        self::assertStringContainsString('dataset.aieaEditorConfig', $bundle);
        self::assertStringContainsString('resolveEditorConfig()', $bundle);
        self::assertStringContainsString('configurationMissing = !resolvedConfig', $bundle);
    }

    public function testSimplifiedEditorUsesTheOfficialElementorCreateCommand(): void
    {
        $assets = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Admin/EditorAssets.php');
        $bundle = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/src/editor/main.tsx');
        $settings = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Admin/Settings.php');
        $permission = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Rest/Permission.php');

        self::assertStringContainsString("'pageStatus' => \$postId > 0 ? (string) get_post_status(\$postId) : ''", $assets);
        self::assertStringContainsString("'simple_editor_mode' => true", $settings);
        self::assertStringContainsString("commandApi.run('document/elements/create'", $bundle);
        self::assertStringContainsString('getPreviewContainer', $bundle);
        self::assertStringContainsString('animateDrop(title)', $bundle);
        self::assertStringContainsString('گفت‌وگو، تحلیل، Plan، Job، اجرای خودکار و Rollback موقتاً غیرفعال‌اند.', $bundle);
        self::assertStringContainsString('aiea_simple_editor_mode', $permission);
    }

    public function testEditorBundleHasAFallbackMountPoint(): void
    {
        $bundle = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/src/editor/main.tsx');

        self::assertStringContainsString("rootNode.dataset.aieaEditorRoot = 'fallback'", $bundle);
        self::assertStringContainsString('document.body.append(rootNode)', $bundle);
        self::assertStringContainsString('افزودن با هوش مصنوعی', $bundle);
        self::assertStringContainsString('useState(false)', $bundle);
    }
}
