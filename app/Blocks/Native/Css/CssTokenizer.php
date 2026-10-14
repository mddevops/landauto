<?php

namespace App\Blocks\Native\Css;

use App\Blocks\Native\NativeCompileException;

/**
 * CSS Syntax Module Level 3 tokenizer (§4) for Native Block stylesheets. Raw token text is kept
 * so the compiler re-emits author CSS verbatim apart from the parts it rewrites. Unterminated
 * comments and strings fail closed instead of being repaired.
 */
final class CssTokenizer
{
    private string $css = '';

    private int $pos = 0;

    private int $length = 0;

    /**
     * @return list<CssToken>
     */
    public function tokenize(string $css): array
    {
        $this->css = str_replace(["\r\n", "\r", "\f", "\0"], ["\n", "\n", "\n", "\u{FFFD}"], $css);
        $this->pos = 0;
        $this->length = strlen($this->css);
        $tokens = [];

        while ($this->pos < $this->length) {
            $tokens[] = $this->next();
        }

        return $tokens;
    }

    public function line(int $offset): int
    {
        return substr_count($this->css, "\n", 0, min($offset, $this->length)) + 1;
    }

    private function next(): CssToken
    {
        $start = $this->pos;
        $char = $this->css[$this->pos];

        if ($char === '/' && $this->at(1) === '*') {
            $end = strpos($this->css, '*/', $this->pos + 2);

            if ($end === false) {
                throw NativeCompileException::css('Комментарий в styles.css не закрыт: добавьте */.', $this->line($start));
            }

            $this->pos = $end + 2;

            return new CssToken(CssToken::COMMENT, '/**/', '', $start);
        }

        if (self::isWhitespace($char)) {
            while ($this->pos < $this->length && self::isWhitespace($this->css[$this->pos])) {
                $this->pos++;
            }

            return new CssToken(CssToken::WHITESPACE, ' ', '', $start);
        }

        switch ($char) {
            case '"':
            case "'":
                return $this->string($char);
            case '#':
                if (self::isName($this->at(1)) || $this->isEscape($this->pos + 1)) {
                    $this->pos++;
                    $name = $this->name();

                    return $this->token(CssToken::HASH, $start, $name);
                }

                return $this->delim($start);
            case '(':
            case ')':
            case '[':
            case ']':
            case '{':
            case '}':
            case ',':
            case ':':
            case ';':
                $this->pos++;

                return $this->token($char, $start, $char);
            case '+':
            case '.':
                return $this->startsNumber($this->pos) ? $this->numeric($start) : $this->delim($start);
            case '-':
                if ($this->startsNumber($this->pos)) {
                    return $this->numeric($start);
                }

                if ($this->at(1) === '-' && $this->at(2) === '>') {
                    $this->pos += 3;

                    return $this->token(CssToken::CDC, $start, '-->');
                }

                return $this->startsIdent($this->pos) ? $this->identLike($start) : $this->delim($start);
            case '<':
                if (substr($this->css, $this->pos, 4) === '<!--') {
                    $this->pos += 4;

                    return $this->token(CssToken::CDO, $start, '<!--');
                }

                return $this->delim($start);
            case '@':
                if ($this->startsIdent($this->pos + 1)) {
                    $this->pos++;
                    $name = $this->name();

                    return $this->token(CssToken::AT_KEYWORD, $start, $name);
                }

                return $this->delim($start);
            case '\\':
                if (! $this->isEscape($this->pos)) {
                    throw NativeCompileException::css('Некорректная escape-последовательность в styles.css.', $this->line($start));
                }

                return $this->identLike($start);
        }

        if (ctype_digit($char)) {
            return $this->numeric($start);
        }

        return self::isNameStart($char) ? $this->identLike($start) : $this->delim($start);
    }

    private function token(string $type, int $start, string $value): CssToken
    {
        return new CssToken($type, substr($this->css, $start, $this->pos - $start), $value, $start);
    }

    private function delim(int $start): CssToken
    {
        $this->pos += self::charLength($this->css[$this->pos]);
        $raw = substr($this->css, $start, $this->pos - $start);

        return new CssToken(CssToken::DELIM, $raw, $raw, $start);
    }

    private function string(string $quote): CssToken
    {
        $start = $this->pos++;
        $value = '';

        while (true) {
            if ($this->pos >= $this->length) {
                throw NativeCompileException::css('Строка в styles.css не закрыта кавычкой.', $this->line($start));
            }

            $char = $this->css[$this->pos];

            if ($char === $quote) {
                $this->pos++;
                break;
            }

            if ($char === "\n") {
                throw NativeCompileException::css('Строка в styles.css содержит перевод строки: закройте кавычку.', $this->line($start));
            }

            if ($char === '\\') {
                if ($this->pos + 1 >= $this->length) {
                    $this->pos++;
                } elseif ($this->css[$this->pos + 1] === "\n") {
                    $this->pos += 2;
                } else {
                    $value .= $this->escape();
                }

                continue;
            }

            $value .= $this->char();
        }

        return $this->token(CssToken::STRING, $start, $value);
    }

    private function numeric(int $start): CssToken
    {
        if ($this->css[$this->pos] === '+' || $this->css[$this->pos] === '-') {
            $this->pos++;
        }

        $this->digits();

        if ($this->at(0) === '.' && ctype_digit($this->at(1))) {
            $this->pos++;
            $this->digits();
        }

        $exponent = strtolower($this->at(0));

        if ($exponent === 'e' && (ctype_digit($this->at(1)) || (in_array($this->at(1), ['+', '-'], true) && ctype_digit($this->at(2))))) {
            $this->pos += ctype_digit($this->at(1)) ? 1 : 2;
            $this->digits();
        }

        if ($this->startsIdent($this->pos)) {
            $unit = $this->name();

            return $this->token(CssToken::DIMENSION, $start, $unit);
        }

        if ($this->at(0) === '%') {
            $this->pos++;

            return $this->token(CssToken::PERCENTAGE, $start, '%');
        }

        return $this->token(CssToken::NUMBER, $start, '');
    }

    private function identLike(int $start): CssToken
    {
        $name = $this->name();

        if ($this->at(0) !== '(') {
            return $this->token(CssToken::IDENT, $start, $name);
        }

        $this->pos++;

        if (strtolower($name) === 'url') {
            $next = $this->pos;

            while ($next < $this->length && self::isWhitespace($this->css[$next])) {
                $next++;
            }

            $quote = $this->css[$next] ?? '';

            if ($quote !== '"' && $quote !== "'") {
                $end = strpos($this->css, ')', $this->pos);
                $this->pos = $end === false ? $this->length : $end + 1;

                return $this->token(CssToken::URL, $start, 'url');
            }
        }

        return $this->token(CssToken::FUNCTION, $start, $name);
    }

    private function name(): string
    {
        $value = '';

        while ($this->pos < $this->length) {
            $char = $this->css[$this->pos];

            if (self::isName($char)) {
                $value .= $this->char();
            } elseif ($this->isEscape($this->pos)) {
                $value .= $this->escape();
            } else {
                break;
            }
        }

        return $value;
    }

    /** Consumes `\` and the escaped code point (§4.3.7); the caller checked it is a valid escape. */
    private function escape(): string
    {
        $this->pos++;
        $hex = '';

        while (strlen($hex) < 6 && $this->pos < $this->length && ctype_xdigit($this->css[$this->pos])) {
            $hex .= $this->css[$this->pos++];
        }

        if ($hex === '') {
            return $this->char();
        }

        if ($this->pos < $this->length && self::isWhitespace($this->css[$this->pos])) {
            $this->pos++;
        }

        $code = (int) hexdec($hex);

        if ($code === 0 || ($code >= 0xD800 && $code <= 0xDFFF) || $code > 0x10FFFF) {
            return "\u{FFFD}";
        }

        return (string) mb_chr($code, 'UTF-8');
    }

    private function char(): string
    {
        $length = self::charLength($this->css[$this->pos]);
        $char = substr($this->css, $this->pos, $length);
        $this->pos += $length;

        return $char;
    }

    private function digits(): void
    {
        while ($this->pos < $this->length && ctype_digit($this->css[$this->pos])) {
            $this->pos++;
        }
    }

    private function at(int $offset): string
    {
        return $this->css[$this->pos + $offset] ?? '';
    }

    private function isEscape(int $position): bool
    {
        return ($this->css[$position] ?? '') === '\\' && $position + 1 < $this->length && $this->css[$position + 1] !== "\n";
    }

    private function startsIdent(int $position): bool
    {
        $first = $this->css[$position] ?? '';

        if ($first === '-') {
            $second = $this->css[$position + 1] ?? '';

            return self::isNameStart($second) || $second === '-' || $this->isEscape($position + 1);
        }

        return self::isNameStart($first) || $this->isEscape($position);
    }

    private function startsNumber(int $position): bool
    {
        $first = $this->css[$position] ?? '';
        $second = $this->css[$position + 1] ?? '';

        return match ($first) {
            '+', '-' => ctype_digit($second) || ($second === '.' && ctype_digit($this->css[$position + 2] ?? '')),
            '.' => ctype_digit($second),
            default => ctype_digit($first),
        };
    }

    private static function isWhitespace(string $char): bool
    {
        return $char === ' ' || $char === "\t" || $char === "\n";
    }

    private static function isNameStart(string $char): bool
    {
        return $char !== '' && (ctype_alpha($char) || $char === '_' || ord($char) >= 0x80);
    }

    private static function isName(string $char): bool
    {
        return self::isNameStart($char) || ($char !== '' && (ctype_digit($char) || $char === '-'));
    }

    private static function charLength(string $lead): int
    {
        $byte = ord($lead);

        return match (true) {
            $byte < 0x80 => 1,
            $byte < 0xE0 => 2,
            $byte < 0xF0 => 3,
            default => 4,
        };
    }
}
