<?php

namespace Tests\Unit\Blocks;

use App\Blocks\BlockSchemaValidator;
use App\Exceptions\InvalidBlockSchemaException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BlockSchemaValidatorTest extends TestCase
{
    public function test_schema_with_all_initial_field_types_is_valid(): void
    {
        $schema = ['fields' => [
            ['key' => 'title', 'type' => 'text', 'label' => 'Заголовок', 'required' => true, 'max_length' => 120, 'default' => 'Новые автомобили'],
            ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Подзаголовок', 'help' => 'Короткое описание'],
            ['key' => 'show_button', 'type' => 'boolean', 'label' => 'Показывать кнопку', 'default' => true],
            ['key' => 'align', 'type' => 'select', 'label' => 'Выравнивание', 'options' => [
                ['value' => 'left', 'label' => 'Слева'],
                ['value' => 'center', 'label' => 'По центру'],
            ], 'default' => 'center'],
            ['key' => 'background', 'type' => 'image', 'label' => 'Фон'],
            ['key' => 'button', 'type' => 'group', 'label' => 'Кнопка', 'fields' => [
                ['key' => 'label', 'type' => 'text', 'label' => 'Текст кнопки'],
                ['key' => 'id', 'type' => 'text', 'label' => 'Якорь'],
            ]],
            ['key' => 'slides', 'type' => 'repeater', 'label' => 'Слайды', 'min_items' => 1, 'max_items' => 5, 'fields' => [
                ['key' => 'title', 'type' => 'text', 'label' => 'Заголовок слайда'],
                ['key' => 'buttons', 'type' => 'repeater', 'label' => 'Кнопки', 'max_items' => 2, 'fields' => [
                    ['key' => 'label', 'type' => 'text', 'label' => 'Текст'],
                ]],
            ]],
        ]];

        $validator = new BlockSchemaValidator;

        $this->assertSame([], $validator->errors($schema));
        $validator->assertValid($schema);
        $this->assertSame([], $validator->errors(['fields' => []]));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidSchemas(): array
    {
        $text = ['key' => 'title', 'type' => 'text', 'label' => 'Заголовок'];
        $repeater = fn (array $fields, int $max = 3): array => ['key' => 'items', 'type' => 'repeater', 'label' => 'Элементы', 'max_items' => $max, 'fields' => $fields];

        return [
            'not an object' => ['text', 'schema'],
            'list root' => [[$text], 'schema'],
            'missing fields' => [[], 'fields'],
            'unknown root key' => [['fields' => [], 'renderer' => 'x'], 'schema.renderer'],
            'fields not list' => [['fields' => ['a' => $text]], 'fields'],
            'invalid key' => [['fields' => [[...$text, 'key' => 'Title']]], 'fields.0.key'],
            'duplicate key' => [['fields' => [$text, $text]], 'fields.1.key'],
            'unsupported type' => [['fields' => [[...$text, 'type' => 'richtext']]], 'fields.0.type'],
            'missing label' => [['fields' => [['key' => 'title', 'type' => 'text']]], 'fields.0.label'],
            'unknown field option' => [['fields' => [[...$text, 'options' => []]]], 'fields.0.options'],
            'text too long limit' => [['fields' => [[...$text, 'max_length' => 256]]], 'fields.0.max_length'],
            'default over length' => [['fields' => [[...$text, 'max_length' => 3, 'default' => 'длинно']]], 'fields.0.default'],
            'non-bool required' => [['fields' => [[...$text, 'required' => 'yes']]], 'fields.0.required'],
            'boolean default' => [['fields' => [['key' => 'on', 'type' => 'boolean', 'label' => 'Вкл', 'default' => 1]]], 'fields.0.default'],
            'select without options' => [['fields' => [['key' => 'a', 'type' => 'select', 'label' => 'A', 'options' => []]]], 'fields.0.options'],
            'select duplicate value' => [['fields' => [['key' => 'a', 'type' => 'select', 'label' => 'A', 'options' => [
                ['value' => 'x', 'label' => 'X'], ['value' => 'x', 'label' => 'Y'],
            ]]]], 'fields.0.options.1.value'],
            'select unknown default' => [['fields' => [['key' => 'a', 'type' => 'select', 'label' => 'A', 'options' => [
                ['value' => 'x', 'label' => 'X'],
            ], 'default' => 'z']]], 'fields.0.default'],
            'image default' => [['fields' => [['key' => 'img', 'type' => 'image', 'label' => 'Фото', 'default' => 'x.jpg']]], 'fields.0.default'],
            'empty group' => [['fields' => [['key' => 'g', 'type' => 'group', 'label' => 'Группа', 'fields' => []]]], 'fields.0.fields'],
            'repeater without max' => [['fields' => [['key' => 'items', 'type' => 'repeater', 'label' => 'Элементы', 'fields' => [$text]]]], 'fields.0.max_items'],
            'repeater max too high' => [['fields' => [$repeater([$text], 51)]], 'fields.0.max_items'],
            'repeater min over max' => [['fields' => [[...$repeater([$text], 2), 'min_items' => 3]]], 'fields.0.min_items'],
            'repeater reserved id' => [['fields' => [$repeater([[...$text, 'key' => 'id']])]], 'fields.0.fields.0.key'],
            'nested field error path' => [['fields' => [$repeater([[...$text, 'type' => 'video']])]], 'fields.0.fields.0.type'],
            'repeater nesting' => [['fields' => [$repeater([$repeater([$repeater([$text])])])]], 'fields.0.fields.0.fields.0'],
            'container depth' => [['fields' => [['key' => 'a', 'type' => 'group', 'label' => 'A', 'fields' => [
                ['key' => 'b', 'type' => 'group', 'label' => 'B', 'fields' => [
                    ['key' => 'c', 'type' => 'group', 'label' => 'C', 'fields' => [
                        ['key' => 'd', 'type' => 'group', 'label' => 'D', 'fields' => [$text]],
                    ]],
                ]],
            ]]]], 'fields.0.fields.0.fields.0.fields.0'],
        ];
    }

    #[DataProvider('invalidSchemas')]
    public function test_invalid_schema_reports_russian_error_at_path(mixed $schema, string $path): void
    {
        $errors = (new BlockSchemaValidator)->errors($schema);

        $this->assertArrayHasKey($path, $errors, 'Errors: '.json_encode($errors, JSON_UNESCAPED_UNICODE));
        $this->assertMatchesRegularExpression('/\p{Cyrillic}/u', $errors[$path]);
    }

    public function test_assert_valid_throws_with_all_errors(): void
    {
        try {
            (new BlockSchemaValidator)->assertValid(['fields' => [['key' => '1', 'type' => 'nope']]]);
            $this->fail('Invalid schema must throw.');
        } catch (InvalidBlockSchemaException $exception) {
            $this->assertArrayHasKey('fields.0.key', $exception->errors);
            $this->assertArrayHasKey('fields.0.type', $exception->errors);
        }
    }
}
