import type { CellEditorOption } from '@querycn/table-react';

/** One line of the bulk edit dialog: a column and its new value. */
export interface BulkEditField {
    key: number;
    columnId?: string;
    value?: unknown;
    /** The picked option of a `select`, its label too. */
    option?: CellEditorOption;
    error?: string;
}

const update = (
    fields: readonly BulkEditField[],
    key: number,
    change: (field: BulkEditField) => BulkEditField,
) => fields.map((field) => (field.key === key ? change(field) : field));

export const addField = (fields: readonly BulkEditField[], key: number) => [
    ...fields,
    { key },
];

export const removeField = (fields: readonly BulkEditField[], key: number) =>
    fields.filter((field) => field.key !== key);

/** Picks the field's column; its value starts over, `false` for a checkbox. */
export const setFieldColumn = (
    fields: readonly BulkEditField[],
    key: number,
    columnId: string,
    editorType: string,
) =>
    update(fields, key, () => ({
        key,
        columnId,
        value: editorType === 'boolean' ? false : undefined,
    }));

export const setFieldValue = (
    fields: readonly BulkEditField[],
    key: number,
    value: unknown,
    option?: CellEditorOption,
) =>
    update(fields, key, (field) => ({
        ...field,
        value,
        option,
        error: undefined,
    }));

export const setFieldError = (
    fields: readonly BulkEditField[],
    key: number,
    error: string | undefined,
) => update(fields, key, (field) => ({ ...field, error }));

/** The columns a field can pick: its own and those no other field has. */
export function getAvailableColumnIds(
    columnIds: readonly string[],
    fields: readonly BulkEditField[],
    key: number,
) {
    const taken = new Set(
        fields
            .filter((field) => field.key !== key)
            .map((field) => field.columnId),
    );
    return columnIds.filter((id) => !taken.has(id));
}

/** Every field has a column and a value, and none has an error. */
export const canApplyFields = (fields: readonly BulkEditField[]) =>
    fields.length > 0 &&
    fields.every(
        (field) =>
            field.columnId !== undefined &&
            field.value !== undefined &&
            field.error === undefined,
    );

/** `onBulkEdit`'s `changes` and `options`, by column id. */
export function toBulkChanges(fields: readonly BulkEditField[]) {
    // No prototype: a column id like `constructor` stays a plain key.
    const changes: Record<string, unknown> = Object.create(null);
    const options: Record<string, CellEditorOption> = Object.create(null);
    for (const field of fields) {
        if (field.columnId === undefined) continue;
        changes[field.columnId] = field.value;
        if (field.option) options[field.columnId] = field.option;
    }
    return { changes, options };
}
