'use client';

import type * as React from 'react';
import type { DataTableInstance, DataTableRow } from '@querycn/table-react';

import { cn } from 'cn';
import { getColumnLabel } from '@/components/data-table/column-label';
import {
    isFromControl,
    isRowClick,
} from '@/components/data-table/data-table-row-clicks';

type Cell<TData extends object> = ReturnType<
    DataTableRow<TData>['getAllCells']
>[number];

export interface DataTableCardsProps<TData extends object> {
    table: DataTableInstance<TData>;
    onRowClick?: (row: DataTableRow<TData>, event: React.MouseEvent) => void;
    onRowDoubleClick?: (
        row: DataTableRow<TData>,
        event: React.MouseEvent,
    ) => void;
    rowClassName?: (row: DataTableRow<TData>) => string | undefined;
    /** Dims the cards while they reload. */
    isLoading?: boolean;
    className?: string;
}

/**
 * The page's rows as cards, for narrow containers. Each shows its visible
 * columns: the first one as the title, those pinned to the end beside it, the
 * rest as labelled fields. Read-only; edit in the table.
 */
export function DataTableCards<TData extends object>({
    table,
    className,
    isLoading,
    ...props
}: DataTableCardsProps<TData>) {
    return (
        <ul
            data-slot="data-table-cards"
            className={cn(
                'flex flex-col gap-2 bg-muted/40 p-2 transition-opacity',
                isLoading && 'pointer-events-none opacity-60',
                className,
            )}
        >
            {table.getRowModel().rows.map((row) => (
                <DataTableCard
                    key={row.id}
                    table={table}
                    row={row}
                    {...props}
                />
            ))}
        </ul>
    );
}

function DataTableCard<TData extends object>({
    table,
    row,
    onRowClick,
    onRowDoubleClick,
    rowClassName,
}: Omit<DataTableCardsProps<TData>, 'className' | 'isLoading'> & {
    row: DataTableRow<TData>;
}) {
    const end = row.getEndVisibleCells();
    const cells = [
        ...row.getStartVisibleCells(),
        ...row.getCenterVisibleCells(),
    ];
    // `createSelectionColumn`'s checkbox goes before the title.
    const select = cells.find((cell) => cell.column.id === 'select');
    const [title, ...fields] = cells.filter((cell) => cell !== select);
    const render = (cell: Cell<TData>) => <table.FlexRender cell={cell} />;

    return (
        <li
            data-slot="data-table-card"
            data-state={row.getIsSelected() ? 'selected' : undefined}
            onClick={
                onRowClick &&
                ((event) => {
                    if (isRowClick(event)) onRowClick(row, event);
                })
            }
            onDoubleClick={
                onRowDoubleClick &&
                ((event) => {
                    if (!isFromControl(event)) onRowDoubleClick(row, event);
                })
            }
            className={cn(
                'rounded-md border bg-background p-3 text-sm shadow-xs data-[state=selected]:border-primary/40 data-[state=selected]:bg-muted',
                onRowClick && 'cursor-pointer',
                rowClassName?.(row),
            )}
        >
            <div className="flex min-h-5 items-center gap-3">
                {select && (
                    <div className="flex shrink-0">{render(select)}</div>
                )}
                <div className="min-w-0 flex-1 truncate font-medium">
                    {title && render(title)}
                </div>
                {end.map((cell) => (
                    <div key={cell.id} className="shrink-0">
                        {render(cell)}
                    </div>
                ))}
            </div>
            {fields.length > 0 && (
                <dl
                    className={cn(
                        'mt-3 grid grid-cols-2 gap-x-4 gap-y-2.5',
                        // Lines up with the title, past the checkbox.
                        select && 'ps-7',
                    )}
                >
                    {fields.map((cell) => (
                        <div key={cell.id} className="min-w-0">
                            <dt className="truncate text-xs text-muted-foreground">
                                {getColumnLabel(cell.column)}
                            </dt>
                            <dd className="mt-0.5 break-words">
                                {render(cell)}
                            </dd>
                        </div>
                    ))}
                </dl>
            )}
        </li>
    );
}
