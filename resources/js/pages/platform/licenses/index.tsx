import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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

type Scope = 'site' | 'workspace';

type License = {
    public_id: string;
    item: string;
    kind: 'block' | 'template';
    access_label: string;
    scope: Scope;
    scope_label: string;
    target: string;
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

const targetFields = {
    site: {
        label: 'Сайт',
        hint: 'Поддомен сайта на Landflow или ID сайта. Другие сайты пространства лицензию не получат.',
    },
    workspace: {
        label: 'Пространство',
        hint: 'ID пространства или поддомен любого его сайта. Лицензия действует на все текущие и будущие сайты этого пространства.',
    },
} as const;

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

function recipient(license: License): string {
    return license.scope === 'site'
        ? `сайта «${license.target}»`
        : `пространства «${license.target}»`;
}

function grantPayload(data: Record<string, unknown>): Record<string, string> {
    const item = typeof data.item === 'string' ? data.item : '';
    const [kind, publicId = ''] = item.split(':');

    return {
        [kind === 'template' ? 'template' : 'block']: publicId,
        scope: typeof data.scope === 'string' ? data.scope : '',
        target: typeof data.target === 'string' ? data.target : '',
    };
}

function RevokeLicense({ license }: { license: License }) {
    const [open, setOpen] = useState(false);
    const who =
        license.scope === 'site'
            ? `Сайт «${license.target}» больше не сможет`
            : `Сайты пространства «${license.target}» больше не смогут`;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    variant="outline"
                    aria-label={`Отозвать лицензию на «${license.item}» у ${recipient(license)}`}
                >
                    Отозвать
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Отозвать лицензию?</DialogTitle>
                    <DialogDescription>
                        {`${who} заново добавлять ${license.kind === 'template' ? `сайты из шаблона «${license.item}»` : `блок «${license.item}»`} и переходить на его новые версии без своего доступа. Уже установленные версии блоков продолжат работать, опубликованные сайты не изменятся.`}
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
    scopes,
}: {
    licenses: License[];
    items: LicenseItem[];
    scopes: { value: Scope; label: string }[];
}) {
    const [scope, setScope] = useState<Scope>('site');
    const target = targetFields[scope];

    return (
        <>
            <Head title="Лицензии каталога" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Лицензии каталога
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Лицензия даёт право использовать блок или шаблон из
                        каталога, который выдаёт администратор или который
                        платный. Лицензия на один сайт действует только для
                        него. Лицензия на всё пространство действует для всех
                        его текущих и будущих сайтов. Лицензия на шаблон
                        покрывает блоки устанавливаемой версии шаблона. Уже
                        установленные версии блоков продолжают работать после
                        отзыва. Покупка лицензий в Landflow пока недоступна.
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
                            resetOnSuccess={['item', 'target']}
                            className="grid gap-4 sm:grid-cols-2 sm:items-start"
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
                                    <fieldset className="space-y-2">
                                        <legend className="text-sm font-medium">
                                            На что выдаётся
                                        </legend>
                                        <div className="flex flex-wrap gap-x-6 gap-y-2">
                                            {scopes.map((option) => (
                                                <label
                                                    key={option.value}
                                                    className="flex items-center gap-2 text-sm"
                                                >
                                                    <input
                                                        type="radio"
                                                        name="scope"
                                                        value={option.value}
                                                        checked={
                                                            scope ===
                                                            option.value
                                                        }
                                                        onChange={() =>
                                                            setScope(
                                                                option.value,
                                                            )
                                                        }
                                                        className="size-4 accent-primary"
                                                    />
                                                    {option.label}
                                                </label>
                                            ))}
                                        </div>
                                        <InputError message={errors.scope} />
                                    </fieldset>
                                    <TextField
                                        id="license-target"
                                        name="target"
                                        label={target.label}
                                        required
                                        maxLength={63}
                                        autoComplete="off"
                                        hint={target.hint}
                                        error={errors.target}
                                    />
                                    <div className="flex sm:items-end sm:justify-end sm:pt-5.5">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            Выдать лицензию
                                        </Button>
                                    </div>
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
                                        {`${license.scope_label}: ${license.target}`}
                                        {license.scope === 'site' &&
                                            ` · ${license.workspace}`}
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
    breadcrumbs: [{ title: 'Лицензии каталога', href: index() }],
};
