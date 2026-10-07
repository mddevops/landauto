import { Form, Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import type { PickerLevel } from '@/components/vehicles/series-picker';
import { SeriesPicker } from '@/components/vehicles/series-picker';
import { dashboard } from '@/routes';
import { create, index, store } from '@/routes/sites/vehicles';

type CreateVehicleProps = {
    site: { public_id: string; name: string };
    levels: PickerLevel[];
};

export default function CreateVehicle({ site, levels }: CreateVehicleProps) {
    return (
        <>
            <Head title={`Добавить автомобиль — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="space-y-1">
                    <p className="truncate text-sm text-muted-foreground">
                        {site.name}
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Добавить автомобиль
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Выберите марку, модель, поколение и серию. Предложения с
                        комплектациями и ценами добавляются на следующем шаге.
                    </p>
                </header>

                <SeriesPicker
                    levels={levels}
                    href={(query) => create(site.public_id, { query })}
                    addedLabel="Уже на сайте"
                    renderAdd={(item) => (
                        <Form
                            {...store.form(site.public_id)}
                            disableWhileProcessing
                        >
                            {({ errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="series"
                                        value={item.public_id}
                                    />
                                    <Button
                                        type="submit"
                                        size="sm"
                                        aria-label={`Добавить серию ${item.name}`}
                                    >
                                        <Plus aria-hidden="true" />
                                        Добавить
                                    </Button>
                                    <InputError message={errors.series} />
                                </>
                            )}
                        </Form>
                    )}
                />

                <div>
                    <Button variant="outline" asChild>
                        <Link href={index(site.public_id)}>
                            Вернуться к автомобилям
                        </Link>
                    </Button>
                </div>
            </main>
        </>
    );
}

CreateVehicle.layout = {
    breadcrumbs: [{ title: 'Все сайты', href: dashboard() }],
};
