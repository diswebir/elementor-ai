<?php

declare(strict_types=1);

namespace AIEA\Tests\Unit;

use AIEA\Support\Temperature;
use PHPUnit\Framework\TestCase;

final class TemperatureTest extends TestCase
{
    /** @dataProvider decimalTemperatureProvider */
    public function testItPreservesValidDecimalTemperatures(mixed $input, string $expected): void
    {
        self::assertSame($expected, Temperature::normalize($input));
    }

    /** @return array<string, array{mixed, string}> */
    public static function decimalTemperatureProvider(): array
    {
        return [
            'english decimal' => ['0.2', '0.2'],
            'two decimal places' => ['0.75', '0.75'],
            'persian decimal separator and digits' => ['۰٫۷۵', '0.75'],
            'arabic digits' => ['١٫٥', '1.5'],
            'comma decimal separator' => ['1,25', '1.25'],
            'integer zero' => ['0', '0'],
            'integer one' => [1, '1'],
        ];
    }

    public function testItClampsTemperatureToSupportedRange(): void
    {
        self::assertSame('2', Temperature::normalize('2.8'));
        self::assertSame('0', Temperature::normalize('-0.5'));
    }

    public function testItKeepsFallbackForInvalidValue(): void
    {
        self::assertSame('0.75', Temperature::normalize('invalid', '0.75'));
    }
}
