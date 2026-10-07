<?php

namespace App\Http\Requests\Blocks;

use App\Blocks\BlockStudio;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Raw Draft sources (kept verbatim, see the trim exclusions in bootstrap/app.php) plus the revision
 * the editor last loaded. Schema validity is reported, not enforced, so unfinished work is saved.
 */
class SaveBlockDraftRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $sources = $this->input('sources');

        if (! is_array($sources)) {
            return;
        }

        foreach (array_keys(BlockStudio::SOURCES) as $key) {
            $sources[$key] ??= '';
        }

        $this->merge(['sources' => $sources]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'revision' => ['required', 'integer', 'min:0'],
            'sources' => ['required', 'array:'.implode(',', array_keys(BlockStudio::SOURCES))],
        ];

        foreach (array_keys(BlockStudio::SOURCES) as $key) {
            $rules["sources.{$key}"] = ['present', 'string', $this->maxBytes(...)];
        }

        $rules['preview'] = ['nullable', 'array', $this->previewSize(...)];

        return $rules;
    }

    private function maxBytes(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) > BlockStudio::SOURCE_MAX_BYTES) {
            $fail('Файл :attribute больше 64 КБ. Сократите код.');
        }
    }

    private function previewSize(string $attribute, mixed $value, Closure $fail): void
    {
        $json = json_encode($value);

        if ($json === false || strlen($json) > BlockStudio::SOURCE_MAX_BYTES) {
            $fail('Данные предпросмотра больше 64 КБ. Сократите тексты или количество элементов.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        /** @var array<string, mixed>|null $preview */
        $preview = $this->validated('preview');

        return $preview ?? [];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sources.html' => 'index.html',
            'sources.css' => 'styles.css',
            'sources.js' => 'script.js',
            'sources.schema' => 'schema.json',
        ];
    }

    /**
     * @return array{html: string, css: string, js: string, schema: string}
     */
    public function sources(): array
    {
        /** @var array{html: string, css: string, js: string, schema: string} $sources */
        $sources = $this->validated('sources');

        return $sources;
    }
}
