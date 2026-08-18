<?php

declare(strict_types=1);

namespace AIEA\Tests\Unit;

use AIEA\Agent\ContextBudget;
use PHPUnit\Framework\TestCase;

final class PlanBudgetContractTest extends TestCase
{
    public function testPlanBudgetCompactsElementTreeAndSettings(): void
    {
        $budget = new ContextBudget(40000, 14000);
        $context = [
            'document' => [
                'content' => array_fill(0, 20, [
                    'id' => 'element',
                    'type' => 'widget',
                    'widgetType' => 'text-editor',
                    'settings' => ['editor' => str_repeat('x', 900)],
                    'children' => [],
                ]),
                'page_settings' => ['title' => str_repeat('y', 900)],
            ],
            'globals' => ['unused' => str_repeat('z', 900)],
        ];

        $result = $budget->forPlan($context);

        self::assertTrue($result['plan_context_reduced']);
        self::assertCount(14, $result['document']['content']);
        self::assertSame(500, mb_strlen($result['document']['content'][0]['settings']['editor']));
        self::assertSame(500, mb_strlen($result['document']['page_settings']['title']));
    }

    public function testPlanServiceUsesDedicatedBudgetAndTimeout(): void
    {
        $service = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Agent/PlanService.php');
        $provider = (string) file_get_contents(dirname(__DIR__, 2) . '/src/AI/OpenAICompatibleProvider.php');

        self::assertStringContainsString('$this->context->planInput($context[\'data\'])', $service);
        self::assertStringContainsString('min(1800, max(800', $service);
        self::assertStringContainsString('min(120, max(60', $service);
        self::assertStringContainsString('$options->timeout ??', $provider);
    }
}
