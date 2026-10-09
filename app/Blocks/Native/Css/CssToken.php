<?php

namespace App\Blocks\Native\Css;

/**
 * A CSS Syntax Level 3 token. `raw` is the exact source text (re-emitted verbatim), `value` the
 * unescaped name of idents, functions, at-keywords, hashes, strings and url tokens.
 */
final readonly class CssToken
{
    public const WHITESPACE = 'whitespace';

    public const COMMENT = 'comment';

    public const IDENT = 'ident';

    public const FUNCTION = 'function';

    public const AT_KEYWORD = 'at-keyword';

    public const HASH = 'hash';

    public const STRING = 'string';

    public const URL = 'url';

    public const BAD_URL = 'bad-url';

    public const DELIM = 'delim';

    public const NUMBER = 'number';

    public const PERCENTAGE = 'percentage';

    public const DIMENSION = 'dimension';

    public const CDO = 'cdo';

    public const CDC = 'cdc';

    public const COLON = ':';

    public const SEMICOLON = ';';

    public const COMMA = ',';

    public const OPEN_SQUARE = '[';

    public const CLOSE_SQUARE = ']';

    public const OPEN_PAREN = '(';

    public const CLOSE_PAREN = ')';

    public const OPEN_CURLY = '{';

    public const CLOSE_CURLY = '}';

    public function __construct(
        public string $type,
        public string $raw,
        public string $value,
        public int $offset,
    ) {}

    public function is(string $type, ?string $value = null): bool
    {
        return $this->type === $type && ($value === null || strtolower($this->value) === $value);
    }

    public function isTrivia(): bool
    {
        return $this->type === self::WHITESPACE || $this->type === self::COMMENT;
    }

    public function withRaw(string $raw): self
    {
        return new self($this->type, $raw, $this->value, $this->offset);
    }
}
