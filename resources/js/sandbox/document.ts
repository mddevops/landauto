import { SANDBOX_BOOTSTRAP } from '@/sandbox/bootstrap';

export type SandboxSources = { html: string; css: string; js: string };

export type SandboxOptions = {
    props: Record<string, unknown>;
    /** Keys of the schema `action` fields the Block may trigger. */
    actions: string[];
    /** The only origin the sandbox posts its messages to. */
    parentOrigin: string;
    /** Additional image origin, e.g. the Landflow asset origin on published Sites. */
    assetOrigin?: string;
};

export const SANDBOX_ATTRIBUTE = 'allow-scripts';

function contentSecurityPolicy(assetOrigin?: string): string {
    return [
        "default-src 'none'",
        "script-src 'unsafe-inline'",
        "style-src 'unsafe-inline'",
        `img-src data: blob:${assetOrigin ? ` ${assetOrigin}` : ''}`,
        'font-src data:',
        "connect-src 'none'",
        "media-src 'none'",
        "frame-src 'none'",
        "worker-src 'none'",
        "object-src 'none'",
        "form-action 'none'",
        "base-uri 'none'",
    ].join('; ');
}

/** Authored code may not close the element it is embedded in. */
function neutralize(source: string, element: 'style' | 'script'): string {
    const closed = source.replace(new RegExp(`</(${element})`, 'gi'), '<\\/$1');

    return element === 'script' ? closed.replace(/<!--/g, '<\\!--') : closed;
}

function escapeAttribute(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;');
}

/**
 * Builds the srcdoc of an opaque-origin sandbox (ADR-008 §2–§3). The Landflow CSP comes first;
 * the template and props travel as JSON with `<` escaped and are rendered by the bootstrap.
 */
export function buildSandboxDocument(
    sources: SandboxSources,
    options: SandboxOptions,
): string {
    const data = JSON.stringify({
        template: sources.html,
        props: options.props,
        actions: options.actions,
        parentOrigin: options.parentOrigin,
    })
        .replace(/</g, '\\u003c')
        .replace(/\u2028/g, '\\u2028')
        .replace(/\u2029/g, '\\u2029');

    return [
        '<!doctype html><html lang="ru"><head>',
        `<meta http-equiv="Content-Security-Policy" content="${escapeAttribute(contentSecurityPolicy(options.assetOrigin))}">`,
        '<meta charset="utf-8">',
        '<meta name="referrer" content="no-referrer">',
        '<meta name="viewport" content="width=device-width, initial-scale=1">',
        '<style>html,body{margin:0}#landflow-root{display:flow-root}</style>',
        `<style>${neutralize(sources.css, 'style')}</style>`,
        '</head><body><div id="landflow-root"></div>',
        `<script type="application/json" id="landflow-data">${data}</script>`,
        `<script>${SANDBOX_BOOTSTRAP}</script>`,
        `<script>${neutralize(sources.js, 'script')}</script>`,
        '</body></html>',
    ].join('');
}
