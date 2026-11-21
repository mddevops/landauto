'use client';

import * as React from 'react';
import { formatCellText, type TableMessages } from '@querycn/table-react';
import { XIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { BulkEditField } from '@/components/data-table/bulk-edit-fields';
import { getColumnLabel } from '@/components/data-table/column-label';
import type { BulkEditState } from '@/components/data-table/use-bulk-edit';
import {
    TextCellEditor,
    type CellEditors,
} from '@/components/data-table/data-table-cell-editors';

export interface DataTableBulkEditFieldProps<TData extends object> {
    bulk: BulkEditState<TData>;
    field: BulkEditField;
    editors: CellEditors;
    messages: TableMessages;
    /** Gets the column picker, to focus a field just added. */
    pickerRef?: React.Ref<HTMLButtonElement>;
}

/** One line of the bulk edit dialog: a column, its new value, remove. */
export function DataTableBulkEditField<TData extends object>({
    bulk,
    field,
    editors,
    messages,
    pickerRef,
}: DataTableBulkEditFieldProps<TData>) {
    const valueRef = React.useRef<HTMLDivElement>(null);
    const picked = React.useRef(false);
    const column = bulk.columns.find((c) => c.id === field.columnId);
    const label = column ? getColumnLabel(column) : undefined;

    return (
        <li className="flex flex-col gap-1.5">
            <div className="grid gap-2 sm:grid-cols-[10rem_1fr_auto] sm:items-center">
                <Select
                    value={field.columnId ?? ''}
                    onValueChange={(id) => {
                        picked.current = true;
                        bulk.setColumn(field.key, id);
                    }}
                    disabled={bulk.pending}
                >
                    <SelectTrigger
                        ref={pickerRef}
                        size="sm"
                        aria-label={messages.bulkEdit.column}
                        className="w-full"
                    >
                        <SelectValue
                            placeholder={messages.bulkEdit.chooseColumn}
                        />
                    </SelectTrigger>
                    <SelectContent
                        position="popper"
                        align="start"
                        onCloseAutoFocus={(event) => {
                            if (!picked.current) return;
                            picked.current = false;
                            // On to the value, rather than back to the picker.
                            event.preventDefault();
                            valueRef.current
                                ?.querySelector<HTMLElement>('input, button')
                                ?.focus();
                        }}
                    >
                        {bulk.availableColumns(field.key).map((option) => (
                            <SelectItem key={option.id} value={option.id}>
                                {getColumnLabel(option)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <div
                    ref={valueRef}
                    className="flex min-h-8 items-center [&>*]:my-0"
                >
                    {column && label ? (
                        <FieldValue
                            key={column.id}
                            bulk={bulk}
                            field={field}
                            column={column}
                            label={label}
                            editors={editors}
                            messages={messages}
                        />
                    ) : (
                        <span className="flex h-8 w-full items-center rounded-md border border-dashed px-2.5 text-sm text-muted-foreground">
                            {messages.bulkEdit.chooseValue}
                        </span>
                    )}
                </div>
                {bulk.fields.length > 1 && (
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label={messages.bulkEdit.removeField(
                            label ?? messages.bulkEdit.column,
                        )}
                        disabled={bulk.pending}
                        onClick={() => bulk.removeField(field.key)}
                        className="justify-self-end"
                    >
                        <XIcon />
                    </Button>
                )}
            </div>
            {field.error && (
                <p role="alert" className="text-xs text-destructive">
                    {field.error}
                </p>
            )}
        </li>
    );
}

/** The column's cell editor, saving into the field instead of a cell. */
function FieldValue<TData extends object>({
    bulk,
    field,
    column,
    label,
    editors,
    messages,
}: Omit<DataTableBulkEditFieldProps<TData>, 'pickerRef'> & {
    column: BulkEditState<TData>['columns'][number];
    label: string;
}) {
    const editor = column.columnDef.meta!.edit!;
    if (editor.type === 'boolean') {
        return (
            <Checkbox
                aria-label={label}
                checked={field.value === true}
                disabled={bulk.pending}
                onCheckedChange={(checked) =>
                    bulk.setValue(field.key, checked === true)
                }
            />
        );
    }
    const Editor = Object.hasOwn(editors, editor.type)
        ? editors[editor.type]!
        : TextCellEditor;
    return (
        <Editor
            value={field.value}
            editor={editor}
            label={label}
            display={
                field.option?.label ??
                (field.value === undefined
                    ? messages.bulkEdit.chooseValue
                    : formatCellText(field.value))
            }
            messages={messages}
            saving={bulk.pending}
            invalid={field.error !== undefined}
            onSave={(value, { option } = {}) =>
                bulk.setValue(field.key, value, option)
            }
            onCancel={() => bulk.setError(field.key, undefined)}
            onError={(message) => bulk.setError(field.key, message)}
        />
    );
}
