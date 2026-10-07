import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { SelectField, TextField } from '@/components/platform/form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { destroy, index, store } from '@/routes/platform/licenses';
import type { CatalogAccessCard } from '@/types/blocks';

type License = {
    public_id: string;
    item: string;
    kind: 'block' | 'template';
    access_label: string;
    site: string;
    subdomain: string | null;
    workspace: string;
    source_label: string;
    granted_at: string | null;
};

type LicenseItem = {
    kind: 'block' | 'template';
    public_id: string;
    name: string;
    author: string;
    access: CatalogAccessCard;
};

function grantedAt(value: string | null): string {
    return value
        ? new Date(value).toLocaleDateString('ru-RU', {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          })
        : '';
}

const kindLabels = { block: 'Блок', template: 'Шаблон' } as const;

function itemLabel(item: LicenseItem): string {
    return [
        `${kindLabels[item.kind]} «${item.name}»`,
        item.author,
        item.access.label,
        item.access.detail,
    ]
        .filter(Boolean)
        .join(' · ');
}

function grantPayload(data: Record<string, unknown>): Record<string, string> {
    const item = typeof data.item === 'string' ? data.item : '';
    const [kind, publicId = ''] = item.split(':');

    return {
        [kind === 'template' ? 'template' : 'block']: publicId,
        site: typeof data.site === 'string' ? data.site : '',
    };
}

function RevokeLicense({ license }: { license: License }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    variant="outline"
                    aria-label={`Отозвать лицензию на «${license.item}» у сайта «${license.site}»`}
                >
                    Отозвать
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Отозвать лицензию?</DialogTitle>
                    <DialogDescription>
                        {license.kind === 'template'
                            ? `Сайт «${license.site}» потеряет доступ к блокам шаблона «${license.item}», если у них нет своего доступа. Уже добавленные блоки останутся в черновике, но опубликовать сайт с ними будет нельзя.`
                            : `Сайт «${license.site}» потеряет доступ к блоку «${license.item}». Уже добавленные блоки останутся в черновике, но опубликовать сайт с ними будет нельзя.`}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...destroy.form(license.public_id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing }) => (
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button type="button" variant="outline">
                                    Отмена
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Отозвать лицензию
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function PlatformLicenses({
    licenses,
    items,
}: {
    licenses: License[];
    items: LicenseItem[];
}) {
    return (
        <>
            <Head title="Лицензии сайтов" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Лицензии сайтов
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Лицензия даёт одному сайту право использовать платный
                        блок или шаблон либо тот, который выдаёт администратор.
                        Лицензия на шаблон покрывает его блоки для этого сайта.
                        Другому сайту нужна своя лицензия. Покупка лицензий в
                        Landflow пока недоступна.
                    </p>
                </header>

                <section
                    aria-labelledby="grant-heading"
                    className="rounded-xl border bg-card p-4 shadow-sm"
                >
                    <h2 id="grant-heading" className="mb-3 font-semibold">
                        Выдать лицензию
                    </h2>
                    {items.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            В каталоге нет опубликованных блоков и шаблонов, для
                            которых нужна лицензия.
                        </p>
                    ) : (
                        <Form
                            {...store.form()}
                            transform={grantPayload}
                            options={{ preserveScroll: true }}
                            disableWhileProcessing
                            resetOnSuccess
                            className="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-start"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <SelectField
                                        id="license-item"
                                        name="item"
                                        label="Блок или шаблон"
                                        required
                                        emptyLabel="Выберите блок или шаблон"
                                        defaultValue=""
                                        choices={items.map((item) => ({
                                            value: `${item.kind}:${item.public_id}`,
                                            label: itemLabel(item),
                                        }))}
                                        error={errors.block ?? errors.template}
                                    />
                                    <TextField
                                        id="license-site"
                                        name="site"
                                        label="Сайт"
                                        required
                                        maxLength={63}
                                        autoComplete="off"
                                        hint="Поддомен сайта на Landflow или ID сайта."
                                        error={errors.site}
                                    />
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="sm:mt-5.5"
                                    >
                                        {processing && <Spinner />}
                                        Выдать лицензию
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </section>

                {licenses.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Лицензий пока нет.
                    </p>
                ) : (
                    <ul
                        aria-label="Лицензии"
                        className="divide-y rounded-xl border bg-card shadow-sm"
                    >
                        {licenses.map((license) => (
                            <li
                                key={license.public_id}
                                data-testid="license-row"
                                className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center"
                            >
                                <div className="min-w-0 flex-1 space-y-0.5">
                                    <p className="font-medium break-words">
                                        {`${kindLabels[license.kind]} «${license.item}»`}
                                    </p>
                                    <p className="text-sm break-words text-muted-foreground">
                                        {`${license.site} · ${license.workspace}`}
                                        {license.subdomain && (
                                            <>
                                                {' · '}
                                                <code>{license.subdomain}</code>
                                            </>
                                        )}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {`${license.source_label}, ${grantedAt(license.granted_at)}`}
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-3">
                                    <Badge variant="outline">
                                        {license.access_label}
                                    </Badge>
                                    <RevokeLicense license={license} />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </main>
        </>
    );
}

PlatformLicenses.layout = {
    breadcrumbs: [{ title: 'Лицензии сайтов', href: index() }],
};
