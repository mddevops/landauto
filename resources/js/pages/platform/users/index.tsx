import { Head } from '@inertiajs/react';
import {
    createDataTableColumnHelper,
    useDataTable,
} from '@querycn/table-react';
import { DataTable } from '@/components/data-table/data-table';
import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { DataTableSearch } from '@/components/data-table/data-table-search';
import { DataTableToolbar } from '@/components/data-table/data-table-toolbar';
import { ruTableMessages } from '@/components/data-table/ru-messages';
import { Badge } from '@/components/ui/badge';
import { dashboard } from '@/routes';

type PlatformUser = {
    name: string;
    email: string;
    registered_at: string;
    roles: string;
    plans: string;
    verified: boolean;
    verified_label: string;
};

const dateFormat = new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const column = createDataTableColumnHelper<PlatformUser>();

const columns = [
    column.accessor('name', { header: 'Имя', size: 180 }),
    column.accessor('email', { header: 'Почта', size: 240 }),
    column.accessor('registered_at', {
        header: 'Дата регистрации',
        size: 180,
        sortFn: 'datetime',
        cell: ({ getValue }) => {
            const value = getValue();

            return value === '' ? '—' : dateFormat.format(new Date(value));
        },
    }),
    column.accessor('roles', { header: 'Роли', size: 220 }),
    column.accessor('plans', { header: 'Тариф', size: 160 }),
    column.accessor('verified_label', {
        header: 'Почта подтверждена',
        size: 170,
        cell: ({ row }) => (
            <Badge variant={row.original.verified ? 'secondary' : 'outline'}>
                {row.original.verified_label}
            </Badge>
        ),
    }),
];

function UsersTable({ users }: { users: PlatformUser[] }) {
    const table = useDataTable({
        data: users,
        columns,
        getRowId: (user) => user.email,
        storageKey: 'platform-users',
        enableRowSelection: false,
    });

    return (
        <div className="flex flex-col gap-3">
            <DataTableToolbar table={table}>
                <DataTableSearch table={table} messages={ruTableMessages} />
            </DataTableToolbar>
            <DataTable
                table={table}
                messages={ruTableMessages}
                className="max-h-[640px]"
            />
            <DataTablePagination table={table} messages={ruTableMessages} />
        </div>
    );
}

export default function PlatformUsers({
    users,
    total,
}: {
    users: PlatformUser[];
    total: number;
}) {
    return (
        <>
            <Head title="Пользователи" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Пользователи
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {`Зарегистрировано: ${total}`}
                    </p>
                </header>
                <UsersTable users={users} />
            </main>
        </>
    );
}

PlatformUsers.layout = {
    breadcrumbs: [
        { title: 'Все сайты', href: dashboard() },
        { title: 'Пользователи', href: '/platform/users' },
    ],
};
