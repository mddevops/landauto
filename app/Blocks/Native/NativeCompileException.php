<?php

namespace App\Blocks\Native;

use RuntimeException;

/**
 * A Native Block source or Instance state the compiler refuses (ADR-009). The message is safe,
 * Russian and shown to the author or publisher; it never contains rendered output.
 */
final class NativeCompileException extends RuntimeException
{
    /**
     * @param  'html'|'css'|'state'  $source
     */
    public function __construct(string $message, public readonly string $source, public readonly ?int $sourceLine = null)
    {
        parent::__construct($message);
    }

    public static function html(string $message, ?int $line = null): self
    {
        return new self($message, 'html', $line);
    }

    public static function css(string $message, ?int $line = null): self
    {
        return new self($message, 'css', $line);
    }

    public static function state(string $message): self
    {
        return new self($message, 'state');
    }
}
