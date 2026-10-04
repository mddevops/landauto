export type SchemaFieldType =
    | 'text'
    | 'textarea'
    | 'boolean'
    | 'select'
    | 'image'
    | 'group'
    | 'repeater';

export type SchemaField = {
    key: string;
    type: SchemaFieldType;
    label: string;
    help?: string;
    required?: boolean;
    max_length?: number;
    default?: string | boolean;
    options?: { value: string; label: string }[];
    fields?: SchemaField[];
    min_items?: number;
    max_items?: number;
    visible_if?: { field: string; equals: string | boolean };
};

export type BlockSchema = {
    fields: SchemaField[];
};

export function isFieldVisible(
    field: SchemaField,
    siblings: Record<string, unknown>,
): boolean {
    if (!field.visible_if) {
        return true;
    }

    const controller = field.visible_if.field;
    const value = Object.hasOwn(siblings, controller)
        ? siblings[controller]
        : null;

    return value === field.visible_if.equals;
}
