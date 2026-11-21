import * as React from 'react';
import {
    applyBulkEdit,
    getBulkEditColumns,
    type CellEditorOption,
    type DataTableInstance,
    type DataTableRow,
    type TableMessages,
} from '@querycn/table-react';

import {
    addField,
    canApplyFields,
    getAvailableColumnIds,
    removeField,
    setFieldColumn,
    setFieldError,
    setFieldValue,
    toBulkChanges,
    type BulkEditField,
} from '@/components/data-table/bulk-edit-fields';

export interface UseBulkEditOptions<TData extends object> {
    table: DataTableInstance<TData>;
    /** The selected rows of the page. */
    rows: DataTableRow<TData>[];
    messages: Pick<TableMessages, 'editing'>;
}

/**
 * The bulk edit dialog's state. Opening it keeps the rows selected then, so
 * a refetch can't change what it applies to. A save closes it and clears the
 * selection; a failed one keeps it open with the error's message.
 */
export function useBulkEdit<TData extends object>({
    table,
    rows,
    messages,
}: UseBulkEditOptions<TData>) {
    const [open, setOpen] = React.useState(false);
    const [target, setTarget] = React.useState<DataTableRow<TData>[]>([]);
    const [fields, setFields] = React.useState<BulkEditField[]>([]);
    const [pending, setPending] = React.useState(false);
    const [error, setError] = React.useState<string>();
    const nextKey = React.useRef(0);
    // A second click on Apply before the re-render doesn't save twice.
    const busy = React.useRef(false);

    const columns = getBulkEditColumns(table, open ? target : rows);
    const columnIds = columns.map((column) => column.id);
    const editorType = (columnId: string) =>
        columns.find((column) => column.id === columnId)?.columnDef.meta?.edit
            ?.type ?? 'text';

    return {
        /** What a bulk edit can change in these rows; none hides the button. */
        columns,
        open,
        onOpenChange: (next: boolean) => {
            if (pending) return;
            if (next) {
                setTarget(rows);
                setFields([{ key: nextKey.current++ }]);
                setError(undefined);
            }
            setOpen(next);
        },
        rowCount: target.length,
        fields,
        addField: () =>
            setFields((current) => addField(current, nextKey.current++)),
        removeField: (key: number) =>
            setFields((current) => removeField(current, key)),
        setColumn: (key: number, columnId: string) =>
            setFields((current) =>
                setFieldColumn(current, key, columnId, editorType(columnId)),
            ),
        setValue: (key: number, value: unknown, option?: CellEditorOption) =>
            setFields((current) => setFieldValue(current, key, value, option)),
        setError: (key: number, message: string | undefined) =>
            setFields((current) => setFieldError(current, key, message)),
        availableColumns: (key: number) => {
            const ids = getAvailableColumnIds(columnIds, fields, key);
            return columns.filter((column) => ids.includes(column.id));
        },
        canApply: !pending && canApplyFields(fields),
        pending,
        error,
        apply: async () => {
            if (busy.current || !canApplyFields(fields)) return;
            busy.current = true;
            setPending(true);
            setError(undefined);
            const { changes, options } = toBulkChanges(fields);
            try {
                await applyBulkEdit(table, target, changes, options);
                setOpen(false);
                table.resetRowSelection(true);
            } catch (caught) {
                setError(
                    caught instanceof Error && caught.message
                        ? caught.message
                        : messages.editing.saveFailed,
                );
            } finally {
                busy.current = false;
                setPending(false);
            }
        },
    };
}

export type BulkEditState<TData extends object = object> = ReturnType<
    typeof useBulkEdit<TData>
>;
