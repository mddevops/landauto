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
};

export type BlockSchema = {
    fields: SchemaField[];
};
