import { Head, Link } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { designer } from '@/routes/sites';
import { index as submissionsIndex } from '@/routes/sites/submissions';

type SubmittedValue = {
    key: string;
    type: string;
    label: string;
    value: string | boolean | null;
};

type TrustedContext = {
    page?: { title: string };
    block?: { name: string };
    popup?: { name: string };
    vehicle?: { title: string; series: string };
    offer?: { modification: string; equipment: string; price_label: string };
    media_set?: { name: string };
};

type SubmissionMode = 'public' | 'preview';

type SubmissionRow = {
    public_id: string;
    form: { public_id: string; name: string };
    status: string;
    mode: SubmissionMode;
    mode_label: string;
    submitted_at: string;
    phone_normalized: string | null;
    values: SubmittedValue[];
    context: { trusted: TrustedContext; visitor: Record<string, string> };
};

const visitorLabels: Record<string, string> = {
    page_url: 'Адрес страницы',
    referrer: 'Источник перехода',
    utm_source: 'utm_source',
    utm_medium: 'utm_medium',
    utm_campaign: 'utm_campaign',
    utm_content: 'utm_content',
    utm_term: 'utm_term',
};

function contextRows({ trusted, visitor }: SubmissionRow['context']) {
    const rows: { label: string; value: string }[] = [];

    if (trusted.vehicle) {
        rows.push({
            label: 'Автомобиль',
            value: `${trusted.vehicle.title}, ${trusted.vehicle.series}`,
        });
    }

    if (trusted.offer) {
        rows.push({
            label: 'Комплектация',
            value: `${trusted.offer.modification} · ${trusted.offer.equipment}`,
        });
        rows.push({ label: 'Цена на сайте', value: trusted.offer.price_label });
    }

    if (trusted.media_set) {
        rows.push({ label: 'Цвет', value: trusted.media_set.name });
    }

    if (trusted.page) {
        rows.push({ label: 'Страница', value: trusted.page.title });
    }

    if (trusted.block) {
        rows.push({ label: 'Блок', value: trusted.block.name });
    }

    if (trusted.popup) {
        rows.push({ label: 'Попап', value: trusted.popup.name });
    }

    for (const [key, value] of Object.entries(visitor)) {
        rows.push({ label: visitorLabels[key] ?? key, value });
    }

    return rows;
}

type SubmissionsIndexProps = {
    site: { public_id: string; name: string };
    mode: SubmissionMode;
    previewCount: number;
    submissions: SubmissionRow[];
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

function displayValue(item: SubmittedValue): string {
    if (typeof item.value === 'boolean') {
        return item.value ? 'Да' : 'Нет';
    }

    return item.value ?? '—';
}

export default function SubmissionsIndex({
    site,
    mode,
    previewCount,
    submissions,
    pagination,
}: SubmissionsIndexProps) {
    const isPreview = mode === 'preview';

    return (
        <>
            <Head title={`Заявки — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <p className="truncate text-sm text-muted-foreground">
                            {site.name}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            {isPreview ? 'Тестовые заявки' : 'Заявки'}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {`Всего: ${pagination.total}`}
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={designer(site.public_id)}>
                            Открыть дизайнер
                        </Link>
                    </Button>
                </header>

                <nav aria-label="Тип заявок" className="flex flex-wrap gap-2">
                    <Button
                        asChild
                        size="sm"
                        variant={isPreview ? 'outline' : 'default'}
                    >
                        <Link
                            href={submissionsIndex(site.public_id)}
                            aria-current={isPreview ? undefined : 'page'}
                        >
                            С сайта
                        </Link>
                    </Button>
                    <Button
                        asChild
                        size="sm"
                        variant={isPreview ? 'default' : 'outline'}
                    >
                        <Link
                            href={submissionsIndex(site.public_id, {
                                query: { mode: 'preview' },
                            })}
                            aria-current={isPreview ? 'page' : undefined}
                        >
                            {`Тестовые из предпросмотра (${previewCount})`}
                        </Link>
                    </Button>
                </nav>

                {isPreview && (
                    <p className="text-sm text-muted-foreground">
                        Тестовые заявки отправлены из предпросмотра черновика.
                        Они не считаются заявками клиентов и никуда не
                        передаются.
                    </p>
                )}

                {submissions.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader>
                            <h2 className="leading-none font-semibold">
                                {isPreview
                                    ? 'Тестовых заявок нет'
                                    : 'Заявок пока нет'}
                            </h2>
                            <CardDescription>
                                {isPreview
                                    ? 'Здесь появятся заявки, отправленные из предпросмотра.'
                                    : 'Здесь появятся заявки, отправленные через формы опубликованного сайта.'}
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <ol className="flex flex-col gap-4">
                        {submissions.map((submission) => (
                            <li
                                key={submission.public_id}
                                className="min-w-0 rounded-xl border bg-card p-4 shadow-sm"
                            >
                                <article
                                    aria-label={`Заявка от ${dateFormat.format(new Date(submission.submitted_at))}`}
                                    className="flex flex-col gap-3"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Inbox
                                            aria-hidden="true"
                                            className="size-4 text-muted-foreground"
                                        />
                                        <h2 className="font-semibold break-words">
                                            {submission.form.name}
                                        </h2>
                                        <Badge variant="secondary">
                                            {submission.status}
                                        </Badge>
                                        {submission.mode === 'preview' && (
                                            <Badge variant="outline">
                                                {submission.mode_label}
                                            </Badge>
                                        )}
                                        <time
                                            dateTime={submission.submitted_at}
                                            className="text-sm text-muted-foreground"
                                        >
                                            {dateFormat.format(
                                                new Date(
                                                    submission.submitted_at,
                                                ),
                                            )}
                                        </time>
                                    </div>
                                    <dl className="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-[minmax(0,12rem)_minmax(0,1fr)]">
                                        {submission.values.map((item) => (
                                            <div
                                                key={item.key}
                                                className="contents"
                                            >
                                                <dt className="truncate text-muted-foreground">
                                                    {item.type === 'consent'
                                                        ? 'Согласие'
                                                        : item.label}
                                                </dt>
                                                <dd className="min-w-0 break-words">
                                                    {displayValue(item)}
                                                    {item.type === 'hidden' && (
                                                        <span className="text-muted-foreground">
                                                            {
                                                                ' (скрытое поле, заполняется браузером)'
                                                            }
                                                        </span>
                                                    )}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                    {contextRows(submission.context).length >
                                        0 && (
                                        <section
                                            aria-label="Контекст заявки"
                                            className="border-t pt-3"
                                        >
                                            <h3 className="mb-2 text-sm font-medium">
                                                Контекст
                                            </h3>
                                            <dl className="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-[minmax(0,12rem)_minmax(0,1fr)]">
                                                {contextRows(
                                                    submission.context,
                                                ).map((row) => (
                                                    <div
                                                        key={row.label}
                                                        className="contents"
                                                    >
                                                        <dt className="truncate text-muted-foreground">
                                                            {row.label}
                                                        </dt>
                                                        <dd className="min-w-0 break-all">
                                                            {row.value}
                                                        </dd>
                                                    </div>
                                                ))}
                                            </dl>
                                        </section>
                                    )}
                                </article>
                            </li>
                        ))}
                    </ol>
                )}

                {pagination.last > 1 && (
                    <nav
                        aria-label="Страницы заявок"
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

SubmissionsIndex.layout = {
    breadcrumbs: [{ title: 'Панель управления', href: dashboard() }],
};
