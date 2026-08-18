<?php

declare(strict_types=1);

namespace AIEA\Tests\Unit;

use AIEA\Agent\AgentState;
use AIEA\Agent\PlanSchemaValidator;
use AIEA\Agent\Redactor;
use AIEA\Agent\StateMachine;
use AIEA\Audit\DiffService;
use AIEA\Elementor\CapabilityCatalog;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CoreLogicTest extends TestCase
{
    public function testRedactorRemovesSensitiveKeysAndTokens(): void
    {
        $result = (new Redactor())->redact([
            'api_key' => 'sk-live-should-not-leak',
            'nested' => ['authorization' => 'Bearer token-that-must-not-leak'],
            'text' => 'This text contains sk-abcdefghijklmnop but must be masked.',
        ]);

        self::assertSame('[REDACTED]', $result['api_key']);
        self::assertSame('[REDACTED]', $result['nested']['authorization']);
        self::assertStringNotContainsString('sk-abcdefghijklmnop', $result['text']);
    }

    public function testStateMachineRejectsInvalidTransition(): void
    {
        $stateMachine = new StateMachine();
        $this->expectException(DomainException::class);
        $stateMachine->assertTransition(AgentState::Idle, AgentState::Completed);
    }

    public function testPlanValidatorAcceptsAllowlistedWidget(): void
    {
        $validator = new PlanSchemaValidator(new CapabilityCatalog());
        $plan = $validator->validate([
            'schema_version' => '1.0',
            'goal' => 'ساخت عنوان',
            'assumptions' => [],
            'acceptance_criteria' => [],
            'risk_level' => 'low',
            'actions' => [[
                'id' => 'a-hero',
                'tool' => 'create_widget',
                'depends_on' => [],
                'args' => ['parent' => 'root', 'widget_type' => 'heading', 'settings' => ['title' => 'نمونه']],
                'description' => 'ایجاد عنوان',
                'risk_level' => 'low',
                'requires_approval' => true,
            ]],
        ]);

        self::assertSame('create_widget', $plan['actions'][0]['tool']);
    }

    public function testPlanValidatorRejectsUnapprovedWidget(): void
    {
        $validator = new PlanSchemaValidator(new CapabilityCatalog());
        $this->expectException(InvalidArgumentException::class);
        $validator->validate([
            'schema_version' => '1.0', 'goal' => 'unsafe', 'assumptions' => [], 'acceptance_criteria' => [], 'risk_level' => 'low',
            'actions' => [[
                'id' => 'a-html', 'tool' => 'create_widget', 'depends_on' => [],
                'args' => ['parent' => 'root', 'widget_type' => 'html', 'settings' => []],
                'description' => 'unsafe', 'risk_level' => 'high', 'requires_approval' => true,
            ]],
        ]);
    }

    public function testDiffServiceIdentifiesAddedModifiedAndDeletedElements(): void
    {
        $diff = (new DiffService())->compare(
            ['content' => [['id' => 'one', 'settings' => ['title' => 'A']], ['id' => 'deleted']]],
            ['content' => [['id' => 'one', 'settings' => ['title' => 'B']], ['id' => 'added']]],
        );

        self::assertSame(['added'], $diff['added']);
        self::assertSame(['one'], $diff['modified']);
        self::assertSame(['deleted'], $diff['deleted']);
    }
}
