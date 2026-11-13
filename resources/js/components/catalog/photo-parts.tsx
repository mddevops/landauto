import { useForm } from '@inertiajs/react';
import { ImagePlus, Trash2 } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Field, TextField } from '@/components/platform/form-fields';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { RouteDefinition } from '@/wayfinder';

export const IMAGE_ACCEPT = 'image/jpeg,image/png,image/webp';

export function Swatch({
    hex,
    className,
}: {
    hex: string | null;
    className?: string;
}) {
    return (
        <span
            aria-hidden="true"
            className={cn(
                'inline-block size-4 shrink-0 rounded-full border shadow-xs',
                !hex &&
                    'bg-[conic-gradient(var(--color-muted)_0_25%,transparent_0_50%,var(--color-muted)_0_75%,transparent_0)] bg-[length:8px_8px]',
                className,
            )}
            style={hex ? { backgroundColor: hex } : undefined}
        />
    );
}

/**
 * One angle cell: the photo with a remove button, or an upload button when empty.
 */
export function AngleSlot({
    label,
    url,
    onUpload,
    onRemove,
    uploading = false,
    highlighted = false,
    disabled = false,
}: {
    label: string;
    url: string | null;
    onUpload?: (file: File) => void;
    onRemove?: () => void;
    uploading?: boolean;
    highlighted?: boolean;
    disabled?: boolean;
}) {
    const input = useRef<HTMLInputElement>(null);

    return (
        <figure className="flex min-w-0 flex-col gap-1.5">
            <div
                className={cn(
                    'group relative aspect-[4/3] overflow-hidden rounded-lg border bg-muted/40',
                    highlighted && 'ring-2 ring-primary',
                )}
            >
                {url ? (
                    <>
                        <img
                            src={url}
                            alt={label}
                            loading="lazy"
                            className="size-full object-contain"
                        />
                        {onRemove && !disabled && (
                            <Button
                                type="button"
                                size="icon"
                                variant="secondary"
                                className="absolute top-1.5 right-1.5 size-7 opacity-90 shadow-sm sm:opacity-0 sm:group-focus-within:opacity-100 sm:group-hover:opacity-100"
                                aria-label={`Удалить фото: ${label}`}
                                onClick={onRemove}
                            >
                                <Trash2 aria-hidden="true" />
                            </Button>
                        )}
                    </>
                ) : onUpload && !disabled ? (
                    <button
                        type="button"
                        className="flex size-full flex-col items-center justify-center gap-1 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        onClick={() => input.current?.click()}
                        aria-label={`Загрузить фото: ${label}`}
                        disabled={uploading}
                    >
                        {uploading ? (
                            <Spinner />
                        ) : (
                            <ImagePlus className="size-5" aria-hidden="true" />
                        )}
                        Загрузить
                    </button>
                ) : (
                    <span className="flex size-full items-center justify-center text-xs text-muted-foreground">
                        Нет фото
                    </span>
                )}
            </div>
            <figcaption className="truncate text-center text-xs text-muted-foreground">
                {label}
            </figcaption>
            {onUpload && (
                <input
                    ref={input}
                    type="file"
                    accept={IMAGE_ACCEPT}
                    className="sr-only"
                    tabIndex={-1}
                    aria-hidden="true"
                    onChange={(event) => {
                        const file = event.target.files?.[0];
                        event.target.value = '';

                        if (file) {
                            onUpload(file);
                        }
                    }}
                />
            )}
        </figure>
    );
}

/**
 * Button that opens a multi-file picker for gallery uploads.
 */
export function UploadButton({
    onFiles,
    uploading,
    children,
    multiple = true,
    variant = 'outline',
}: {
    onFiles: (files: File[]) => void;
    uploading: boolean;
    children: ReactNode;
    multiple?: boolean;
    variant?: 'outline' | 'default' | 'secondary';
}) {
    const input = useRef<HTMLInputElement>(null);

    return (
        <>
            <Button
                type="button"
                variant={variant}
                size="sm"
                disabled={uploading}
                onClick={() => input.current?.click()}
            >
                {uploading ? <Spinner /> : <ImagePlus aria-hidden="true" />}
                {children}
            </Button>
            <input
                ref={input}
                type="file"
                accept={IMAGE_ACCEPT}
                multiple={multiple}
                className="sr-only"
                tabIndex={-1}
                aria-hidden="true"
                onChange={(event) => {
                    const files = Array.from(event.target.files ?? []);
                    event.target.value = '';

                    if (files.length > 0) {
                        onFiles(files);
                    }
                }}
            />
        </>
    );
}

/**
 * Create / edit a colour: name and swatch, optionally status and order (platform library).
 */
export function ColorDialog({
    trigger,
    title,
    action,
    color,
    withStatus = false,
    sortOrder,
}: {
    trigger: ReactNode;
    title: string;
    action: RouteDefinition<'post' | 'patch'>;
    color?: {
        name: string;
        swatch_hex: string | null;
        status?: boolean;
        sort_order?: number;
    };
    withStatus?: boolean;
    sortOrder?: number;
}) {
    const id = useId();
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: color?.name ?? '',
        swatch_hex: color?.swatch_hex ?? '',
        status: color?.status ?? true,
        sort_order: String(color?.sort_order ?? sortOrder ?? 0),
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            name: data.name,
            swatch_hex: data.swatch_hex,
            ...(withStatus
                ? { status: data.status ? 1 : 0, sort_order: data.sort_order }
                : {}),
        }));
        form.submit(action, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);

                if (!color) {
                    form.reset();
                }
            },
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);

                if (!value) {
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        Название видят посетители сайта, например «Белый
                        перламутр».
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <TextField
                        id={`${id}-name`}
                        label="Название цвета"
                        maxLength={100}
                        autoComplete="off"
                        required
                        autoFocus
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        error={form.errors.name}
                    />
                    <Field
                        id={`${id}-hex`}
                        label="Образец цвета"
                        hint="Кружок рядом с названием. Можно оставить пустым."
                        error={form.errors.swatch_hex}
                    >
                        <div className="flex items-center gap-2">
                            <input
                                type="color"
                                aria-label="Выбрать цвет на палитре"
                                value={
                                    /^#[0-9a-f]{6}$/i.test(form.data.swatch_hex)
                                        ? form.data.swatch_hex
                                        : '#ffffff'
                                }
                                onChange={(event) =>
                                    form.setData(
                                        'swatch_hex',
                                        event.target.value,
                                    )
                                }
                                className="h-9 w-12 shrink-0 cursor-pointer rounded-md border bg-transparent p-1"
                            />
                            <Input
                                id={`${id}-hex`}
                                placeholder="#ffffff"
                                maxLength={7}
                                autoComplete="off"
                                value={form.data.swatch_hex}
                                aria-invalid={Boolean(form.errors.swatch_hex)}
                                onChange={(event) =>
                                    form.setData(
                                        'swatch_hex',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                    </Field>
                    {withStatus && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="flex items-center gap-2 pt-6">
                                <Checkbox
                                    id={`${id}-status`}
                                    checked={form.data.status}
                                    onCheckedChange={(value) =>
                                        form.setData('status', value === true)
                                    }
                                />
                                <Label htmlFor={`${id}-status`}>
                                    Доступен клиентам
                                </Label>
                            </div>
                            <TextField
                                id={`${id}-sort`}
                                label="Порядок"
                                type="number"
                                min={0}
                                value={form.data.sort_order}
                                onChange={(event) =>
                                    form.setData(
                                        'sort_order',
                                        event.target.value,
                                    )
                                }
                                error={form.errors.sort_order}
                            />
                        </div>
                    )}
                    <InputError
                        message={(form.errors as Record<string, string>).status}
                    />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Отмена
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Сохранить
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Confirmation dialog for destructive actions.
 */
export function ConfirmDialog({
    trigger,
    title,
    description,
    confirmLabel = 'Удалить',
    onConfirm,
}: {
    trigger: ReactNode;
    title: string;
    description: string;
    confirmLabel?: string;
    onConfirm: () => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            Отмена
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={() => {
                            setOpen(false);
                            onConfirm();
                        }}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
