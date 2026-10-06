import { router } from '@inertiajs/react';
import { ImageOff } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

export type MediaSetChoice = {
    public_id: string;
    name: string;
    swatch_hex: string | null;
    selected: boolean;
    preview_url: string | null;
    images_count: number;
};

export function MediaSelection({
    saveUrl,
    mediaSets,
    canEdit,
}: {
    saveUrl: string;
    mediaSets: MediaSetChoice[];
    canEdit: boolean;
}) {
    const [selected, setSelected] = useState<string[]>(
        mediaSets.filter((set) => set.selected).map((set) => set.public_id),
    );
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | undefined>();
    const [saved, setSaved] = useState(false);

    function toggle(publicId: string) {
        setSaved(false);
        setSelected((current) =>
            current.includes(publicId)
                ? current.filter((id) => id !== publicId)
                : [...current, publicId],
        );
    }

    function save() {
        router.put(
            saveUrl,
            {
                sets: mediaSets
                    .map((set) => set.public_id)
                    .filter((id) => selected.includes(id)),
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setError(undefined);
                    setSaved(true);
                },
                onError: (errors) => setError(errors.sets),
            },
        );
    }

    if (mediaSets.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Для этой серии пока нет подготовленных изображений.
            </p>
        );
    }

    return (
        <div className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {mediaSets.map((set) => (
                    <label
                        key={set.public_id}
                        className={cn(
                            'flex min-w-0 cursor-pointer flex-col gap-2 rounded-xl border bg-card p-3 shadow-sm transition-colors has-[:checked]:border-primary has-[:checked]:ring-2 has-[:checked]:ring-primary/20 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring',
                            !canEdit && 'cursor-default',
                        )}
                    >
                        <div className="flex aspect-[4/3] items-center justify-center overflow-hidden rounded-md bg-muted">
                            {set.preview_url ? (
                                <img
                                    src={set.preview_url}
                                    alt=""
                                    loading="lazy"
                                    className="size-full object-contain"
                                />
                            ) : (
                                <ImageOff
                                    aria-hidden="true"
                                    className="size-6 text-muted-foreground"
                                />
                            )}
                        </div>
                        <span className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                className="size-4 shrink-0 accent-primary"
                                checked={selected.includes(set.public_id)}
                                onChange={() => toggle(set.public_id)}
                                disabled={!canEdit}
                            />
                            {set.swatch_hex && (
                                <span
                                    aria-hidden="true"
                                    className="size-4 shrink-0 rounded-full border"
                                    style={{ backgroundColor: set.swatch_hex }}
                                />
                            )}
                            <span className="min-w-0 flex-1 break-words">
                                {set.name}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {`Ракурсов: ${set.images_count}`}
                            </span>
                        </span>
                    </label>
                ))}
            </div>
            <InputError message={error} />
            {canEdit && (
                <div className="flex items-center gap-3">
                    <Button type="button" onClick={save} disabled={processing}>
                        {processing && <Spinner />}
                        Сохранить выбор
                    </Button>
                    {saved && (
                        <p className="text-sm text-muted-foreground">
                            Сохранено
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}
