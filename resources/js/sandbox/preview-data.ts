import type { SchemaField } from '@/blocks/schema';

export type PreviewData = Record<string, unknown>;

/** Neutral placeholder shown for every image in the Studio preview. */
export const PLACEHOLDER_IMAGE =
    'data:image/svg+xml,' +
    encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360"><rect width="640" height="360" fill="#e5e7eb"/><path d="M250 230l50-60 40 45 30-30 60 75H210z" fill="#9ca3af"/><circle cx="260" cy="130" r="22" fill="#9ca3af"/></svg>',
    );

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

/**
 * Synthetic Studio preview data: saved values where they still fit the schema, schema defaults
 * (or neutral samples) otherwise. Actions are not data; images keep only their caption.
 */
export function resolvePreviewData(
    fields: SchemaField[],
    saved: unknown,
): PreviewData {
    const source = isRecord(saved) ? saved : {};
    const data: PreviewData = {};

    for (const field of fields) {
        const value = resolveValue(field, source[field.key]);

        if (value !== undefined) {
            data[field.key] = value;
        }
    }

    return data;
}

function resolveValue(field: SchemaField, saved: unknown): unknown {
    switch (field.type) {
        case 'text':
        case 'textarea':
            return typeof saved === 'string'
                ? saved
                : typeof field.default === 'string'
                  ? field.default
                  : field.label;
        case 'number':
            return typeof saved === 'number'
                ? saved
                : typeof field.default === 'number'
                  ? field.default
                  : (field.min ?? 0);
        case 'boolean':
            return typeof saved === 'boolean' ? saved : field.default === true;
        case 'select': {
            const values = (field.options ?? []).map((option) => option.value);

            if (typeof saved === 'string' && values.includes(saved)) {
                return saved;
            }

            return typeof field.default === 'string'
                ? field.default
                : values[0];
        }
        case 'image':
            return {
                alt:
                    isRecord(saved) && typeof saved.alt === 'string'
                        ? saved.alt
                        : field.label,
            };
        case 'group':
            return resolvePreviewData(field.fields ?? [], saved);
        case 'repeater': {
            const max = field.max_items ?? 6;
            const items = Array.isArray(saved)
                ? saved.slice(0, max)
                : Array.from({
                      length: Math.min(max, Math.max(field.min_items ?? 0, 2)),
                  });

            return items.map((item) =>
                resolvePreviewData(field.fields ?? [], item),
            );
        }
        default:
            return undefined;
    }
}

/** Template props: preview data with image URLs replaced by the placeholder. */
export function previewProps(
    fields: SchemaField[],
    data: PreviewData,
): PreviewData {
    const props: PreviewData = {};

    for (const field of fields) {
        const value = data[field.key];

        if (field.type === 'image') {
            props[field.key] = {
                url: PLACEHOLDER_IMAGE,
                alt:
                    isRecord(value) && typeof value.alt === 'string'
                        ? value.alt
                        : '',
            };
        } else if (field.type === 'group') {
            props[field.key] = previewProps(
                field.fields ?? [],
                isRecord(value) ? value : {},
            );
        } else if (field.type === 'repeater') {
            props[field.key] = (Array.isArray(value) ? value : []).map((item) =>
                previewProps(field.fields ?? [], isRecord(item) ? item : {}),
            );
        } else if (value !== undefined) {
            props[field.key] = value;
        }
    }

    return props;
}

export function actionKeys(fields: SchemaField[]): string[] {
    return fields
        .filter((field) => field.type === 'action')
        .map((field) => field.key);
}
