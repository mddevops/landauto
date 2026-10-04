<?php

namespace Tests\Unit\Support;

use App\Support\PhoneNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    #[DataProvider('validPhones')]
    public function test_valid_phones_normalize_to_digits(string $input, string $expected): void
    {
        $normalizer = new PhoneNormalizer;

        $this->assertNull($normalizer->error($input));
        $this->assertSame($expected, $normalizer->normalize($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validPhones(): array
    {
        return [
            'plus seven with formatting' => ['+7 (999) 111-22-33', '79991112233'],
            'spaces only' => ['7 999 111 22 33', '79991112233'],
            'parentheses and hyphens' => ['(999)111-22-33', '9991112233'],
            'dots' => ['+7.999.111.22.33', '79991112233'],
            'russian trunk eight rewritten' => ['8 (999) 111-22-33', '79991112233'],
            'russian trunk eight digits only' => ['89991112233', '79991112233'],
            'plus eight is international and kept' => ['+8 999 111 22 33', '89991112233'],
            'foreign thirteen digits starting with eight kept' => ['86 1380 013 8000', '8613800138000'],
            'ten digits starting with eight kept' => ['800 111 22 33', '8001112233'],
            'surrounding whitespace' => ['  +79991112233  ', '79991112233'],
            'international max length' => ['+123 456 789 012 345', '123456789012345'],
        ];
    }

    #[DataProvider('invalidPhones')]
    public function test_invalid_phones_are_rejected(?string $input): void
    {
        $normalizer = new PhoneNormalizer;

        $this->assertNull($normalizer->normalize($input));

        if ($input !== null) {
            $this->assertNotNull($normalizer->error($input));
        }
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function invalidPhones(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'letters' => ['+7 999 ABC-22-33'],
            'plus in the middle' => ['7+9991112233'],
            'extension marker' => ['+7 999 111-22-33 доб. 5'],
            'too short' => ['+7 999 111'],
            'too long' => ['+7 999 111 22 33 44 55 6'],
            'only formatting' => ['(--)'],
        ];
    }
}
