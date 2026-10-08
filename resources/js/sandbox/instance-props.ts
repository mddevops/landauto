import type { SchemaField } from '@/blocks/schema';

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

/**
 * Template props for a placed Block: the Instance state restricted to the published schema.
 * Images become `{ url, alt }` through `imageUrl`; actions stay with the host (keys only) and
 * vehicle references are not exposed to Block code.
 */
export function instanceProps(
    fields: SchemaField[],
    state: unknown,
    imageUrl: (assetId: string) => string,
): Record<string, unknown> {
    const source = isRecord(state) ? state : {};
    const props: Record<string, unknown> = {};

    for (const field of fields) {
        const value = source[field.key];

        switch (field.type) {
            case 'text':
            case 'textarea':
            case 'select':
                if (typeof value === 'string') {
                    props[field.key] = value;
                }

                break;
            case 'number':
                if (typeof value === 'number') {
                    props[field.key] = value;
                }

                break;
            case 'boolean':
                props[field.key] = value === true;
                break;
            case 'image':
                if (typeof value === 'string' && value !== '') {
                    props[field.key] = { url: imageUrl(value), alt: '' };
                }

                break;
            case 'group':
                props[field.key] = instanceProps(
                    field.fields ?? [],
                    value,
                    imageUrl,
                );
                break;
            case 'repeater':
                props[field.key] = (Array.isArray(value) ? value : []).map(
                    (item) => instanceProps(field.fields ?? [], item, imageUrl),
                );
                break;
        }
    }

    return props;
}
