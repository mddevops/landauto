import { Head, router, useForm } from '@inertiajs/react';
import { Mail, UserPlus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import type { Choice } from '@/components/platform/form-fields';
import { SelectField, TextField } from '@/components/platform/form-fields';
import type {
    SiteAccessMode,
    TeamSite,
} from '@/components/team/site-access-fields';
import { SiteAccessFields } from '@/components/team/site-access-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { index as teamIndex } from '@/routes/workspace/team';
import { destroy, resend, store } from '@/routes/workspace/team/invitations';
import {
    destroy as removeMember,
    reactivate as reactivateMember,
    role as updateRole,
    siteAccess as updateSiteAccess,
    suspend as suspendMember,
} from '@/routes/workspace/team/members';

type MemberRow = {
    public_id: string;
    name: string;
    email: string;
    role: string;
    role_label: string;
    status: string;
    joined_at: string | null;
    is_self: boolean;
    can_manage: boolean;
    site_access_mode: SiteAccessMode;
    sites: string[];
};

type InvitationRow = {
    public_id: string;
    email: string;
    role: string;
    role_label: string;
    state: 'pending' | 'expired';
    expires_at: string;
    can_manage: boolean;
    site_access_mode: SiteAccessMode;
    site_count: number;
};

type TeamProps = {
    members: MemberRow[];
    invitations: InvitationRow[];
    sites: TeamSite[];
    seats: { limit: number; reserved: number };
    assignableRoles: Choice[];
    allSitesRoles: string[];
    canManageRoles: boolean;
    invitationTtlHours: number;
};

function RoleDialog({
    member,
    roles,
    allSitesRoles,
}: {
    member: MemberRow;
    roles: Choice[];
    allSitesRoles: string[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ role: member.role });
    const forcesAllSites =
        allSitesRoles.includes(form.data.role) &&
        member.site_access_mode === 'selected_sites';

    function submit(event: FormEvent) {
        event.preventDefault();
        form.submit(updateRole(member.public_id), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                form.clearErrors();

                if (next) {
                    form.setData('role', member.role);
                }
            }}
        >
            <DialogTrigger asChild>
                <Button type="button" size="sm" variant="outline">
                    Изменить роль
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Роль участника</DialogTitle>
                    <DialogDescription>
                        {member.name || member.email}. Роль определяет, что
                        участник может делать в пространстве.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <SelectField
                        id={`role-${member.public_id}`}
                        label="Роль"
                        choices={roles}
                        value={form.data.role}
                        onChange={(event) =>
                            form.setData('role', event.target.value)
                        }
                        required
                        error={form.errors.role}
                    />
                    {forcesAllSites && (
                        <p className="text-sm text-muted-foreground">
                            Для этой роли откроется доступ ко всем сайтам
                            пространства.
                        </p>
                    )}
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Отмена
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Сохранить
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function siteAccessSummary(mode: SiteAccessMode, count: number): string {
    return mode === 'all_sites' ? 'Все сайты' : `Выбранные сайты: ${count}`;
}

const memberStatusLabels: Record<string, string> = {
    active: 'Активен',
    suspended: 'Приостановлен',
    invited: 'Приглашён',
};

function firstNestedError(
    errors: Partial<Record<string, string>>,
    prefix: string,
): string | undefined {
    const key = Object.keys(errors).find((name) => name.startsWith(prefix));

    return key ? errors[key] : undefined;
}

function SiteAccessDialog({
    member,
    sites,
}: {
    member: MemberRow;
    sites: TeamSite[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ site_access_mode: SiteAccessMode; sites: string[] }>(
        { site_access_mode: member.site_access_mode, sites: member.sites },
    );

    function submit(event: FormEvent) {
        event.preventDefault();
        form.submit(updateSiteAccess(member.public_id), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                form.clearErrors();

                if (next) {
                    form.setData({
                        site_access_mode: member.site_access_mode,
                        sites: member.sites,
                    });
                }
            }}
        >
            <DialogTrigger asChild>
                <Button type="button" size="sm" variant="outline">
                    Доступ к сайтам
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Доступ к сайтам</DialogTitle>
                    <DialogDescription>
                        {member.name || member.email} — {member.role_label}.
                        Роль определяет действия, а доступ — на каких сайтах их
                        можно выполнять.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <SiteAccessFields
                        idPrefix={`member-${member.public_id}`}
                        sites={sites}
                        mode={form.data.site_access_mode}
                        selected={form.data.sites}
                        onModeChange={(mode) =>
                            form.setData('site_access_mode', mode)
                        }
                        onSelectedChange={(selected) =>
                            form.setData('sites', selected)
                        }
                        error={
                            form.errors.sites ??
                            form.errors.site_access_mode ??
                            firstNestedError(form.errors, 'sites.')
                        }
                        forcedAllSites={false}
                    />
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Отмена
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Сохранить
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString('ru-RU', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

function InviteDialog({
    roles,
    sites,
    allSitesRoles,
    disabled,
    ttlHours,
}: {
    roles: Choice[];
    sites: TeamSite[];
    allSitesRoles: string[];
    disabled: boolean;
    ttlHours: number;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<{
        email: string;
        role: string;
        site_access_mode: SiteAccessMode;
        sites: string[];
    }>({
        email: '',
        role: roles[0]?.value ?? '',
        site_access_mode: 'all_sites',
        sites: [],
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.submit(store(), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                form.clearErrors();
            }}
        >
            <DialogTrigger asChild>
                <Button disabled={disabled}>
                    <UserPlus />
                    Пригласить
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Пригласить участника</DialogTitle>
                    <DialogDescription>
                        Мы отправим письмо со ссылкой. Ссылка действует{' '}
                        {ttlHours % 24 === 0
                            ? `${ttlHours / 24} дн.`
                            : `${ttlHours} ч.`}{' '}
                        и подходит только для указанного адреса.
                    </DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={submit}
                    className="grid gap-4"
                    autoComplete="off"
                >
                    <TextField
                        id="invite-email"
                        label="Электронная почта"
                        type="email"
                        inputMode="email"
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                        maxLength={255}
                        required
                        error={form.errors.email}
                    />
                    <SelectField
                        id="invite-role"
                        label="Роль"
                        choices={roles}
                        value={form.data.role}
                        onChange={(event) =>
                            form.setData('role', event.target.value)
                        }
                        required
                        error={form.errors.role}
                    />
                    <SiteAccessFields
                        idPrefix="invite"
                        sites={sites}
                        mode={form.data.site_access_mode}
                        selected={form.data.sites}
                        onModeChange={(mode) =>
                            form.setData('site_access_mode', mode)
                        }
                        onSelectedChange={(selected) =>
                            form.setData('sites', selected)
                        }
                        error={
                            form.errors.sites ??
                            form.errors.site_access_mode ??
                            firstNestedError(form.errors, 'sites.')
                        }
                        forcedAllSites={allSitesRoles.includes(form.data.role)}
                    />
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Отмена
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Отправить приглашение
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function InvitationActions({ invitation }: { invitation: InvitationRow }) {
    const [confirmCancel, setConfirmCancel] = useState(false);
    const [processing, setProcessing] = useState(false);
    const options = {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => {
            setProcessing(false);
            setConfirmCancel(false);
        },
    };

    if (!invitation.can_manage) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-2">
            <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={processing}
                onClick={() =>
                    router.post(resend.url(invitation.public_id), {}, options)
                }
            >
                Отправить повторно
            </Button>
            {confirmCancel ? (
                <Button
                    type="button"
                    size="sm"
                    variant="destructive"
                    disabled={processing}
                    onClick={() =>
                        router.delete(
                            destroy.url(invitation.public_id),
                            options,
                        )
                    }
                >
                    Подтвердить отмену
                </Button>
            ) : (
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    disabled={processing}
                    onClick={() => setConfirmCancel(true)}
                >
                    Отменить приглашение
                </Button>
            )}
        </div>
    );
}

function MemberActions({
    member,
    sites,
    allSitesRoles,
    roles,
    canManageRoles,
}: {
    member: MemberRow;
    sites: TeamSite[];
    allSitesRoles: string[];
    roles: Choice[];
    canManageRoles: boolean;
}) {
    const [confirmRemove, setConfirmRemove] = useState(false);
    const [processing, setProcessing] = useState(false);
    const options = {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => {
            setProcessing(false);
            setConfirmRemove(false);
        },
    };

    if (!member.can_manage) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-2">
            {canManageRoles && (
                <RoleDialog
                    member={member}
                    roles={roles}
                    allSitesRoles={allSitesRoles}
                />
            )}
            {!allSitesRoles.includes(member.role) && (
                <SiteAccessDialog member={member} sites={sites} />
            )}
            {member.status === 'suspended' ? (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={processing}
                    onClick={() =>
                        router.post(
                            reactivateMember.url(member.public_id),
                            {},
                            options,
                        )
                    }
                >
                    Восстановить доступ
                </Button>
            ) : (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={processing}
                    onClick={() =>
                        router.post(
                            suspendMember.url(member.public_id),
                            {},
                            options,
                        )
                    }
                >
                    Приостановить
                </Button>
            )}
            {confirmRemove ? (
                <Button
                    type="button"
                    size="sm"
                    variant="destructive"
                    disabled={processing}
                    onClick={() =>
                        router.delete(
                            removeMember.url(member.public_id),
                            options,
                        )
                    }
                >
                    Подтвердить удаление
                </Button>
            ) : (
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    disabled={processing}
                    onClick={() => setConfirmRemove(true)}
                >
                    Удалить из пространства
                </Button>
            )}
        </div>
    );
}

export default function WorkspaceTeam({
    members,
    invitations,
    sites,
    seats,
    assignableRoles,
    allSitesRoles,
    canManageRoles,
    invitationTtlHours,
}: TeamProps) {
    const unavailable = seats.limit === 0;
    const full = !unavailable && seats.reserved >= seats.limit;

    return (
        <>
            <Head title="Команда" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <p className="text-sm text-muted-foreground">
                            Рабочее пространство
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Команда
                        </h1>
                    </div>
                    <InviteDialog
                        roles={assignableRoles}
                        sites={sites}
                        allSitesRoles={allSitesRoles}
                        disabled={
                            unavailable || full || assignableRoles.length === 0
                        }
                        ttlHours={invitationTtlHours}
                    />
                </header>

                <p className="text-sm text-muted-foreground" data-test="seats">
                    {unavailable
                        ? 'Добавление участников недоступно на текущем тарифе.'
                        : full
                          ? `Все места заняты: ${seats.reserved} из ${seats.limit}. Отмените приглашение или повысьте тариф.`
                          : `Занято мест: ${seats.reserved} из ${seats.limit}. Места занимают участники и действующие приглашения.`}
                </p>

                <Card>
                    <CardHeader>
                        <CardTitle>Участники</CardTitle>
                        <CardDescription>
                            Люди, у которых есть доступ к пространству.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="divide-y" aria-label="Участники">
                            {members.map((member) => (
                                <li
                                    key={member.public_id}
                                    className="flex flex-col gap-3 py-3 lg:flex-row lg:items-center lg:justify-between"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium break-words">
                                            {member.name}
                                            {member.is_self && (
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    (вы)
                                                </span>
                                            )}
                                        </p>
                                        <p className="text-sm break-all text-muted-foreground">
                                            {member.email}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant="secondary">
                                            {member.role_label}
                                        </Badge>
                                        <Badge
                                            variant={
                                                member.status === 'active'
                                                    ? 'outline'
                                                    : 'destructive'
                                            }
                                        >
                                            {memberStatusLabels[
                                                member.status
                                            ] ?? member.status}
                                        </Badge>
                                        <Badge variant="outline">
                                            {siteAccessSummary(
                                                member.site_access_mode,
                                                member.sites.length,
                                            )}
                                        </Badge>
                                        <MemberActions
                                            member={member}
                                            sites={sites}
                                            allSitesRoles={allSitesRoles}
                                            roles={assignableRoles}
                                            canManageRoles={canManageRoles}
                                        />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Приглашения</CardTitle>
                        <CardDescription>
                            Повторная отправка создаёт новую ссылку, старая
                            перестаёт работать.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {invitations.length === 0 ? (
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Mail className="size-4" aria-hidden />
                                Активных приглашений нет.
                            </div>
                        ) : (
                            <ul className="divide-y" aria-label="Приглашения">
                                {invitations.map((invitation) => (
                                    <li
                                        key={invitation.public_id}
                                        className="flex flex-col gap-3 py-3 lg:flex-row lg:items-center lg:justify-between"
                                    >
                                        <div className="min-w-0 space-y-1">
                                            <p className="font-medium break-all">
                                                {invitation.email}
                                            </p>
                                            <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                                <Badge variant="secondary">
                                                    {invitation.role_label}
                                                </Badge>
                                                <Badge variant="outline">
                                                    {siteAccessSummary(
                                                        invitation.site_access_mode,
                                                        invitation.site_count,
                                                    )}
                                                </Badge>
                                                {invitation.state ===
                                                'expired' ? (
                                                    <Badge variant="destructive">
                                                        Срок истёк
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline">
                                                        Ожидает ответа
                                                    </Badge>
                                                )}
                                                <span>
                                                    {invitation.state ===
                                                    'expired'
                                                        ? 'Истекло '
                                                        : 'Действует до '}
                                                    {formatDate(
                                                        invitation.expires_at,
                                                    )}
                                                </span>
                                            </div>
                                        </div>
                                        <InvitationActions
                                            invitation={invitation}
                                        />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}

WorkspaceTeam.layout = {
    breadcrumbs: [
        { title: 'Все сайты', href: dashboard() },
        { title: 'Команда', href: teamIndex() },
    ],
};
