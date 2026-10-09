<?php

namespace App\Blocks;

use App\Models\BlockVersion;

final class BlockSourceFingerprint
{
    /** @param array{html:string,css:string,js:string,schema:string} $sources */
    public function fromDraft(array $sources): string
    {
        $schema = json_decode($sources['schema'], true, 64, JSON_THROW_ON_ERROR);

        return $this->hash($sources['html'], $sources['css'], $sources['js'], $schema);
    }

    public function fromVersion(BlockVersion $version): string
    {
        return $this->hash((string) $version->html, (string) $version->css, (string) $version->js, $version->schema_json);
    }

    private function hash(string $html, string $css, string $js, mixed $schema): string
    {
        $payload = ['html' => $html, 'css' => $css, 'js' => $js, 'schema' => $this->canonical($schema)];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map($this->canonical(...), $value);
    }
}
