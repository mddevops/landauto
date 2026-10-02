<?php

namespace Tests\Unit\Blocks;

use App\Blocks\BlockStateValidator;
use App\Exceptions\InvalidBlockStateException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BlockStateValidatorTest extends TestCase
{
    private const ITEM_A = '01J9Z3QK5V8W2X4Y6Z8A0B2C4D';

    private const ITEM_B = '01J9Z3QK5V8W2X4Y6Z8A0B2C4E';

    /**
     * @return array<string, mixed>
     */
    private static function schema(): array
    {
        return ['fields' => [
            ['key' => 'title', 'type' => 'text', 'label' => 'Заголовок', 'required' => true, 'max_length' => 10],
            ['key' => 'text', 'type' => 'textarea', 'label' => 'Текст'],
            ['key' => 'show', 'type' => 'boolean', 'label' => 'Показывать'],
            ['key' => 'align', 'type' => 'select', 'label' => 'Выравнивание', 'options' => [
                ['value' => 'left', 'label' => 'Слева'],
                ['value' => 'center', 'label' => 'По центру'],
            ]],
            ['key' => 'photo', 'type' => 'image', 'label' => 'Фото'],
            ['key' => 'button', 'type' => 'group', 'label' => 'Кнопка', 'fields' => [
                ['key' => 'label', 'type' => 'text', 'label' => 'Текст кнопки'],
            ]],
            ['key' => 'items', 'type' => 'repeater', 'label' => 'Элементы', 'min_items' => 1, 'max_items' => 2, 'fields' => [
                ['key' => 'name', 'type' => 'text', 'label' => 'Название'],
            ]],
        ]];
    }

    public function test_complete_and_incomplete_draft_states_are_valid(): void
    {
        $validator = new BlockStateValidator;

        $this->assertSame([], $validator->errors(self::schema(), [
            'title' => 'Акция',
            'text' => 'Описание',
            'show' => false,
            'align' => 'center',
            'photo' => null,
            'button' => ['label' => 'Подробнее'],
            'items' => [
                ['id' => self::ITEM_A, 'name' => 'Первый'],
                ['id' => self::ITEM_B],
            ],
        ]));
        $this->assertSame([], $validator->errors(self::schema(), []));
        $this->assertSame([], $validator->errors(self::schema(), ['title' => null, 'items' => []]));
        $validator->assertValid(self::schema(), ['button' => []]);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidStates(): array
    {
        return [
            'list root' => [['Акция'], 'state'],
            'scalar root' => ['Акция', 'state'],
            'unknown key' => [['secret' => 'token'], 'state.secret'],
            'text type' => [['title' => 5], 'state.title'],
            'text too long' => [['title' => str_repeat('я', 11)], 'state.title'],
            'boolean type' => [['show' => 'yes'], 'state.show'],
            'select option' => [['align' => 'right'], 'state.align'],
            'image reference' => [['photo' => 'https://example.com/a.jpg'], 'state.photo'],
            'group type' => [['button' => 'Подробнее'], 'state.button'],
            'group unknown key' => [['button' => ['url' => 'javascript:alert(1)']], 'state.button.url'],
            'repeater type' => [['items' => ['id' => self::ITEM_A]], 'state.items'],
            'repeater too many' => [['items' => [
                ['id' => self::ITEM_A], ['id' => self::ITEM_B], ['id' => '01J9Z3QK5V8W2X4Y6Z8A0B2C4F'],
            ]], 'state.items'],
            'repeater missing id' => [['items' => [['name' => 'Без id']]], 'state.items.0.id'],
            'repeater invalid id' => [['items' => [['id' => '1']]], 'state.items.0.id'],
            'repeater duplicate id' => [['items' => [['id' => self::ITEM_A], ['id' => self::ITEM_A]]], 'state.items.1.id'],
            'repeater item field' => [['items' => [['id' => self::ITEM_A, 'name' => ['x']]]], 'state.items.0.name'],
        ];
    }

    #[DataProvider('invalidStates')]
    public function test_invalid_state_reports_russian_error_at_path(mixed $state, string $path): void
    {
        $errors = (new BlockStateValidator)->errors(self::schema(), $state);

        $this->assertArrayHasKey($path, $errors, 'Errors: '.json_encode($errors, JSON_UNESCAPED_UNICODE));
        $this->assertMatchesRegularExpression('/\p{Cyrillic}/u', $errors[$path]);
    }

    public function test_assert_valid_throws_with_errors(): void
    {
        $this->expectException(InvalidBlockStateException::class);

        (new BlockStateValidator)->assertValid(self::schema(), ['title' => 1]);
    }
}
