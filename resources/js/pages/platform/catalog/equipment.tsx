import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import type { Choice } from '@/components/platform/form-fields';
import { NativeSelect } from '@/components/platform/form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/platform/catalog';
import {
    characteristics as saveCharacteristics,
    options as saveOptions,
} from '@/routes/platform/catalog/equipments';

type ChainLink = { public_id: string; name: string };

type CharacteristicGroup = {
    public_id: string;
    name: string;
    items: {
        public_id: string;
        name: string;
        unit: string | null;
        value: string | null;
    }[];
};

type OptionGroup = {
    public_id: string;
    name: string;
    items: { public_id: string; name: string; availability: string }[];
};

type EquipmentProps = {
    equipment: { public_id: string; name: string; status: boolean };
    chain: Record<
        'mark' | 'model' | 'generation' | 'series' | 'modification',
        ChainLink
    >;
    characteristicGroups: CharacteristicGroup[];
    optionGroups: OptionGroup[];
    availabilityChoices: Choice[];
    can: { edit: boolean };
};

function hasItems(groups: { items: unknown[] }[]): boolean {
    return groups.some((group) => group.items.length > 0);
}

export default function CatalogEquipment({
    equipment,
    chain,
    characteristicGroups,
    optionGroups,
    availabilityChoices,
    can,
}: EquipmentProps) {
    const backQuery = {
        mark: chain.mark.public_id,
        model: chain.model.public_id,
        generation: chain.generation.public_id,
        series: chain.series.public_id,
        modification: chain.modification.public_id,
    };

    return (
        <>
            <Head title={`Комплектация ${equipment.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="space-y-2">
                    <p className="text-sm text-muted-foreground">
                        {[
                            chain.mark.name,
                            chain.model.name,
                            chain.generation.name,
                            chain.series.name,
                            chain.modification.name,
                        ].join(' · ')}
                    </p>
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            {`Комплектация ${equipment.name}`}
                        </h1>
                        {!equipment.status && (
                            <Badge variant="secondary">Выключена</Badge>
                        )}
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={index({ query: backQuery })}>
                            Вернуться к каталогу
                        </Link>
                    </Button>
                </header>

                <section
                    aria-labelledby="characteristics-title"
                    className="space-y-4"
                >
                    <h2
                        id="characteristics-title"
                        className="text-xl font-semibold"
                    >
                        Характеристики
                    </h2>
                    {!hasItems(characteristicGroups) ? (
                        <p className="text-sm text-muted-foreground">
                            Словарь характеристик пуст.
                        </p>
                    ) : (
                        <Form
                            {...saveCharacteristics.form(equipment.public_id)}
                            options={{ preserveScroll: true }}
                            disableWhileProcessing
                            className="space-y-6"
                        >
                            {({ processing, errors, recentlySuccessful }) => (
                                <>
                                    <InputError message={errors.values} />
                                    {characteristicGroups.map((group) => (
                                        <fieldset
                                            key={group.public_id}
                                            className="space-y-3 rounded-xl border bg-card p-4"
                                        >
                                            <legend className="px-1 font-medium">
                                                {group.name}
                                            </legend>
                                            {group.items.map((item) => {
                                                const id = `characteristic-${item.public_id}`;

                                                return (
                                                    <div
                                                        key={item.public_id}
                                                        className="grid gap-1.5 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] sm:items-center"
                                                    >
                                                        <label
                                                            htmlFor={id}
                                                            className="text-sm"
                                                        >
                                                            {item.unit
                                                                ? `${item.name}, ${item.unit}`
                                                                : item.name}
                                                        </label>
                                                        <Input
                                                            id={id}
                                                            name={`values[${item.public_id}]`}
                                                            defaultValue={
                                                                item.value ?? ''
                                                            }
                                                            maxLength={1000}
                                                            readOnly={!can.edit}
                                                            autoComplete="off"
                                                        />
                                                    </div>
                                                );
                                            })}
                                        </fieldset>
                                    ))}
                                    {can.edit && (
                                        <div className="flex items-center gap-3">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                Сохранить характеристики
                                            </Button>
                                            {recentlySuccessful && (
                                                <p className="text-sm text-muted-foreground">
                                                    Сохранено
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </>
                            )}
                        </Form>
                    )}
                </section>

                <section aria-labelledby="options-title" className="space-y-4">
                    <h2 id="options-title" className="text-xl font-semibold">
                        Опции
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        «Неизвестно» означает, что данных о наличии опции нет.
                    </p>
                    {!hasItems(optionGroups) ? (
                        <p className="text-sm text-muted-foreground">
                            Словарь опций пуст.
                        </p>
                    ) : (
                        <Form
                            {...saveOptions.form(equipment.public_id)}
                            options={{ preserveScroll: true }}
                            disableWhileProcessing
                            className="space-y-6"
                        >
                            {({ processing, errors, recentlySuccessful }) => (
                                <>
                                    <InputError message={errors.values} />
                                    {optionGroups.map((group) => (
                                        <fieldset
                                            key={group.public_id}
                                            className="space-y-3 rounded-xl border bg-card p-4"
                                        >
                                            <legend className="px-1 font-medium">
                                                {group.name}
                                            </legend>
                                            {group.items.map((item) => {
                                                const id = `option-${item.public_id}`;

                                                return (
                                                    <div
                                                        key={item.public_id}
                                                        className="grid gap-1.5 sm:grid-cols-[minmax(0,1fr)_minmax(0,16rem)] sm:items-center"
                                                    >
                                                        <label
                                                            htmlFor={id}
                                                            className="text-sm"
                                                        >
                                                            {item.name}
                                                        </label>
                                                        <NativeSelect
                                                            id={id}
                                                            name={`values[${item.public_id}]`}
                                                            defaultValue={
                                                                item.availability
                                                            }
                                                            disabled={!can.edit}
                                                        >
                                                            {availabilityChoices.map(
                                                                (choice) => (
                                                                    <option
                                                                        key={
                                                                            choice.value
                                                                        }
                                                                        value={
                                                                            choice.value
                                                                        }
                                                                    >
                                                                        {
                                                                            choice.label
                                                                        }
                                                                    </option>
                                                                ),
                                                            )}
                                                        </NativeSelect>
                                                    </div>
                                                );
                                            })}
                                        </fieldset>
                                    ))}
                                    {can.edit && (
                                        <div className="flex items-center gap-3">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                Сохранить опции
                                            </Button>
                                            {recentlySuccessful && (
                                                <p className="text-sm text-muted-foreground">
                                                    Сохранено
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </>
                            )}
                        </Form>
                    )}
                </section>
            </main>
        </>
    );
}

CatalogEquipment.layout = {
    breadcrumbs: [{ title: 'Каталог автомобилей', href: index() }],
};
