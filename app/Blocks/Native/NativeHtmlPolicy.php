<?php

namespace App\Blocks\Native;

/**
 * Default-deny element, attribute and URL policy of Native Block markup (ADR-009). Only listed
 * elements and attributes survive compilation; everything else fails the compile with a reason.
 */
final class NativeHtmlPolicy
{
    /** Named explicitly in errors: they execute code, load documents or submit data. */
    public const FORBIDDEN_ELEMENTS = [
        'script', 'style', 'link', 'meta', 'base', 'iframe', 'frame', 'frameset', 'object', 'embed',
        'form', 'portal', 'applet', 'noscript', 'template', 'foreignobject', 'animate', 'set',
        'animatemotion', 'animatetransform', 'image', 'math', 'input', 'textarea', 'select', 'option',
    ];

    private const HTML_ELEMENTS = [
        'a', 'abbr', 'address', 'article', 'aside', 'b', 'bdi', 'bdo', 'blockquote', 'br', 'button',
        'caption', 'cite', 'code', 'col', 'colgroup', 'data', 'dd', 'del', 'details', 'dfn', 'div',
        'dl', 'dt', 'em', 'figcaption', 'figure', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'header', 'hgroup', 'hr', 'i', 'img', 'ins', 'kbd', 'li', 'mark', 'nav', 'ol', 'p', 'pre',
        'q', 'rp', 'rt', 'ruby', 's', 'samp', 'section', 'small', 'span', 'strong', 'sub', 'summary',
        'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'time', 'tr', 'u', 'ul', 'var', 'wbr',
    ];

    /** The HTML parser lowercases SVG names; browsers restore camelCase on parse (HTML §13.2.6.5). */
    private const SVG_ELEMENTS = [
        'svg', 'g', 'path', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'rect', 'defs',
        'lineargradient', 'radialgradient', 'stop', 'use', 'symbol', 'desc', 'clippath', 'mask',
        'pattern', 'text', 'tspan',
    ];

    public const VOID_ELEMENTS = ['br', 'col', 'hr', 'img', 'wbr'];

    private const GLOBAL_ATTRIBUTES = ['class', 'id', 'title', 'lang', 'dir', 'hidden', 'tabindex', 'role', 'translate'];

    /** @var array<string, list<string>> */
    private const ELEMENT_ATTRIBUTES = [
        'a' => ['href', 'target', 'rel', 'hreflang', 'type'],
        'img' => ['src', 'alt', 'width', 'height', 'loading', 'decoding'],
        'button' => ['type', 'disabled'],
        'ol' => ['start', 'reversed', 'type'],
        'li' => ['value'],
        'td' => ['colspan', 'rowspan', 'headers'],
        'th' => ['colspan', 'rowspan', 'headers', 'scope', 'abbr'],
        'col' => ['span'],
        'colgroup' => ['span'],
        'time' => ['datetime'],
        'data' => ['value'],
        'details' => ['open'],
        'blockquote' => ['cite'],
        'q' => ['cite'],
        'del' => ['cite', 'datetime'],
        'ins' => ['cite', 'datetime'],
    ];

    private const SVG_ATTRIBUTES = [
        'viewbox', 'xmlns', 'preserveaspectratio', 'width', 'height', 'x', 'y', 'x1', 'x2', 'y1', 'y2',
        'cx', 'cy', 'r', 'rx', 'ry', 'fx', 'fy', 'd', 'points', 'fill', 'fill-opacity', 'fill-rule',
        'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray',
        'stroke-dashoffset', 'stroke-opacity', 'stroke-miterlimit', 'opacity', 'transform',
        'clip-path', 'clip-rule', 'mask', 'offset', 'stop-color', 'stop-opacity', 'gradientunits',
        'gradienttransform', 'patternunits', 'patterncontentunits', 'patterntransform', 'spreadmethod',
        'maskunits', 'maskcontentunits', 'clippathunits', 'font-size', 'font-family', 'font-weight',
        'text-anchor', 'dominant-baseline', 'dx', 'dy', 'rotate', 'textlength', 'lengthadjust',
        'letter-spacing', 'focusable', 'vector-effect', 'color', 'display', 'visibility', 'overflow',
        'shape-rendering', 'href',
    ];

    /** Enumerated attributes whose rendered value must be one of the listed values. */
    private const ENUMERATED = [
        'target' => ['_blank', '_self'],
        'loading' => ['lazy', 'eager'],
        'decoding' => ['async', 'sync', 'auto'],
    ];

    private const SVG_NAMESPACES = ['http://www.w3.org/2000/svg', 'http://www.w3.org/1999/xlink'];

    public const ACTION_ATTRIBUTE = 'data-landflow-action';

    public static function isSvg(string $element): bool
    {
        return in_array($element, self::SVG_ELEMENTS, true);
    }

    /**
     * Null when the element is allowed, otherwise the reason.
     */
    public static function elementError(string $element): ?string
    {
        return match (true) {
            in_array($element, self::HTML_ELEMENTS, true), self::isSvg($element) => null,
            $element === 'script' => 'Тег <script> запрещён в шаблоне: код блока пишется в script.js.',
            $element === 'style' => 'Тег <style> запрещён в шаблоне: стили блока пишутся в styles.css.',
            $element === 'form', in_array($element, ['input', 'textarea', 'select', 'option'], true) => "Тег <{$element}> запрещён в нативном блоке: заявки собираются формами Landflow через действие «Открыть попап».",
            in_array($element, self::FORBIDDEN_ELEMENTS, true) => "Тег <{$element}> запрещён в нативном блоке.",
            default => "Тег <{$element}> не поддерживается в нативном блоке.",
        };
    }

    /**
     * Null when the attribute name is allowed on the element, otherwise the reason.
     */
    public static function attributeError(string $element, string $attribute): ?string
    {
        if (preg_match('/^[a-z][a-z0-9_.:-]*$/', $attribute) !== 1) {
            return "Недопустимое имя атрибута «{$attribute}».";
        }

        if (str_starts_with($attribute, 'on')) {
            return "Атрибут {$attribute} запрещён: обработчики событий не допускаются в нативной разметке.";
        }

        if ($attribute === 'style') {
            return 'Атрибут style запрещён: оформление блока задаётся в styles.css.';
        }

        if ($attribute === self::ACTION_ATTRIBUTE) {
            return null;
        }

        if (str_starts_with($attribute, 'data-landflow')) {
            return "Атрибут {$attribute} зарезервирован Landflow.";
        }

        if (preg_match('/^(?:aria|data)-[a-z0-9_.-]+$/', $attribute) === 1 || in_array($attribute, self::GLOBAL_ATTRIBUTES, true)) {
            return null;
        }

        $allowed = self::isSvg($element) ? self::SVG_ATTRIBUTES : (self::ELEMENT_ATTRIBUTES[$element] ?? []);

        return in_array($attribute, $allowed, true) ? null : "Атрибут «{$attribute}» не поддерживается у тега <{$element}> в нативном блоке.";
    }

    /**
     * Checks the final, decoded attribute value. Null when safe, otherwise the reason.
     */
    public static function valueError(string $element, string $attribute, string $value): ?string
    {
        if (isset(self::ENUMERATED[$attribute]) && ! in_array(strtolower(trim($value)), self::ENUMERATED[$attribute], true)) {
            return "Недопустимое значение атрибута {$attribute}.";
        }

        return match (true) {
            $element === 'button' && $attribute === 'type' => strtolower(trim($value)) === 'button' ? null : 'У кнопки допустим только type="button".',
            $attribute === 'id' => self::idError($value),
            $attribute === 'xmlns' => in_array($value, self::SVG_NAMESPACES, true) ? null : 'Атрибут xmlns допускает только пространство имён SVG.',
            $attribute === 'href' && self::isSvg($element) => preg_match('/^#[A-Za-z][\w.-]*$/', $value) === 1 ? null : 'В SVG <use href> допустима только ссылка на фрагмент вида #id.',
            in_array($attribute, ['href', 'cite'], true) => self::navigationError($value),
            self::isSvg($element) && ! self::isTextAttribute($attribute) => self::presentationError($value),
            default => null,
        };
    }

    /**
     * Navigation URLs: same-site relative paths, fragments, queries, http(s), mailto and tel.
     */
    public static function navigationError(string $value): ?string
    {
        // Browsers drop tab/newline anywhere in a URL and trim C0 controls and spaces.
        $url = strtolower((string) preg_replace('/[\x00-\x20\x7F]+/', '', $value));

        if (str_contains($url, '\\') || str_starts_with($url, '//')) {
            return 'Небезопасная ссылка: адреса вида //… и с обратной косой чертой запрещены.';
        }

        if (preg_match('/^([a-z][a-z0-9+.-]*):/', $url, $scheme) === 1 && ! in_array($scheme[1], ['http', 'https', 'mailto', 'tel'], true)) {
            return "Небезопасная ссылка: схема «{$scheme[1]}:» запрещена.";
        }

        return null;
    }

    private static function idError(string $value): ?string
    {
        if (preg_match('/^[A-Za-z][\w-]*$/', $value) !== 1) {
            return 'Атрибут id должен начинаться с латинской буквы и содержать только буквы, цифры, «-» и «_».';
        }

        return preg_match('/^(?:lf-|landflow|block-)/i', $value) === 1 ? 'Атрибут id не может начинаться с «lf-», «landflow» или «block-»: эти имена зарезервированы Landflow.' : null;
    }

    /** SVG presentation values are CSS: only same-document `url(#id)` references are allowed. */
    private static function presentationError(string $value): ?string
    {
        if (str_contains($value, '\\')) {
            return 'Обратная косая черта запрещена в атрибутах SVG.';
        }

        if (stripos($value, 'url(') !== false && preg_match('/^\s*url\(\s*#[A-Za-z][\w.-]*\s*\)\s*$/i', $value) !== 1) {
            return 'В атрибутах SVG допустима только ссылка вида url(#id).';
        }

        return null;
    }

    private static function isTextAttribute(string $attribute): bool
    {
        return in_array($attribute, self::GLOBAL_ATTRIBUTES, true) || preg_match('/^(?:aria|data)-/', $attribute) === 1;
    }
}
