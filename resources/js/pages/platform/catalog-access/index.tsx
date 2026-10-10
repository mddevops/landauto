import { Head, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';

type Plan = { key: string; name: string; active: boolean };
type CatalogItem = {
    kind: 'block' | 'template';
    public_id: string;
    name: string;
    author: string;
    access_mode: string;
    access_label: string;
    plan_keys: string[];
    update_url: string;
};

function ItemAccessCard({ item, plans }: { item: CatalogItem; plans: Plan[] }) {
    const form = useForm<{ plan_keys: string[] }>({
        plan_keys: item.plan_keys,
    });
    const title = item.kind === 'block' ? 'Блок' : 'Шаблон';
    const changed = useMemo(
        () =>
            [...form.data.plan_keys].sort().join('|') !==
            [...item.plan_keys].sort().join('|'),
        [form.data.plan_keys, item.plan_keys],
    );

    return (
        <section className="space-y-4 rounded-xl border bg-card p-4 shadow-sm sm:p-5">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 space-y-1">
                    <h3 className="font-semibold break-words">{item.name}</h3>
                    <p className="text-sm text-muted-foreground">
                        {title} · {item.author}
                    </p>
                </div>
                <Badge variant="outline">{item.access_label}</Badge>
            </div>

            {item.access_mode === 'entitlement' ? (
                <>
                    <p className="text-sm text-muted-foreground">
                        Доступ получат пространства с выбранным активным
                        тарифом. Лицензии каталога продолжают действовать
                        отдельно.
                    </p>
                    <fieldset className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <legend className="mb-2 text-sm font-medium">
                            Входит в тарифы
                        </legend>
                        {plans.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Тарифы ещё не созданы.
                            </p>
                        ) : (
                            plans.map((plan) => (
                                <label
                                    key={plan.key}
                                    className="flex cursor-pointer items-center gap-3 rounded-lg border p-3 text-sm"
                                >
                                    <Checkbox
                                        checked={form.data.plan_keys.includes(
                                            plan.key,
                                        )}
                                        onCheckedChange={(checked) => {
                                            const selected = new Set(
                                                form.data.plan_keys,
                                            );
                                            if (checked) selected.add(plan.key);
                                            else selected.delete(plan.key);
                                            form.setData('plan_keys', [
                                                ...selected,
                                            ]);
                                        }}
                                    />
                                    <span className="min-w-0 flex-1">
                                        {plan.name}
                                        {!plan.active && (
                                            <span className="ml-2 text-muted-foreground">
                                                (неактивен)
                                            </span>
                                        )}
                                    </span>
                                </label>
                            ))
                        )}
                    </fieldset>
                    {form.errors.plan_keys && (
                        <p role="alert" className="text-sm text-destructive">
                            {form.errors.plan_keys}
                        </p>
                    )}
                </>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Для режима «{item.access_label}» список тарифов не
                    ограничивает доступ. Назначения сохраняются, но применяются
                    только в режиме «По тарифу».
                </p>
            )}

            <div className="flex justify-end">
                <Button
                    type="button"
                    disabled={
                        form.processing ||
                        !changed ||
                        item.access_mode !== 'entitlement'
                    }
                    onClick={() =>
                        form.put(item.update_url, { preserveScroll: true })
                    }
                >
                    {form.processing && <Spinner />}
                    Сохранить тарифы
                </Button>
            </div>
        </section>
    );
}

export default function CatalogAccessMatrix({
    plans,
    items,
}: {
    plans: Plan[];
    items: CatalogItem[];
}) {
    const blocks = items.filter((item) => item.kind === 'block');
    const templates = items.filter((item) => item.kind === 'template');

    return (
        <>
            <Head title="Доступ по тарифам" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="space-y-2">
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Доступ по тарифам
                    </h1>
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        Укажите, в какие тарифы входят блоки и шаблоны.
                        Бесплатные материалы доступны всем. Платные лицензии
                        можно будет приобрести на любом тарифе; администратор
                        может выдать лицензию независимо от тарифа.
                    </p>
                </header>

                {blocks.length > 0 && (
                    <section
                        className="space-y-3"
                        aria-labelledby="plan-blocks-title"
                    >
                        <h2
                            id="plan-blocks-title"
                            className="text-lg font-semibold"
                        >
                            Блоки
                        </h2>
                        <div className="grid gap-4 xl:grid-cols-2">
                            {blocks.map((item) => (
                                <ItemAccessCard
                                    key={`block:${item.public_id}`}
                                    item={item}
                                    plans={plans}
                                />
                            ))}
                        </div>
                    </section>
                )}

                {templates.length > 0 && (
                    <section
                        className="space-y-3"
                        aria-labelledby="plan-templates-title"
                    >
                        <h2
                            id="plan-templates-title"
                            className="text-lg font-semibold"
                        >
                            Шаблоны
                        </h2>
                        <div className="grid gap-4 xl:grid-cols-2">
                            {templates.map((item) => (
                                <ItemAccessCard
                                    key={`template:${item.public_id}`}
                                    item={item}
                                    plans={plans}
                                />
                            ))}
                        </div>
                    </section>
                )}

                {items.length === 0 && (
                    <section className="rounded-xl border border-dashed p-8 text-center">
                        <h2 className="font-semibold">
                            В каталоге пока нет опубликованных материалов
                        </h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Опубликованные блоки и шаблоны появятся здесь, когда
                            будут готовы к использованию клиентами.
                        </p>
                    </section>
                )}
            </main>
        </>
    );
}
