<?php

declare(strict_types=1);

namespace AIEA\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ProviderTestIntegrationContractTest extends TestCase
{
    public function testProviderTestRoutesRequireManagedAccessAndExposeLogs(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Rest/Routes.php');

        self::assertStringContainsString("'/providers/test'", $routes);
        self::assertStringContainsString("'/providers/test/logs'", $routes);
        self::assertStringContainsString('[$this->permission, \'manage\']', $routes);
        self::assertStringContainsString('mb_strlen($value) <= 1000', $routes);
    }

    public function testAdminTestPanelAvoidsStoringRawMessageInLogs(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Rest/ProviderController.php');

        self::assertStringContainsString("'arguments_hash' => $message", $controller);
        self::assertStringContainsString("'result_summary' => 'Provider response received.'", $controller);
        self::assertStringNotContainsString("'result_summary' => \$message", $controller);
    }

    public function testAdminBundleHasConnectionTestAndRecentLogsPanel(): void
    {
        $bundle = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/src/admin/main.tsx');

        self::assertStringContainsString('ارسال و دریافت پیام آزمایشی', $bundle);
        self::assertStringContainsString('لاگ تست‌های اخیر', $bundle);
        self::assertStringContainsString('providers/test/logs', $bundle);
    }
}
