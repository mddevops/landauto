import { Head, router, useForm } from '@inertiajs/react';
import { Copy, ImageUp, Trash2 } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader } from '@/components/ui/card';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { copy, destroy, index, store } from '@/routes/workspace/assets';

type LibraryAsset = {
    public_id: string;
    original_name: string;
    mime_type: string;
    size_bytes: number;
    width: number;
    height: number;
    url: string;
};

type DestinationSite = { public_id: string; name: string };

function formatSize(bytes: number): string {
    return bytes >= 1024 * 1024
        ? `${(bytes / (1024 * 1024)).toFixed(1).replace('.', ',')} МБ`
        : `${Math.max(1, Math.round(bytes / 1024))} КБ`;
}

function Upload() {
    const inputId = useId();
    const input = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | undefined>();

    function upload(file: File) {
        router.post(
            store.url(),
            { file },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setUploading(true),
                onFinish: () => {
                    setUploading(false);

                    if (input.current) {
                        input.current.value = '';
                    }
                },
                onSuccess: () => setError(undefined),
                onError: (errors) => setError(errors.file),
            },
        );
    }

    return (
        <div className="space-y-1">
            <Label htmlFor={inputId} className="sr-only">
                Файл изображения
            </Label>
            <input
                ref={input}
                id={inputId}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="sr-only"
                disabled={uploading}
                onChange={(event) => {
                    const file = event.target.files?.[0];

                    if (file) {
                        upload(file);
                    }
                }}
            />
            <Button
                type="button"
                onClick={() => input.current?.click()}
                disabled={uploading}
            >
                {uploading ? <Spinner /> : <ImageUp aria-hidden="true" />}
                Загрузить изображение
            </Button>
            <p className="text-xs text-muted-foreground">
                JPEG, PNG или WebP до 10 МБ.
            </p>
            <InputError message={error} />
        </div>
    );
}

function CopyToSite({
    asset,
    sites,
}: {
    asset: LibraryAsset;
    sites: DestinationSite[];
}) {
    const [open, setOpen] = useState(false);
    const selectId = useId();
    const form = useForm({ site: sites[0]?.public_id ?? '' });

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    variant="outline"
                    disabled={sites.length === 0}
                >
                    <Copy aria-hidden="true" />
                    На сайт
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(copy.url(asset.public_id), {
                            preserveScroll: true,
                            onSuccess: () => setOpen(false),
                        });
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>Копировать на сайт</DialogTitle>
                        <DialogDescription>
                            На сайт добавится независимая копия изображения.
                            Удаление из медиатеки её не затронет.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor={selectId}>Сайт</Label>
                        <select
                            id={selectId}
                            value={form.data.site}
                            onChange={(event) =>
                                form.setData('site', event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.site)}
                            className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive"
                        >
                            {sites.map((site) => (
                                <option
                                    key={site.public_id}
                                    value={site.public_id}
                                >
                                    {site.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.site} />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Отмена
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            disabled={form.processing || form.data.site === ''}
                        >
                            {form.processing && <Spinner />}
                            Копировать
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteAsset({ asset }: { asset: LibraryAsset }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    variant="ghost"
                    aria-label={`Удалить ${asset.original_name}`}
                >
                    <Trash2 aria-hidden="true" />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Удалить изображение?</DialogTitle>
                    <DialogDescription>
                        Изображение будет удалено из медиатеки. Копии на сайтах
                        и опубликованные версии не изменятся.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Отмена</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        onClick={() =>
                            router.delete(destroy.url(asset.public_id), {
                                preserveScroll: true,
                            })
                        }
                    >
                        Удалить
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function WorkspaceAssets({
    assets,
    total,
    shownLimit,
    sites,
}: {
    assets: LibraryAsset[];
    total: number;
    shownLimit: number;
    sites: DestinationSite[];
}) {
    return (
        <>
            <Head title="Медиатека" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <p className="text-sm text-muted-foreground">
                            Рабочее пространство
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Медиатека
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Общие изображения для всех сайтов пространства. На
                            сайт копируется независимая копия.
                        </p>
                    </div>
                    <Upload />
                </header>

                {assets.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader>
                            <h2 className="leading-none font-semibold">
                                В медиатеке пока нет изображений
                            </h2>
                            <CardDescription>
                                Загрузите логотипы, фотографии салона и другие
                                изображения, которые используются на нескольких
                                сайтах.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <>
                        {total > assets.length && (
                            <p className="text-sm text-muted-foreground">
                                {`Показаны последние ${shownLimit} из ${total} изображений.`}
                            </p>
                        )}
                        {sites.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Нет сайтов, на которые у вас есть право
                                добавлять изображения.
                            </p>
                        )}
                        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {assets.map((asset) => (
                                <li
                                    key={asset.public_id}
                                    className="flex min-w-0 flex-col overflow-hidden rounded-xl border bg-card shadow-sm"
                                >
                                    <div className="flex aspect-[4/3] items-center justify-center bg-muted">
                                        <img
                                            src={asset.url}
                                            alt={asset.original_name}
                                            loading="lazy"
                                            className="size-full object-contain"
                                        />
                                    </div>
                                    <div className="flex min-w-0 flex-1 flex-col gap-2 p-3">
                                        <p
                                            className="truncate text-sm font-medium"
                                            title={asset.original_name}
                                        >
                                            {asset.original_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {`${asset.width} × ${asset.height} · ${formatSize(asset.size_bytes)}`}
                                        </p>
                                        <div className="mt-auto flex items-center justify-between gap-2">
                                            <CopyToSite
                                                asset={asset}
                                                sites={sites}
                                            />
                                            <DeleteAsset asset={asset} />
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </main>
        </>
    );
}

WorkspaceAssets.layout = {
    breadcrumbs: [
        { title: 'Все сайты', href: dashboard() },
        { title: 'Медиатека', href: index() },
    ],
};
