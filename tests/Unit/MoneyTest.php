<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{string, int|null}>
     */
    public static function inputs(): array
    {
        return [
            'integer rubles' => ['1850000', 185_000_000],
            'spaces and comma' => ['1 850 000,5', 185_000_050],
            'non-breaking spaces' => ["1\u{00A0}850\u{00A0}000.05", 185_000_005],
            'zero' => ['0', 0],
            'leading zeros' => ['000120', 12_000],
            'maximum' => ['10000000000000', Money::MAX_MINOR],
            'over maximum' => ['10000000000000.01', null],
            'three decimals' => ['1.005', null],
            'negative' => ['-100', null],
            'exponent' => ['1e6', null],
            'empty' => ['', null],
            'letters' => ['100 руб', null],
            'two separators' => ['1.000,00', null],
        ];
    }

    #[DataProvider('inputs')]
    public function test_parses_decimal_strings_into_minor_units(string $input, ?int $expected): void
    {
        $this->assertSame($expected, Money::parse($input));
    }

    public function test_formats_minor_units_without_floats(): void
    {
        $this->assertSame("1\u{00A0}850\u{00A0}000\u{00A0}₽", Money::format(185_000_000));
        $this->assertSame("999,05\u{00A0}₽", Money::format(99_905));
        $this->assertSame("0,07\u{00A0}₽", Money::format(7));
        $this->assertSame('1850000,50', Money::toInput(185_000_050));
        $this->assertSame('0', Money::toInput(0));
    }

    public function test_unsupported_currency_is_rejected(): void
    {
        $this->assertFalse(Money::supports('USD'));
        $this->expectException(InvalidArgumentException::class);
        Money::parse('10', 'USD');
    }
}
