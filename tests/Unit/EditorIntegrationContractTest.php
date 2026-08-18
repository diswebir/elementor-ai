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
        self::assertStringContainsString('configurationMissing ? null : new AIEAApi(config)', $bundle);
    }

    public function testEditorBundleHasAFallbackMountPoint(): void
    {
        $bundle = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/src/editor/main.tsx');

        self::assertStringContainsString("rootNode.dataset.aieaEditorRoot = 'fallback'", $bundle);
        self::assertStringContainsString('document.body.append(rootNode)', $bundle);
        self::assertStringContainsString('ویرایش با هوش مصنوعی', $bundle);
        self::assertStringContainsString('useState(false)', $bundle);
    }
}
