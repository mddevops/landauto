<?php

namespace App\Blocks\Native;

use Closure;
use stdClass;

/**
 * Template props of a placed Native Block, with the semantics of the sandbox `instanceProps`
 * (resources/js/sandbox/instance-props.ts): the validated Instance state restricted to the
 * published schema; images become `{url, alt}` from version-scoped asset URLs; action and vehicle
 * values are never exposed to the template. Objects stay objects so truthiness matches JavaScript.
 */
final class NativeProps
{
    /** @var list<string> */
    private array $urls = [];

    /**
     * @param  Closure(string): ?string  $assetUrl
     */
    private function __construct(private Closure $assetUrl) {}

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  Closure(string): ?string  $assetUrl
     * @return array{0: stdClass, 1: list<string>} props and the trusted image URLs they contain
     */
    public static function build(array $fields, mixed $state, Closure $assetUrl): array
    {
        $builder = new self($assetUrl);
        $props = $builder->object($fields, $state);

        return [$props, array_values(array_unique($builder->urls))];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private function object(array $fields, mixed $state): stdClass
    {
        $source = is_array($state) ? $state : [];
        $props = new stdClass;

        foreach ($fields as $field) {
            $key = (string) ($field['key'] ?? '');
            $value = $source[$key] ?? null;
            /** @var list<array<string, mixed>> $nested */
            $nested = is_array($field['fields'] ?? null) ? $field['fields'] : [];

            switch ($field['type'] ?? null) {
                case 'text':
                case 'textarea':
                case 'select':
                    if (is_string($value)) {
                        $props->{$key} = $value;
                    }

                    break;
                case 'number':
                    if (is_int($value) || is_float($value)) {
                        $props->{$key} = $value;
                    }

                    break;
                case 'boolean':
                    $props->{$key} = $value === true;
                    break;
                case 'image':
                    $url = is_string($value) && $value !== '' ? ($this->assetUrl)($value) : null;

                    if ($url !== null) {
                        $this->urls[] = $url;
                        $props->{$key} = (object) ['url' => $url, 'alt' => ''];
                    }

                    break;
                case 'group':
                    $props->{$key} = $this->object($nested, $value);
                    break;
                case 'repeater':
                    $props->{$key} = array_map(fn (mixed $item): stdClass => $this->object($nested, $item), is_array($value) ? array_values($value) : []);
                    break;
            }
        }

        return $props;
    }
}
