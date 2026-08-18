<?php

declare(strict_types=1);

namespace AIEA\Tests\Unit;

use AIEA\Agent\PlanJsonDecoder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PlanJsonDecoderTest extends TestCase
{
    public function testItDecodesDirectJson(): void
    {
        $decoder = new PlanJsonDecoder();

        self::assertSame(['goal' => 'test'], $decoder->decode('{"goal":"test"}'));
    }

    public function testItDecodesFencedJson(): void
    {
        $decoder = new PlanJsonDecoder();

        self::assertSame(['goal' => 'test'], $decoder->decode("```json\n{\"goal\":\"test\"}\n```"));
    }

    public function testItExtractsJsonFromSurroundingText(): void
    {
        $decoder = new PlanJsonDecoder();

        self::assertSame(['goal' => 'test', 'meta' => ['brace' => '}']], $decoder->decode('Here is the plan: {"goal":"test","meta":{"brace":"}"}} End.'));
    }

    public function testItRejectsResponseWithoutJsonObject(): void
    {
        $decoder = new PlanJsonDecoder();

        $this->expectException(InvalidArgumentException::class);
        $decoder->decode('I cannot provide JSON.');
    }
}
