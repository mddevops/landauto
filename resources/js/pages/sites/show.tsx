import { Form, Head, Link } from '@inertiajs/react';
import { ExternalLink, Eye, PencilRuler, Rocket } from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { designer, preview, update } from '@/routes/sites';
import { show as publishing } from '@/routes/sites/publishing';

type SiteOverviewProps = {
    site: {
        public_id: string;
        name: string;
        status: 'active' | 'archived';
        subdomain: string | null;
        address: string | null;
        created_at: string | null;
    };
    production: {
        version_number: number;
        published_at: string | null;
    } | null;
    can: {
        update: boolean;
        publish: boolean;
        preview: boolean;
    };
};

const dateFormat = new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

function formatDate(value: string | null): string {
    return value ? dateFormat.format(new Date(value)) : '—';
}

export default function SiteOverview({
    site,
    production,
    can,
}: SiteOverviewProps) {
    return (
        <>
            <Head title={`Общее — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <p className="text-sm text-muted-foreground">Общее</p>
                        <h1 className="text-2xl font-semibold tracking-tight break-words sm:text-3xl">
                            {site.name}
                        </h1>
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
                        <Button asChild>
                            <Link href={designer(site.public_id)}>
                                <PencilRuler aria-hidden="true" />
                                Открыть дизайнер
                            </Link>
                        </Button>
                    </div>
                </header>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card className="min-w-0">
                        <CardHeader>
                            <CardTitle>Состояние</CardTitle>
                            <CardDescription>
                                Статус сайта и опубликованная версия.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl
                                className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-3 text-sm"
                                data-testid="site-overview"
                            >
                                <dt className="text-muted-foreground">
                                    Статус
                                </dt>
                                <dd>
                                    <Badge
                                        variant={
                                            site.status === 'active'
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {site.status === 'active'
                                            ? 'Активен'
                                            : 'В архиве'}
                                    </Badge>
                                </dd>
                                <dt className="text-muted-foreground">
                                    Публикация
                                </dt>
                                <dd>
                                    {production === null
                                        ? 'Сайт ещё не опубликован'
                                        : `Версия ${production.version_number} · ${formatDate(production.published_at)}`}
                                </dd>
                                <dt className="text-muted-foreground">
                                    Поддомен
                                </dt>
                                <dd className="break-all">
                                    {site.subdomain ?? '—'}
                                </dd>
                                <dt className="text-muted-foreground">
                                    Публичный адрес
                                </dt>
                                <dd className="min-w-0">
                                    {site.address && production !== null ? (
                                        <a
                                            href={site.address}
                                            target="_blank"
                                            rel="noopener"
                                            className="inline-flex max-w-full items-center gap-1 break-all text-primary underline-offset-4 hover:underline"
                                        >
                                            <span className="break-all">
                                                {site.address}
                                            </span>
                                            <ExternalLink
                                                className="size-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                        </a>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            Появится после публикации
                                        </span>
                                    )}
                                </dd>
                            </dl>
                            {can.publish && (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="mt-4"
                                >
                                    <Link href={publishing(site.public_id)}>
                                        <Rocket aria-hidden="true" />
                                        Перейти к публикации
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>

                    {can.update && (
                        <Card className="min-w-0">
                            <CardHeader>
                                <CardTitle>Название сайта</CardTitle>
                                <CardDescription>
                                    Используется в кабинете и в данных сайта
                                    после следующей публикации.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    {...update.form(site.public_id)}
                                    options={{ preserveScroll: true }}
                                    disableWhileProcessing
                                    className="grid gap-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="site-name">
                                                    Название
                                                </Label>
                                                <Input
                                                    id="site-name"
                                                    name="name"
                                                    type="text"
                                                    required
                                                    maxLength={255}
                                                    autoComplete="off"
                                                    defaultValue={site.name}
                                                    aria-invalid={Boolean(
                                                        errors.name,
                                                    )}
                                                    aria-describedby={
                                                        errors.name
                                                            ? 'site-name-error'
                                                            : undefined
                                                    }
                                                />
                                                <InputError
                                                    id="site-name-error"
                                                    message={errors.name}
                                                />
                                            </div>
                                            <div>
                                                <Button
                                                    type="submit"
                                                    disabled={processing}
                                                >
                                                    {processing && <Spinner />}
                                                    Сохранить
                                                </Button>
                                            </div>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </main>
        </>
    );
}

SiteOverview.layout = {
    breadcrumbs: [{ title: 'Все сайты', href: dashboard() }],
};
