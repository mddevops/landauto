'use client';

import * as React from 'react';
import {
    enTableMessages,
    type DataTableInstance,
    type DataTableRow,
    type TableMessages,
} from '@querycn/table-react';
import { RefreshCwIcon } from 'lucide-react';

import { cn } from 'cn';
import { Button } from '@/components/ui/button';
import { DataTableResetFiltersButton } from '@/components/data-table/data-table-reset-filters-button';
import { DataTableSelectionBar } from '@/components/data-table/data-table-selection-bar';
import { DataTableViewOptions } from '@/components/data-table/data-table-view-options';
import type { CellEditors } from '@/components/data-table/data-table-cell-editors';

export interface DataTableToolbarProps<TData extends object> extends Omit<
    React.ComponentProps<'div'>,
    'children'
> {
    table: DataTableInstance<TData>;
    messages?: TableMessages;
    /** The app's own controls: a search box, `FilterBuilder`, `FilterChips`… */
    children?: React.ReactNode;
    /** Shows a reload button. */
    onRefresh?: () => void;
    isRefreshing?: boolean;
    /** Actions for the selected rows, in a bar floating over the table while some are. */
    selectionActions?: (rows: DataTableRow<TData>[]) => React.ReactNode;
    /** Editors by `meta.edit.type` for Bulk edit; pass the ones `DataTable` has. */
    cellEditors?: CellEditors;
}

/**
 * Above a `DataTable`: the app's controls, then clear filters, reload and the
 * column menu. While rows are selected, the selection bar floats over the
 * table with `selectionActions` and, for editable columns, Bulk edit.
 */
export function DataTableToolbar<TData extends object>({
    table,
    messages = enTableMessages,
    children,
    onRefresh,
    isRefreshing = false,
    selectionActions,
    cellEditors,
    className,
    ...props
}: DataTableToolbarProps<TData>) {
    return (
        <div
            data-slot="data-table-toolbar"
            // Takes focus from a control that goes away, like clear filters.
            tabIndex={-1}
            className={cn('flex flex-col gap-2 outline-none', className)}
            {...props}
        >
            {/* Only around the controls: a query container is the containing block
          of fixed descendants, like the selection bar. */}
            <div className="@container/data-table-toolbar">
                <div className="flex flex-wrap items-start gap-2">
                    {/* Narrower than `@xl`, its controls join the row: the search on a
              line of its own, the filter beside the actions, then the chips. */}
                    <div className="contents @xl/data-table-toolbar:flex @xl/data-table-toolbar:min-w-0 @xl/data-table-toolbar:flex-1 @xl/data-table-toolbar:flex-wrap @xl/data-table-toolbar:items-center @xl/data-table-toolbar:gap-2 @max-xl/data-table-toolbar:[&>[data-slot=data-table-search]]:w-full @max-xl/data-table-toolbar:[&>[data-slot=filter-chips]]:order-last @max-xl/data-table-toolbar:[&>[data-slot=filter-chips]]:basis-full">
                        {children}
                    </div>
                    <div className="ms-auto flex items-center gap-2">
                        <DataTableResetFiltersButton
                            table={table}
                            messages={messages}
                        />
                        {onRefresh && (
                            <Button
                                variant="outline"
                                size="icon-sm"
                                aria-label={messages.actions.reload}
                                // Pending, not disabled: it keeps focus.
                                aria-disabled={isRefreshing || undefined}
                                onClick={() => {
                                    if (!isRefreshing) onRefresh();
                                }}
                            >
                                <RefreshCwIcon
                                    className={cn(
                                        isRefreshing &&
                                            'animate-spin motion-reduce:animate-none',
                                    )}
                                />
                            </Button>
                        )}
                        <DataTableViewOptions
                            table={table}
                            messages={messages}
                        />
                    </div>
                </div>
            </div>
            <DataTableSelectionBar
                table={table}
                messages={messages}
                actions={selectionActions}
                cellEditors={cellEditors}
            />
        </div>
    );
}
