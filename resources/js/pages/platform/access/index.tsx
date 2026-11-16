import { Head, router, useForm } from '@inertiajs/react';
import { Search } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { index as accessIndex } from '@/routes/platform/access';
import { update as updatePlan } from '@/routes/platform/access/plan';
import { update as updateRoles } from '@/routes/platform/access/roles';
import { dashboard } from '@/routes';

type RoleOption = { value: string; label: string };
type PlanOption = { value: string; label: string };

type AccessUser = {
    email: string;
    name: string;
    verified: boolean;
    roles: string[];
    workspaces: {
        public_id: string;
        name: string;
        plan_key: string | null;
        plan_name: string | null;
    }[];
};

type AccessProps = {
    query: string;
    user: AccessUser | null;
    notFound: boolean;
    roleOptions: RoleOption[];
    planOptions: PlanOption[];
};

function SearchForm({ query }: { query: string }) {
    const form = useForm({ email: query });

    function submit(event: FormEvent) {
        event.preventDefault();
        router.get(
            accessIndex.url({ query: { email: form.data.email.trim() } }),
            {},
            { preserveState: false },
        );
    }

    return (
        <form
            onSubmit={submit}
            className="flex flex-col gap-3 sm:flex-row sm:items-end"
        >
            <div className="grid min-w-0 flex-1 gap-2">
                <Label htmlFor="access-email">Email пользователя</Label>
                <Input
                    id="access-email"
                    type="email"
                    value={form.data.email}
                    required
                    maxLength={255}
                    autoComplete="off"
                    placeholder="user@example.com"
                    onChange={(event) =>
                        form.setData('email', event.target.value)
                    }
                />
            </div>
            <Button type="submit">
                <Search aria-hidden="true" />
                Найти
            </Button>
        </form>
    );
}

function RolesForm({
    user,
    roleOptions,
}: {
    user: AccessUser;
    roleOptions: RoleOption[];
}) {
    const form = useForm({
        email: user.email,
        roles: [...user.roles],
    });

    function toggle(role: string, checked: boolean) {
        form.setData(
            'roles',
            checked
                ? [...new Set([...form.data.roles, role])]
                : form.data.roles.filter((value) => value !== role),
        );
    }

    function save(event: FormEvent) {
        event.preventDefault();
        form.put(updateRoles.url(), { preserveScroll: true });
    }

    return (
        <form onSubmit={save} className="flex flex-col gap-4">
            <div className="space-y-1">
                <h2 className="font-medium">Роли платформы</h2>
                <p className="text-sm text-muted-foreground">
                    Роли Landflow, не роли внутри пространства. CLI{' '}
                    <span className="font-mono">platform:role</span> по-прежнему
                    работает.
                </p>
            </div>
            <ul className="grid gap-3">
                {roleOptions.map((role) => {
                    const id = `role-${role.value}`;
                    const checked = form.data.roles.includes(role.value);

                    return (
                        <li key={role.value} className="flex items-start gap-2">
                            <Checkbox
                                id={id}
                                checked={checked}
                                onCheckedChange={(value) =>
                                    toggle(role.value, value === true)
                                }
                            />
                            <Label htmlFor={id} className="leading-snug">
                                {role.label}
                                <span className="mt-0.5 block font-mono text-xs font-normal text-muted-foreground">
                                    {role.value}
                                </span>
                            </Label>
                        </li>
                    );
                })}
            </ul>
            <InputError message={form.errors.roles ?? form.errors.email} />
            <div>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    Сохранить роли
                </Button>
            </div>
        </form>
    );
}

function WorkspacePlanRow({
    userEmail,
    workspace,
    planOptions,
}: {
    userEmail: string;
    workspace: AccessUser['workspaces'][number];
    planOptions: PlanOption[];
}) {
    const currentPlan = workspace.plan_key ?? planOptions[0]?.value ?? '';
    const form = useForm({
        email: userEmail,
        workspace: workspace.public_id,
        plan: currentPlan,
    });

    function save(event: FormEvent) {
        event.preventDefault();
        form.put(updatePlan.url(), { preserveScroll: true });
    }

    return (
        <li className="rounded-xl border p-4">
            <form
                onSubmit={save}
                className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div className="min-w-0 space-y-1">
                    <p className="truncate font-medium">{workspace.name}</p>
                    <p className="font-mono text-xs break-all text-muted-foreground">
                        {workspace.public_id}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Сейчас:{' '}
                        {workspace.plan_name ??
                            workspace.plan_key ??
                            'без тарифа'}
                    </p>
                </div>
                <div className="flex w-full flex-col gap-2 sm:w-auto">
                    <div className="flex flex-wrap items-end gap-2">
                        <div className="grid gap-2">
                            <Label htmlFor={`plan-${workspace.public_id}`}>
                                Тариф
                            </Label>
                            <Select
                                value={form.data.plan}
                                onValueChange={(value) =>
                                    form.setData('plan', value)
                                }
                            >
                                <SelectTrigger
                                    id={`plan-${workspace.public_id}`}
                                    className="w-40"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {planOptions.map((plan) => (
                                        <SelectItem
                                            key={plan.value}
                                            value={plan.value}
                                        >
                                            {plan.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                form.data.plan === currentPlan
                            }
                        >
                            {form.processing && <Spinner />}
                            Назначить
                        </Button>
                    </div>
                    <InputError
                        message={
                            form.errors.plan ??
                            form.errors.workspace ??
                            form.errors.email
                        }
                    />
                </div>
            </form>
        </li>
    );
}

export default function PlatformAccess({
    query,
    user,
    notFound,
    roleOptions,
    planOptions,
}: AccessProps) {
    return (
        <>
            <Head title="Доступы" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Доступы
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Бета: пока нет оплаты, суперадминистратор назначает
                        тариф пространства (базовый / полный) и роли платформы
                        вручную. Когда появится биллинг, доступы пойдут от
                        купленного тарифа.
                    </p>
                </header>

                <section
                    aria-label="Поиск пользователя"
                    className="rounded-xl border p-4"
                >
                    <SearchForm query={query} />
                </section>

                {notFound && (
                    <p className="text-sm text-muted-foreground">
                        Пользователь с email «{query}» не найден. Аккаунт должен
                        уже существовать.
                    </p>
                )}

                {user && (
                    <section
                        aria-label={`Доступы ${user.email}`}
                        className="grid gap-6 lg:grid-cols-2"
                    >
                        <div className="flex flex-col gap-4 rounded-xl border p-4">
                            <div className="space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h2 className="font-medium break-all">
                                        {user.email}
                                    </h2>
                                    <Badge
                                        variant={
                                            user.verified
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {user.verified
                                            ? 'Email подтверждён'
                                            : 'Email не подтверждён'}
                                    </Badge>
                                </div>
                                {user.name && (
                                    <p className="text-sm text-muted-foreground">
                                        {user.name}
                                    </p>
                                )}
                            </div>
                            <RolesForm user={user} roleOptions={roleOptions} />
                        </div>

                        <div className="flex flex-col gap-4 rounded-xl border p-4">
                            <div className="space-y-1">
                                <h2 className="font-medium">
                                    Пространства (владелец)
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Тариф назначается только пространствам, где
                                    пользователь — Owner. CLI{' '}
                                    <span className="font-mono">
                                        plan:assign
                                    </span>{' '}
                                    тоже остаётся.
                                </p>
                            </div>
                            {user.workspaces.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    У пользователя нет пространств, где он
                                    владелец.
                                </p>
                            ) : (
                                <ul className="grid gap-3">
                                    {user.workspaces.map((workspace) => (
                                        <WorkspacePlanRow
                                            key={workspace.public_id}
                                            userEmail={user.email}
                                            workspace={workspace}
                                            planOptions={planOptions}
                                        />
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>
                )}
            </main>
        </>
    );
}

PlatformAccess.layout = {
    breadcrumbs: [{ title: 'Все сайты', href: dashboard() }],
};
