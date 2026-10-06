import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { update as updateSeo } from '@/routes/sites/pages/seo';

type SeoPage = {
    public_id: string;
    title: string;
    path: string;
    seo_title: string | null;
    seo_description: string | null;
    seo_noindex: boolean;
};

type SeoProps = {
    site: { public_id: string; name: string };
    pages: SeoPage[];
    addresses: {
        primary: string | null;
        sitemap: string | null;
        robots: string | null;
    };
    published: boolean;
    limits: { title: number; description: number };
    can: { editIndexing: boolean };
};

function PageSeoCard({
    site,
    page,
    limits,
    canEditIndexing,
}: {
    site: SeoProps['site'];
    page: SeoPage;
    limits: SeoProps['limits'];
    canEditIndexing: boolean;
}) {
    const form = useForm({
        seo_title: page.seo_title ?? '',
        seo_description: page.seo_description ?? '',
        seo_noindex: page.seo_noindex,
        return: 'seo',
    });
    const id = page.public_id;

    function save(event: FormEvent) {
        event.preventDefault();
        form.transform((data) =>
            canEditIndexing
                ? data
                : {
                      seo_title: data.seo_title,
                      seo_description: data.seo_description,
                      return: data.return,
                  },
        );
        form.submit(updateSeo({ site: site.public_id, page: id }), {
            preserveScroll: true,
        });
    }

    return (
        <Card className="min-w-0" data-testid="seo-page">
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="min-w-0 space-y-1">
                        <CardTitle className="break-words">
                            {page.title}
                        </CardTitle>
                        <CardDescription className="break-all">
                            {page.path}
                        </CardDescription>
                    </div>
                    {page.seo_noindex && (
                        <Badge variant="outline">Скрыта от поисковиков</Badge>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                <form onSubmit={save} className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor={`seo-title-${id}`}>
                            Заголовок для поисковиков
                        </Label>
                        <Input
                            id={`seo-title-${id}`}
                            value={form.data.seo_title}
                            onChange={(event) =>
                                form.setData('seo_title', event.target.value)
                            }
                            maxLength={limits.title}
                            placeholder={page.title}
                            aria-invalid={Boolean(form.errors.seo_title)}
                            aria-describedby={`seo-title-help-${id}`}
                        />
                        <p
                            id={`seo-title-help-${id}`}
                            className="text-xs text-muted-foreground"
                        >
                            {`Если оставить пустым, используется название страницы. ${form.data.seo_title.length} из ${limits.title} символов.`}
                        </p>
                        <InputError message={form.errors.seo_title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`seo-description-${id}`}>
                            Описание для поисковиков
                        </Label>
                        <textarea
                            id={`seo-description-${id}`}
                            value={form.data.seo_description}
                            onChange={(event) =>
                                form.setData(
                                    'seo_description',
                                    event.target.value,
                                )
                            }
                            maxLength={limits.description}
                            rows={3}
                            aria-invalid={Boolean(form.errors.seo_description)}
                            aria-describedby={`seo-description-help-${id}`}
                            className="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive"
                        />
                        <p
                            id={`seo-description-help-${id}`}
                            className="text-xs text-muted-foreground"
                        >
                            {`${form.data.seo_description.length} из ${limits.description} символов.`}
                        </p>
                        <InputError message={form.errors.seo_description} />
                    </div>
                    {canEditIndexing && (
                        <div className="flex items-start gap-2">
                            <Checkbox
                                id={`seo-noindex-${id}`}
                                checked={form.data.seo_noindex}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'seo_noindex',
                                        checked === true,
                                    )
                                }
                            />
                            <Label
                                htmlFor={`seo-noindex-${id}`}
                                className="leading-snug"
                            >
                                Скрыть страницу от поисковых систем
                            </Label>
                        </div>
                    )}
                    <div>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            aria-label={`Сохранить SEO страницы «${page.title}»`}
                        >
                            {form.processing && <Spinner />}
                            Сохранить
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function AddressRow({ label, url }: { label: string; url: string }) {
    return (
        <div className="grid min-w-0 gap-1 text-sm sm:grid-cols-[10rem_minmax(0,1fr)] sm:gap-3">
            <span className="text-muted-foreground">{label}</span>
            <a
                href={url}
                target="_blank"
                rel="noopener noreferrer"
                className="break-all underline underline-offset-4"
            >
                {url}
            </a>
        </div>
    );
}

export default function Seo({
    site,
    pages,
    addresses,
    published,
    limits,
    can,
}: SeoProps) {
    return (
        <>
            <Head title={`SEO — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="min-w-0 space-y-1">
                    <p className="truncate text-sm text-muted-foreground">
                        {site.name}
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        SEO
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Заголовки и описания страниц для поисковых систем.
                        Изменения появятся на сайте после публикации.
                    </p>
                </header>

                <Card className="min-w-0">
                    <CardHeader>
                        <CardTitle>Адреса для поисковых систем</CardTitle>
                        <CardDescription>
                            Канонические ссылки и карта сайта всегда указывают
                            на основной адрес сайта.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {addresses.primary && (
                            <AddressRow
                                label="Основной адрес"
                                url={addresses.primary}
                            />
                        )}
                        {published && addresses.sitemap && addresses.robots ? (
                            <>
                                <AddressRow
                                    label="Карта сайта"
                                    url={addresses.sitemap}
                                />
                                <AddressRow
                                    label="robots.txt"
                                    url={addresses.robots}
                                />
                            </>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Карта сайта и robots.txt появятся после первой
                                публикации.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <section aria-label="SEO страниц" className="grid gap-4">
                    {pages.map((page) => (
                        <PageSeoCard
                            key={page.public_id}
                            site={site}
                            page={page}
                            limits={limits}
                            canEditIndexing={can.editIndexing}
                        />
                    ))}
                </section>
            </main>
        </>
    );
}

Seo.layout = {
    breadcrumbs: [{ title: 'Все сайты', href: dashboard() }],
};
