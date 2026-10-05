import { Head, Link, useForm } from '@inertiajs/react';
import { CircleAlert, Eye, Rocket, TriangleAlert } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { designer, preview } from '@/routes/sites';
import { store } from '@/routes/sites/publishing';

type PublishIssue = {
    code: string;
    message: string;
    page: string | null;
    block: string | null;
};

type PublishingProps = {
    site: { public_id: string; name: string };
    production: {
        public_id: string;
        version_number: number;
        published_at: string | null;
        publisher: string | null;
    } | null;
    lastAttempt: {
        status: string;
        status_label: string;
        started_at: string;
        actor: string | null;
        error: string | null;
    } | null;
    check: { errors: PublishIssue[]; warnings: PublishIssue[] };
    can: { publish: boolean; preview: boolean };
};

const dateFormat = new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

function formatDate(value: string | null): string {
    return value ? dateFormat.format(new Date(value)) : '—';
}

export default function Publishing({
    site,
    production,
    lastAttempt,
    check,
    can,
}: PublishingProps) {
    const form = useForm({});
    const blocked = check.errors.length > 0;

    function publish() {
        form.submit(store(site.public_id), { preserveScroll: true });
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
                                        {formatDate(production.published_at)}
                                    </dd>
                                    <dt className="text-muted-foreground">
                                        Опубликовал
                                    </dt>
                                    <dd>{production.publisher ?? '—'}</dd>
                                </dl>
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
                                    {lastAttempt.error && (
                                        <p role="status">{lastAttempt.error}</p>
                                    )}
                                </>
                            )}
                        </CardContent>
                    </Card>
                </div>

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
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                У вас нет права публиковать этот сайт.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </main>
        </>
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
