import { Head, Link, useForm } from '@inertiajs/react';
import {
    CircleAlert,
    ExternalLink,
    Eye,
    History,
    Rocket,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { designer, preview } from '@/routes/sites';
import { show as showPublishing, store } from '@/routes/sites/publishing';
import { update as updateSubdomain } from '@/routes/sites/subdomain';
import { restore as restoreVersion } from '@/routes/sites/versions';

type PublishIssue = {
    code: string;
    message: string;
    page: string | null;
    block: string | null;
};

type PublishingProps = {
    site: { public_id: string; name: string };
    address: { subdomain: string | null; domain: string; url: string | null };
    production: {
        public_id: string;
        version_number: number;
        published_at: string | null;
        publisher: string | null;
        note: string | null;
        has_unpublished_changes: boolean | null;
    } | null;
    lastAttempt: {
        status: string;
        status_label: string;
        started_at: string;
        actor: string | null;
        note: string | null;
        error: string | null;
    } | null;
    lastRestore: {
        version_number: number;
        restored_at: string;
        actor: string | null;
    } | null;
    versions: PublishedVersionRow[];
    versionsPage: VersionsPage;
    check: { errors: PublishIssue[]; warnings: PublishIssue[] };
    can: {
        publish: boolean;
        preview: boolean;
        manageDomains: boolean;
        restoreVersion: boolean;
    };
};

type PublishedVersionRow = {
    public_id: string;
    version_number: number;
    status: string;
    status_label: string;
    published_at: string | null;
    publisher: string | null;
    note: string | null;
    is_production: boolean;
    restores_count: number;
    last_restore: { restored_at: string; actor: string | null } | null;
};

type VersionsPage = {
    current: number;
    last: number;
    total: number;
    per_page: number;
};

const NOTE_MAX = 500;

const dateFormat = new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

function formatDate(value: string | null): string {
    return value ? dateFormat.format(new Date(value)) : '—';
}

export default function Publishing({
    site,
    address,
    production,
    lastAttempt,
    lastRestore,
    versions,
    versionsPage,
    check,
    can,
}: PublishingProps) {
    const form = useForm({ note: '' });
    const blocked = check.errors.length > 0;

    function publish() {
        form.submit(store(site.public_id), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <>
            <Head title={`Публикация — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <p className="truncate text-sm text-muted-foreground">
                            {site.name}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Публикация
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Изменения в дизайнере сохраняются в черновик.
                            Посетители видят сайт только после публикации.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can.preview && (
                            <Button asChild variant="outline">
                                <a
                                    href={preview.url(site.public_id)}
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <Eye aria-hidden="true" />
                                    Предпросмотр
                                </a>
                            </Button>
                        )}
                        <Button asChild variant="outline">
                            <Link href={designer(site.public_id)}>
                                Открыть дизайнер
                            </Link>
                        </Button>
                    </div>
                </header>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <h2 className="leading-none font-semibold">
                                Опубликованная версия
                            </h2>
                            <CardDescription>
                                То, что сейчас видят посетители сайта.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {production === null ? (
                                <p
                                    className="text-sm text-muted-foreground"
                                    data-testid="production-status"
                                >
                                    Сайт ещё не опубликован.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    <dl
                                        className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm"
                                        data-testid="production-status"
                                    >
                                        <dt className="text-muted-foreground">
                                            Версия
                                        </dt>
                                        <dd>{`Версия ${production.version_number}`}</dd>
                                        <dt className="text-muted-foreground">
                                            Опубликована
                                        </dt>
                                        <dd>
                                            {formatDate(
                                                production.published_at,
                                            )}
                                        </dd>
                                        <dt className="text-muted-foreground">
                                            Опубликовал
                                        </dt>
                                        <dd>{production.publisher ?? '—'}</dd>
                                        {production.note && (
                                            <>
                                                <dt className="text-muted-foreground">
                                                    Комментарий
                                                </dt>
                                                <dd className="break-words whitespace-pre-line">
                                                    {production.note}
                                                </dd>
                                            </>
                                        )}
                                    </dl>
                                    {production.has_unpublished_changes !==
                                        null && (
                                        <Badge
                                            variant={
                                                production.has_unpublished_changes
                                                    ? 'outline'
                                                    : 'secondary'
                                            }
                                            data-testid="draft-state"
                                        >
                                            {production.has_unpublished_changes
                                                ? 'Есть неопубликованные изменения'
                                                : 'Опубликовано'}
                                        </Badge>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <h2 className="leading-none font-semibold">
                                Последняя попытка
                            </h2>
                            <CardDescription>
                                Результат последней публикации.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {lastAttempt === null ? (
                                <p className="text-muted-foreground">
                                    Публикаций ещё не было.
                                </p>
                            ) : (
                                <>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge
                                            variant={
                                                lastAttempt.status === 'failed'
                                                    ? 'destructive'
                                                    : 'secondary'
                                            }
                                            data-testid="last-attempt-status"
                                        >
                                            {lastAttempt.status_label}
                                        </Badge>
                                        <span className="text-muted-foreground">
                                            {formatDate(lastAttempt.started_at)}
                                            {lastAttempt.actor
                                                ? ` · ${lastAttempt.actor}`
                                                : ''}
                                        </span>
                                    </div>
                                    {lastAttempt.note && (
                                        <p className="break-words whitespace-pre-line text-muted-foreground">
                                            {lastAttempt.note}
                                        </p>
                                    )}
                                    {lastAttempt.error && (
                                        <p role="status">{lastAttempt.error}</p>
                                    )}
                                </>
                            )}
                            {lastRestore !== null && (
                                <p
                                    className="text-muted-foreground"
                                    data-testid="last-restore"
                                >
                                    {`Последнее восстановление: версия ${lastRestore.version_number} · ${formatDate(lastRestore.restored_at)}${lastRestore.actor ? ` · ${lastRestore.actor}` : ''}`}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <AddressCard
                    site={site}
                    address={address}
                    published={production !== null}
                    canManage={can.manageDomains}
                />

                <Card>
                    <CardHeader>
                        <h2 className="leading-none font-semibold">
                            Проверка перед публикацией
                        </h2>
                        <CardDescription>
                            {blocked
                                ? 'Исправьте ошибки в черновике, чтобы опубликовать сайт.'
                                : 'Ошибок нет, сайт можно опубликовать.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <IssueList
                            issues={check.errors}
                            tone="error"
                            testId="publish-errors"
                        />
                        <IssueList
                            issues={check.warnings}
                            tone="warning"
                            testId="publish-warnings"
                        />
                        {can.publish ? (
                            <div className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="publication-note">
                                        Комментарий к публикации
                                    </Label>
                                    <textarea
                                        id="publication-note"
                                        name="note"
                                        value={form.data.note}
                                        onChange={(event) =>
                                            form.setData(
                                                'note',
                                                event.target.value,
                                            )
                                        }
                                        maxLength={NOTE_MAX}
                                        rows={3}
                                        aria-invalid={
                                            form.errors.note ? true : undefined
                                        }
                                        aria-describedby="publication-note-help"
                                        className="flex w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm"
                                    />
                                    <p
                                        id="publication-note-help"
                                        className="text-xs text-muted-foreground"
                                    >
                                        {`Необязательно. Видно в истории версий. ${form.data.note.length} / ${NOTE_MAX}`}
                                    </p>
                                    <InputError message={form.errors.note} />
                                </div>
                                <Button
                                    onClick={publish}
                                    disabled={form.processing || blocked}
                                >
                                    {form.processing ? (
                                        <Spinner />
                                    ) : (
                                        <Rocket aria-hidden="true" />
                                    )}
                                    Опубликовать
                                </Button>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                У вас нет права публиковать этот сайт.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <VersionHistory
                    site={site}
                    versions={versions}
                    pagination={versionsPage}
                    canRestore={can.restoreVersion}
                />
            </main>
        </>
    );
}

function restoreSummary(version: PublishedVersionRow): string | null {
    if (version.last_restore === null) {
        return null;
    }

    const actor = version.last_restore.actor
        ? ` · ${version.last_restore.actor}`
        : '';

    return `Восстановлена в черновик: ${version.restores_count} раз(а), последний — ${formatDate(version.last_restore.restored_at)}${actor}`;
}

function VersionHistory({
    site,
    versions,
    pagination,
    canRestore,
}: {
    site: PublishingProps['site'];
    versions: PublishedVersionRow[];
    pagination: VersionsPage;
    canRestore: boolean;
}) {
    const [restoring, setRestoring] = useState<PublishedVersionRow | null>(
        null,
    );
    const form = useForm({});

    function restore() {
        if (restoring === null) {
            return;
        }

        form.submit(
            restoreVersion({
                site: site.public_id,
                version: restoring.public_id,
            }),
            { preserveScroll: true, onFinish: () => setRestoring(null) },
        );
    }

    return (
        <Card>
            <CardHeader>
                <h2 className="leading-none font-semibold">История версий</h2>
                <CardDescription>
                    Восстановление заменяет черновик выбранной версией.
                    Опубликованный сайт не меняется до новой публикации.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {versions.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Версий пока нет.
                    </p>
                ) : (
                    <ul
                        className="divide-y rounded-md border"
                        data-testid="version-history"
                    >
                        {versions.map((version) => (
                            <li
                                key={version.public_id}
                                className="flex flex-col gap-2 p-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="min-w-0 space-y-1">
                                    <div className="flex min-w-0 flex-wrap items-center gap-2">
                                        <span className="font-medium">
                                            {`Версия ${version.version_number}`}
                                        </span>
                                        {version.is_production && (
                                            <Badge>На сайте</Badge>
                                        )}
                                        <Badge
                                            variant={
                                                version.status === 'failed'
                                                    ? 'destructive'
                                                    : 'secondary'
                                            }
                                        >
                                            {version.status_label}
                                        </Badge>
                                        <span className="text-muted-foreground">
                                            {formatDate(version.published_at)}
                                            {version.publisher
                                                ? ` · ${version.publisher}`
                                                : ''}
                                        </span>
                                    </div>
                                    {version.note && (
                                        <p className="break-words whitespace-pre-line">
                                            {version.note}
                                        </p>
                                    )}
                                    {restoreSummary(version) && (
                                        <p className="text-xs text-muted-foreground">
                                            {restoreSummary(version)}
                                        </p>
                                    )}
                                </div>
                                {canRestore && version.status === 'ready' && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setRestoring(version)}
                                        aria-label={`Восстановить версию ${version.version_number} в черновик`}
                                    >
                                        <History aria-hidden="true" />
                                        Восстановить в черновик
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                {pagination.last > 1 && (
                    <nav
                        aria-label="Страницы истории версий"
                        className="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm"
                    >
                        <span className="text-muted-foreground">
                            {`Страница ${pagination.current} из ${pagination.last} · всего версий: ${pagination.total}`}
                        </span>
                        <div className="flex gap-2">
                            <HistoryPageLink
                                site={site}
                                page={pagination.current - 1}
                                disabled={pagination.current <= 1}
                            >
                                Новее
                            </HistoryPageLink>
                            <HistoryPageLink
                                site={site}
                                page={pagination.current + 1}
                                disabled={pagination.current >= pagination.last}
                            >
                                Старее
                            </HistoryPageLink>
                        </div>
                    </nav>
                )}
            </CardContent>

            <Dialog
                open={restoring !== null}
                onOpenChange={(open) => !open && setRestoring(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {`Восстановить версию ${restoring?.version_number ?? ''}?`}
                        </DialogTitle>
                        <DialogDescription>
                            Текущий черновик будет заменён страницами, блоками,
                            автомобилями, формами и попапами этой версии. Заявки
                            и настройки защиты форм не изменятся. Опубликованный
                            сайт останется прежним, пока вы не опубликуете
                            черновик.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRestoring(null)}
                        >
                            Отмена
                        </Button>
                        <Button
                            type="button"
                            onClick={restore}
                            disabled={form.processing}
                        >
                            {form.processing && <Spinner />}
                            Восстановить
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Card>
    );
}

function HistoryPageLink({
    site,
    page,
    disabled,
    children,
}: {
    site: PublishingProps['site'];
    page: number;
    disabled: boolean;
    children: ReactNode;
}) {
    if (disabled) {
        return (
            <Button variant="outline" size="sm" disabled>
                {children}
            </Button>
        );
    }

    return (
        <Button asChild variant="outline" size="sm">
            <Link
                href={showPublishing.url(site.public_id, { query: { page } })}
                preserveScroll
            >
                {children}
            </Link>
        </Button>
    );
}

function AddressCard({
    site,
    address,
    published,
    canManage,
}: {
    site: PublishingProps['site'];
    address: PublishingProps['address'];
    published: boolean;
    canManage: boolean;
}) {
    const form = useForm({ subdomain: address.subdomain ?? '' });
    const unchanged = form.data.subdomain === (address.subdomain ?? '');

    function save(event: FormEvent) {
        event.preventDefault();
        form.submit(updateSubdomain(site.public_id), { preserveScroll: true });
    }

    return (
        <Card>
            <CardHeader>
                <h2 className="leading-none font-semibold">Адрес сайта</h2>
                <CardDescription>
                    Сайт открывается на поддомене Landflow. Смена названия сайта
                    адрес не меняет.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4 text-sm">
                {address.url === null ? (
                    <p className="text-muted-foreground">Адрес не задан.</p>
                ) : published ? (
                    <a
                        href={address.url}
                        target="_blank"
                        rel="noopener"
                        className="inline-flex max-w-full items-center gap-1 font-medium break-all underline underline-offset-4"
                        data-testid="public-url"
                    >
                        {address.url}
                        <ExternalLink
                            aria-hidden="true"
                            className="size-4 shrink-0"
                        />
                    </a>
                ) : (
                    <p data-testid="public-url">
                        <span className="font-medium break-all">
                            {address.url}
                        </span>
                        <span className="text-muted-foreground">
                            {' '}
                            — начнёт работать после публикации.
                        </span>
                    </p>
                )}

                {canManage && (
                    <form onSubmit={save} className="space-y-2">
                        <Label htmlFor="subdomain">Поддомен</Label>
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <div className="flex min-w-0 flex-1 items-center gap-2">
                                <Input
                                    id="subdomain"
                                    name="subdomain"
                                    value={form.data.subdomain}
                                    onChange={(event) =>
                                        form.setData(
                                            'subdomain',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={63}
                                    autoComplete="off"
                                    spellCheck={false}
                                    aria-invalid={
                                        form.errors.subdomain ? true : undefined
                                    }
                                    aria-describedby="subdomain-help"
                                    className="min-w-0"
                                />
                                <span className="shrink-0 text-muted-foreground">
                                    .{address.domain}
                                </span>
                            </div>
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={form.processing || unchanged}
                            >
                                {form.processing && <Spinner />}
                                Сохранить адрес
                            </Button>
                        </div>
                        <p
                            id="subdomain-help"
                            className="text-xs text-muted-foreground"
                        >
                            Строчные латинские буквы, цифры и дефис.
                            {published &&
                                ' После смены старый адрес перестанет открываться.'}
                        </p>
                        <InputError message={form.errors.subdomain} />
                    </form>
                )}
            </CardContent>
        </Card>
    );
}

function IssueList({
    issues,
    tone,
    testId,
}: {
    issues: PublishIssue[];
    tone: 'error' | 'warning';
    testId: string;
}) {
    if (issues.length === 0) {
        return null;
    }

    const Icon = tone === 'error' ? CircleAlert : TriangleAlert;

    return (
        <ul className="space-y-2" data-testid={testId}>
            {issues.map((issue, index) => (
                <li
                    key={`${issue.code}-${issue.page ?? ''}-${issue.block ?? ''}-${index}`}
                    className="flex items-start gap-2 text-sm"
                >
                    <Icon
                        aria-hidden="true"
                        className={
                            tone === 'error'
                                ? 'mt-0.5 size-4 shrink-0 text-destructive'
                                : 'mt-0.5 size-4 shrink-0 text-amber-600'
                        }
                    />
                    <span>
                        <span className="sr-only">
                            {tone === 'error' ? 'Ошибка: ' : 'Предупреждение: '}
                        </span>
                        {issue.message}
                    </span>
                </li>
            ))}
        </ul>
    );
}
