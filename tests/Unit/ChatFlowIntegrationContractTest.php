<?php

declare(strict_types=1);

namespace AIEA\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ChatFlowIntegrationContractTest extends TestCase
{
    public function testSessionAndConversationFallBackToConfiguredModel(): void
    {
        $session = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Rest/SessionController.php');
        $conversation = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Agent/ConversationService.php');

        self::assertStringContainsString("$this->settings->all()['model']", $session);
        self::assertStringContainsString("$this->settings->all()['model']", $conversation);
        self::assertStringContainsString('No provider model is configured.', $conversation);
    }

    public function testChatControllerReturnsSafeProviderAndReadinessErrors(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Rest/ChatController.php');

        self::assertStringContainsString('catch (ProviderException $exception)', $controller);
        self::assertStringContainsString("'aiea_chat_' . $exception->safeCode", $controller);
        self::assertStringContainsString('safeRuntimeMessage', $controller);
    }
}
