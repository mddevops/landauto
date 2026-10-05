<?php

namespace Tests\Support;

use App\Publishing\Rendering\PageRenderer;
use App\Publishing\Rendering\PageRenderException;
use Closure;

/**
 * Deterministic stand-in for the compiled React renderer: PHPUnit runs before the frontend
 * build. It prints every string in the Block states and the vehicle prices of the payload.
 */
final class FakePageRenderer implements PageRenderer
{
    /** @var list<list<array<string, mixed>>> */
    public array $calls = [];

    public bool $fail = false;

    public ?string $emptyPage = null;

    /** Runs once while rendering, e.g. to observe production or start a competing Publish. */
    public ?Closure $onRender = null;

    public function render(array $payloads): array
    {
        $this->calls[] = $payloads;

        if ($this->onRender !== null) {
            $callback = $this->onRender;
            $this->onRender = null;
            $callback();
        }

        if ($this->fail) {
            throw new PageRenderException('Fake render failure.');
        }

        $html = [];

        foreach ($payloads as $payload) {
            $page = $payload['page']['public_id'];
            $parts = [];

            foreach ($payload['blocks'] as $block) {
                $parts[] = '<section>'.implode(' ', array_map(fn (string $text): string => e($text), self::strings((array) $block['state']))).'</section>';
            }

            foreach ($payload['vehicles'] as $vehicle) {
                foreach ($vehicle['offers'] as $offer) {
                    $parts[] = '<p>'.e($vehicle['title']).' '.e($offer['price_label']).'</p>';
                }
            }

            $html[$page] = $page === $this->emptyPage ? '' : '<main>'.implode('', $parts).'</main>';
        }

        return $html;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return list<string>
     */
    private static function strings(array $value): array
    {
        $strings = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $strings[] = $item;
            } elseif (is_array($item) || is_object($item)) {
                array_push($strings, ...self::strings((array) $item));
            }
        }

        return $strings;
    }
}
