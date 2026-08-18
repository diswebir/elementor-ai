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

    public function testEditorBundleHasAFallbackMountPoint(): void
    {
        $bundle = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/src/editor/main.tsx');

        self::assertStringContainsString("rootNode.dataset.aieaEditorRoot = 'fallback'", $bundle);
        self::assertStringContainsString('document.body.append(rootNode)', $bundle);
    }
}
