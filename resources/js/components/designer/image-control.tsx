import { router, usePage } from '@inertiajs/react';
import { ImageIcon } from 'lucide-react';
import { useState } from 'react';
import type { SchemaField } from '@/blocks/schema';
import { useDesignerAssets } from '@/components/designer/assets-context';
import type { DesignerAsset } from '@/components/designer/types';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { store as storeAsset } from '@/routes/sites/assets';

export function ImageControl({
    field,
    value,
    id,
    error,
    onChange,
}: {
    field: SchemaField;
    value: unknown;
    id: string;
    error?: string;
    onChange: (value: string | null) => void;
}) {
    const { siteId, assets, canUpload } = useDesignerAssets();
    const uploadError = usePage<{ errors: Record<string, string> }>().props
        .errors.file;
    const [open, setOpen] = useState(false);
    const [uploading, setUploading] = useState(false);
    const selected =
        typeof value === 'string'
            ? assets.find((asset) => asset.public_id === value)
            : undefined;

    const upload = (file: File) => {
        router.post(
            storeAsset.url(siteId),
            { file },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                onStart: () => setUploading(true),
                onFinish: () => setUploading(false),
                onSuccess: (page) => {
                    const uploaded = (page.props.assets as DesignerAsset[])[0];

                    if (uploaded) {
                        onChange(uploaded.public_id);
                        setOpen(false);
                    }
                },
            },
        );
    };

    return (
        <fieldset className="grid gap-2" aria-describedby={`${id}-error`}>
            <legend className="mb-1.5 text-sm font-medium">
                {field.label}
            </legend>
            {selected ? (
                <figure className="flex items-center gap-3">
                    <img
                        src={selected.url}
                        alt=""
                        className="size-16 shrink-0 rounded-md border object-cover"
                    />
                    <figcaption className="min-w-0 truncate text-sm">
                        {selected.name}
                    </figcaption>
                </figure>
            ) : (
                <p className="text-sm text-muted-foreground">
                    {typeof value === 'string'
                        ? 'Изображение недоступно.'
                        : 'Изображение не выбрано.'}
                </p>
            )}
            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setOpen(true)}
                >
                    <ImageIcon aria-hidden="true" />
                    {`Выбрать: ${field.label}`}
                </Button>
                {typeof value === 'string' && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => onChange(null)}
                    >
                        Убрать изображение
                    </Button>
                )}
            </div>
            <InputError id={`${id}-error`} message={error} />

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Библиотека изображений</DialogTitle>
                        <DialogDescription>
                            Изображения этого сайта. Поддерживаются JPEG, PNG и
                            WebP до 10 МБ.
                        </DialogDescription>
                    </DialogHeader>
                    {canUpload && (
                        <div className="grid gap-1.5">
                            <Label htmlFor={`${id}-upload`}>
                                Загрузить изображение
                            </Label>
                            <input
                                id={`${id}-upload`}
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                disabled={uploading}
                                aria-invalid={Boolean(uploadError)}
                                aria-describedby={
                                    uploadError
                                        ? `${id}-upload-error`
                                        : undefined
                                }
                                onChange={(event) => {
                                    const file = event.target.files?.[0];
                                    event.target.value = '';

                                    if (file) {
                                        upload(file);
                                    }
                                }}
                                className="text-sm file:mr-3 file:rounded-md file:border file:border-input file:bg-background file:px-3 file:py-1.5 file:text-sm"
                            />
                            <InputError
                                id={`${id}-upload-error`}
                                message={uploadError}
                            />
                        </div>
                    )}
                    {assets.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            В библиотеке пока нет изображений.
                        </p>
                    ) : (
                        <ul className="grid max-h-96 grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-4">
                            {assets.map((asset) => (
                                <li key={asset.public_id}>
                                    <button
                                        type="button"
                                        aria-label={`Выбрать «${asset.name}»`}
                                        aria-pressed={asset.public_id === value}
                                        onClick={() => {
                                            onChange(asset.public_id);
                                            setOpen(false);
                                        }}
                                        className={cn(
                                            'block w-full overflow-hidden rounded-md border outline-none hover:ring-2 hover:ring-primary/40 focus-visible:ring-2 focus-visible:ring-primary',
                                            asset.public_id === value &&
                                                'ring-2 ring-primary',
                                        )}
                                    >
                                        <img
                                            src={asset.url}
                                            alt=""
                                            loading="lazy"
                                            className="aspect-square w-full object-cover"
                                        />
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </DialogContent>
            </Dialog>
        </fieldset>
    );
}
