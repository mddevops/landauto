import { Head } from '@inertiajs/react';
import { VehicleWizard } from '@/components/catalog/vehicle-wizard';
import { dashboard } from '@/routes';
import { index, store } from '@/routes/workspace/vehicles';
import type { VehicleCollection, WizardProps } from '@/types/catalog';

type CreateWorkspaceVehicleProps = WizardProps & {
    collections: VehicleCollection[];
    preselectedCollection: string | null;
};

export default function CreateWorkspaceVehicle({
    collections,
    preselectedCollection,
    ...wizard
}: CreateWorkspaceVehicleProps) {
    return (
        <>
            <Head title="Добавить в Каталог" />
            <main className="flex min-w-0 flex-1 flex-col">
                <header className="space-y-1 px-4 pt-4 sm:px-6 sm:pt-6">
                    <p className="text-sm text-muted-foreground">Каталог</p>
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Добавить автомобиль
                    </h1>
                </header>
                <VehicleWizard
                    wizard={wizard}
                    action={store()}
                    cancelHref={index.url()}
                    destination="в ваш Каталог; на сайты его можно скопировать потом"
                    withPrices
                    collections={collections}
                    preselectedCollection={preselectedCollection}
                />
            </main>
        </>
    );
}

CreateWorkspaceVehicle.layout = {
    breadcrumbs: [
        { title: 'Все сайты', href: dashboard() },
        { title: 'Каталог', href: index() },
    ],
};
