import { Form, Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import type { PickerLevel } from '@/components/vehicles/series-picker';
import { SeriesPicker } from '@/components/vehicles/series-picker';
import { dashboard } from '@/routes';
import { create, index, store } from '@/routes/workspace/vehicles';

export default function CreateWorkspaceVehicle({
    levels,
}: {
    levels: PickerLevel[];
}) {
    return (
        <>
            <Head title="Добавить в библиотеку" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="space-y-1">
                    <p className="text-sm text-muted-foreground">
                        Библиотека автомобилей
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Добавить в библиотеку
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Выберите марку, модель, поколение и серию из каталога
                        платформы. Цены задаются отдельно на каждом сайте.
                    </p>
                </header>

                <SeriesPicker
                    levels={levels}
                    href={(query) => create({ query })}
                    addedLabel="Уже в библиотеке"
                    renderAdd={(item) => (
                        <Form {...store.form()} disableWhileProcessing>
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
                        <Link href={index()}>Вернуться к библиотеке</Link>
                    </Button>
                </div>
            </main>
        </>
    );
}

CreateWorkspaceVehicle.layout = {
    breadcrumbs: [
        { title: 'Все сайты', href: dashboard() },
        { title: 'Библиотека автомобилей', href: index() },
    ],
};
