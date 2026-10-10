import { useForm } from '@inertiajs/react';
import type { Choice } from '@/components/platform/form-fields';
import { SelectField, TextField } from '@/components/platform/form-fields';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { BlockAccessSettings } from '@/types/blocks';

/** Catalog access (D-121 / X-026): mode or Site / Workspace license prices. */
export function CatalogAccessForm({
    idPrefix,
    description,
    access,
    modes,
    url,
    className,
}: {
    idPrefix: string;
    description: string;
    access: BlockAccessSettings;
    modes: Choice[];
    url: string;
    className?: string;
}) {
    const form = useForm<{
        mode: string;
        site_price: string;
        workspace_price: string;
    }>({
        mode: access.mode,
        site_price: access.site_price,
        workspace_price: access.workspace_price,
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
                    <p className="text-sm text-muted-foreground sm:col-span-2">
                        Состав тарифов настраивается администратором Landflow в
                        разделе «Доступ по тарифам».
                    </p>
                )}
                {form.data.mode === 'paid' && (
                    <>
                        <TextField
                            id={`${idPrefix}-site-price`}
                            label="Лицензия на 1 сайт, ₽"
                            inputMode="decimal"
                            value={form.data.site_price}
                            onChange={(event) =>
                                form.setData('site_price', event.target.value)
                            }
                            maxLength={32}
                            autoComplete="off"
                            hint="Например, 4900. Пусто — не продаётся для одного сайта."
                            error={form.errors.site_price}
                        />
                        <TextField
                            id={`${idPrefix}-workspace-price`}
                            label="Лицензия на всё пространство, ₽"
                            inputMode="decimal"
                            value={form.data.workspace_price}
                            onChange={(event) =>
                                form.setData(
                                    'workspace_price',
                                    event.target.value,
                                )
                            }
                            maxLength={32}
                            autoComplete="off"
                            hint="Например, 14900. Действует на все сайты пространства."
                            error={form.errors.workspace_price}
                        />
                    </>
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
