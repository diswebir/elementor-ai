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

    public function testEditorConfigurationExposesExecutionPolicyAndAutoMode(): void
    {
        $assets = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Admin/EditorAssets.php');
        $bundle = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/src/editor/main.tsx');

        self::assertStringContainsString("'pageStatus' => \$postId > 0 ? (string) get_post_status(\$postId) : ''", $assets);
        self::assertStringContainsString("'allowAutoMode' => !empty(\$settings['allow_auto_mode'])", $assets);
        self::assertStringContainsString("typeof parsed.pageStatus !== 'string'", $bundle);
        self::assertStringContainsString("typeof parsed.allowAutoMode !== 'boolean'", $bundle);
        self::assertStringContainsString("mode !== 'ask'", $bundle);
        self::assertStringContainsString('disabled={!config.allowAutoMode}', $bundle);
        self::assertStringContainsString('isAutoEligible(generatedActions)', $bundle);
        self::assertStringContainsString('await approve(generated.id, active, generatedActions, true)', $bundle);
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
