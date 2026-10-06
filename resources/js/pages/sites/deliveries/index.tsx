import { Head, Link, router } from '@inertiajs/react';
import { RotateCcw, Send } from 'lucide-react';
import { useState } from 'react';
import type { DeliveryStatus } from '@/components/integrations/delivery-status-badge';
import { DeliveryStatusBadge } from '@/components/integrations/delivery-status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { designer } from '@/routes/sites';
import {
    index as deliveriesIndex,
    retry as retryDelivery,
} from '@/routes/sites/deliveries';
import { index as submissionsIndex } from '@/routes/sites/submissions';

type DeliveryAttempt = {
    number: number;
    manual: boolean;
    started_at: string;
    succeeded: boolean;
    http_status: number | null;
    latency_ms: number | null;
    error: string | null;
};

type DeliveryRow = {
    public_id: string;
    submission: { public_id: string; submitted_at: string };
    form: string;
    route: string;
    destination: string;
    status: DeliveryStatus;
    status_label: string;
    attempt_count: number;
    last_attempt_at: string | null;
    next_retry_at: string | null;
    delivered_at: string | null;
    http_status: number | null;
    error: string | null;
    attempts: DeliveryAttempt[];
};

type DeliveriesIndexProps = {
    site: { public_id: string; name: string };
    status: DeliveryStatus | null;
    statuses: { value: DeliveryStatus; label: string }[];
    can: { retry: boolean };
    deliveries: DeliveryRow[];
    pagination: {
        current: number;
        last: number;
        total: number;
        prev: string | null;
        next: string | null;
    };
};

const dateFormat = new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

function formatDate(value: string | null): string {
    return value === null ? '—' : dateFormat.format(new Date(value));
}

export default function DeliveriesIndex({
    site,
    status,
    statuses,
    can,
    deliveries,
    pagination,
}: DeliveriesIndexProps) {
    const [retrying, setRetrying] = useState<string | null>(null);

    function retry(delivery: DeliveryRow) {
        setRetrying(delivery.public_id);
        router.post(
            retryDelivery.url({
                site: site.public_id,
                delivery: delivery.public_id,
            }),
            {},
            { preserveScroll: true, onFinish: () => setRetrying(null) },
        );
    }

    return (
        <>
            <Head title={`Доставка заявок — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <p className="truncate text-sm text-muted-foreground">
                            {site.name}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Доставка заявок
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {`Всего: ${pagination.total}`}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href={submissionsIndex(site.public_id)}>
                                Заявки
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={designer(site.public_id)}>
                                Открыть дизайнер
                            </Link>
                        </Button>
                    </div>
                </header>

                <nav
                    aria-label="Статус доставки"
                    className="flex flex-wrap gap-2"
                >
                    <Button
                        asChild
                        size="sm"
                        variant={status === null ? 'default' : 'outline'}
                    >
                        <Link
                            href={deliveriesIndex(site.public_id)}
                            aria-current={status === null ? 'page' : undefined}
                        >
                            Все
                        </Link>
                    </Button>
                    {statuses.map((option) => (
                        <Button
                            key={option.value}
                            asChild
                            size="sm"
                            variant={
                                status === option.value ? 'default' : 'outline'
                            }
                        >
                            <Link
                                href={deliveriesIndex(site.public_id, {
                                    query: { status: option.value },
                                })}
                                aria-current={
                                    status === option.value ? 'page' : undefined
                                }
                            >
                                {option.label}
                            </Link>
                        </Button>
                    ))}
                </nav>

                {deliveries.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader>
                            <h2 className="leading-none font-semibold">
                                Доставок пока нет
                            </h2>
                            <CardDescription>
                                Здесь появится отправка заявок с опубликованного
                                сайта по настроенным маршрутам формы.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <ol className="flex flex-col gap-4">
                        {deliveries.map((delivery) => (
                            <li
                                key={delivery.public_id}
                                className="min-w-0 rounded-xl border bg-card p-4 shadow-sm"
                            >
                                <article
                                    aria-label={`Доставка «${delivery.route}»`}
                                    className="flex flex-col gap-3"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Send
                                            aria-hidden="true"
                                            className="size-4 text-muted-foreground"
                                        />
                                        <h2 className="font-semibold break-words">
                                            {delivery.route}
                                        </h2>
                                        <DeliveryStatusBadge
                                            status={delivery.status}
                                            label={delivery.status_label}
                                        />
                                        <span className="text-sm text-muted-foreground">
                                            {delivery.destination}
                                        </span>
                                        {can.retry &&
                                            delivery.status === 'failed' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="ml-auto"
                                                    disabled={
                                                        retrying ===
                                                        delivery.public_id
                                                    }
                                                    onClick={() =>
                                                        retry(delivery)
                                                    }
                                                >
                                                    <RotateCcw aria-hidden="true" />
                                                    Повторить
                                                </Button>
                                            )}
                                    </div>
                                    <dl className="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-[minmax(0,12rem)_minmax(0,1fr)]">
                                        <dt className="text-muted-foreground">
                                            Заявка
                                        </dt>
                                        <dd className="min-w-0 break-words">
                                            {`${delivery.form} · ${formatDate(delivery.submission.submitted_at)}`}
                                        </dd>
                                        <dt className="text-muted-foreground">
                                            Попыток
                                        </dt>
                                        <dd>{delivery.attempt_count}</dd>
                                        {delivery.delivered_at && (
                                            <>
                                                <dt className="text-muted-foreground">
                                                    Доставлено
                                                </dt>
                                                <dd>
                                                    {formatDate(
                                                        delivery.delivered_at,
                                                    )}
                                                </dd>
                                            </>
                                        )}
                                        {delivery.next_retry_at && (
                                            <>
                                                <dt className="text-muted-foreground">
                                                    Следующая попытка
                                                </dt>
                                                <dd>
                                                    {formatDate(
                                                        delivery.next_retry_at,
                                                    )}
                                                </dd>
                                            </>
                                        )}
                                        {delivery.error && (
                                            <>
                                                <dt className="text-muted-foreground">
                                                    Ошибка
                                                </dt>
                                                <dd
                                                    className="min-w-0 break-words"
                                                    role="status"
                                                >
                                                    {delivery.http_status
                                                        ? `${delivery.error} (HTTP ${delivery.http_status})`
                                                        : delivery.error}
                                                </dd>
                                            </>
                                        )}
                                    </dl>
                                    {delivery.attempts.length > 0 && (
                                        <details className="border-t pt-3 text-sm">
                                            <summary className="cursor-pointer font-medium">
                                                История попыток
                                            </summary>
                                            <ol className="mt-2 flex flex-col gap-1">
                                                {delivery.attempts.map(
                                                    (attempt) => (
                                                        <li
                                                            key={attempt.number}
                                                            className="flex flex-wrap gap-x-3 text-muted-foreground"
                                                        >
                                                            <span className="text-foreground">
                                                                {`№${attempt.number}${attempt.manual ? ' (вручную)' : ''}`}
                                                            </span>
                                                            <time
                                                                dateTime={
                                                                    attempt.started_at
                                                                }
                                                            >
                                                                {formatDate(
                                                                    attempt.started_at,
                                                                )}
                                                            </time>
                                                            <span>
                                                                {attempt.succeeded
                                                                    ? 'Успешно'
                                                                    : (attempt.error ??
                                                                      'Ошибка')}
                                                            </span>
                                                            {attempt.http_status !==
                                                                null && (
                                                                <span>{`HTTP ${attempt.http_status}`}</span>
                                                            )}
                                                            {attempt.latency_ms !==
                                                                null && (
                                                                <span>{`${attempt.latency_ms} мс`}</span>
                                                            )}
                                                        </li>
                                                    ),
                                                )}
                                            </ol>
                                        </details>
                                    )}
                                </article>
                            </li>
                        ))}
                    </ol>
                )}

                {pagination.last > 1 && (
                    <nav
                        aria-label="Страницы доставок"
                        className="flex items-center justify-between gap-2"
                    >
                        {pagination.prev ? (
                            <Button asChild variant="outline" size="sm">
                                <Link href={pagination.prev}>Назад</Link>
                            </Button>
                        ) : (
                            <span />
                        )}
                        <span className="text-sm text-muted-foreground">
                            {`Страница ${pagination.current} из ${pagination.last}`}
                        </span>
                        {pagination.next ? (
                            <Button asChild variant="outline" size="sm">
                                <Link href={pagination.next}>Вперёд</Link>
                            </Button>
                        ) : (
                            <span />
                        )}
                    </nav>
                )}
            </main>
        </>
    );
}

DeliveriesIndex.layout = {
    breadcrumbs: [{ title: 'Все сайты', href: dashboard() }],
};
