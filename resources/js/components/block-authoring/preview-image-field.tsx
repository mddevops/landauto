import { ImagePlus, X } from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import type { DragEvent } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const ACCEPTED = ['image/png', 'image/jpeg', 'image/webp'];
const MAX_BYTES = 10 * 1024 * 1024;

/** The first supported image of a paste or drop, named so the server keeps a sensible extension. */
function imageFrom(items: DataTransferItemList | null): File | null {
    for (const item of Array.from(items ?? [])) {
        if (item.kind === 'file' && ACCEPTED.includes(item.type)) {
            const file = item.getAsFile();

            if (file) {
                return file.name
                    ? file
                    : new File([file], `preview.${item.type.split('/')[1]}`, {
                          type: item.type,
                      });
            }
        }
    }

    return null;
}

/**
 * Block version preview picker: choose a file, drop it, or paste a screenshot with Ctrl+V anywhere
 * while the field is shown.
 */
export function PreviewImageField({
    value,
    onChange,
    currentUrl = null,
    error,
}: {
    value: File | null;
    onChange: (file: File | null) => void;
    currentUrl?: string | null;
    error?: string;
}) {
    const inputId = useId();
    const hintId = `${inputId}-hint`;
    const errorId = `${inputId}-error`;
    const inputRef = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);
    const objectUrl = useMemo(
        () => (value ? URL.createObjectURL(value) : null),
        [value],
    );

    useEffect(
        () => () => {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
            }
        },
        [objectUrl],
    );

    const accept = (file: File | null) => {
        if (!file) {
            setLocalError(
                'Перетащите или вставьте изображение JPEG, PNG или WebP.',
            );

            return;
        }

        if (!ACCEPTED.includes(file.type)) {
            setLocalError(
                'Поддерживаются только изображения JPEG, PNG и WebP.',
            );

            return;
        }

        if (file.size > MAX_BYTES) {
            setLocalError('Размер файла не должен превышать 10 МБ.');

            return;
        }

        setLocalError(null);
        onChange(file);
    };

    useEffect(() => {
        const onPaste = (event: ClipboardEvent) => {
            const file = imageFrom(event.clipboardData?.items ?? null);

            if (file) {
                event.preventDefault();
                accept(file);
            }
        };

        window.addEventListener('paste', onPaste);

        return () => window.removeEventListener('paste', onPaste);
    });

    const onDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setDragging(false);
        accept(imageFrom(event.dataTransfer.items));
    };

    const shown = objectUrl ?? currentUrl;
    const message = localError ?? error;

    return (
        <div className="grid gap-2">
            <label htmlFor={inputId} className="text-sm font-medium">
                Превью версии
            </label>
            <div
                onDragOver={(event) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={onDrop}
                className={cn(
                    'flex flex-col items-center gap-3 rounded-lg border border-dashed p-4 text-center transition-colors',
                    dragging && 'border-primary bg-primary/5',
                    message && 'border-destructive',
                )}
            >
                {shown ? (
                    <img
                        src={shown}
                        alt="Превью версии"
                        className="max-h-48 w-full rounded-md border object-contain"
                    />
                ) : (
                    <ImagePlus
                        aria-hidden="true"
                        className="size-8 text-muted-foreground"
                    />
                )}
                <p id={hintId} className="text-sm text-muted-foreground">
                    Вставьте скриншот из буфера обмена (Ctrl+V), перетащите файл
                    сюда или выберите его. JPEG, PNG или WebP до 10 МБ.
                </p>
                <div className="flex flex-wrap justify-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => inputRef.current?.click()}
                    >
                        Выбрать файл
                    </Button>
                    {value && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setLocalError(null);
                                onChange(null);
                            }}
                        >
                            <X aria-hidden="true" />
                            Убрать
                        </Button>
                    )}
                </div>
                <input
                    ref={inputRef}
                    id={inputId}
                    type="file"
                    accept={ACCEPTED.join(',')}
                    aria-describedby={message ? `${hintId} ${errorId}` : hintId}
                    aria-invalid={Boolean(message)}
                    className="sr-only"
                    onChange={(event) => {
                        accept(event.target.files?.[0] ?? null);
                        event.target.value = '';
                    }}
                />
            </div>
            {message && (
                <p id={errorId} className="text-sm text-destructive">
                    {message}
                </p>
            )}
        </div>
    );
}
