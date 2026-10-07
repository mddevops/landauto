import { useForm } from '@inertiajs/react';
import type { Choice } from '@/components/platform/form-fields';
import { SelectField, TextField } from '@/components/platform/form-fields';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { BlockAccessSettings } from '@/types/blocks';

/** Catalog access (D-079) of a Block or Template: mode, entitlement or price per Site. */
export function CatalogAccessForm({
    idPrefix,
    description,
    access,
    modes,
    entitlements,
    url,
    className,
}: {
    idPrefix: string;
    description: string;
    access: BlockAccessSettings;
    modes: Choice[];
    entitlements: Choice[];
    url: string;
    className?: string;
}) {
    const form = useForm<{
        mode: string;
        entitlement: string;
        price: string;
    }>({
        mode: access.mode,
        entitlement: access.entitlement ?? '',
        price: access.price,
    });

    return (
        <section
            aria-labelledby={`${idPrefix}-title`}
            className={cn(
                'space-y-4 rounded-xl border bg-card p-4 shadow-sm sm:p-6',
                className,
            )}
        >
            <div className="space-y-1">
                <h2 id={`${idPrefix}-title`} className="font-semibold">
                    Доступ в каталоге
                </h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            <form
                className="grid gap-4 sm:grid-cols-3 sm:items-start"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(url, { preserveScroll: true });
                }}
            >
                <SelectField
                    id={`${idPrefix}-mode`}
                    label="Режим доступа"
                    choices={modes}
                    value={form.data.mode}
                    onChange={(event) =>
                        form.setData('mode', event.target.value)
                    }
                    required
                    error={form.errors.mode}
                />
                {form.data.mode === 'entitlement' && (
                    <SelectField
                        id={`${idPrefix}-entitlement`}
                        label="Опция тарифа"
                        choices={entitlements}
                        emptyLabel="Выберите опцию"
                        value={form.data.entitlement}
                        onChange={(event) =>
                            form.setData('entitlement', event.target.value)
                        }
                        required
                        error={form.errors.entitlement}
                    />
                )}
                {form.data.mode === 'paid' && (
                    <TextField
                        id={`${idPrefix}-price`}
                        label="Цена за сайт, ₽"
                        inputMode="decimal"
                        value={form.data.price}
                        onChange={(event) =>
                            form.setData('price', event.target.value)
                        }
                        required
                        maxLength={32}
                        autoComplete="off"
                        hint="Например, 1500 или 1500,50."
                        error={form.errors.price}
                    />
                )}
                <div className="flex justify-end sm:col-span-3">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Сохранить доступ
                    </Button>
                </div>
            </form>
        </section>
    );
}
