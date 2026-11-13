import { Head } from '@inertiajs/react';
import { VehicleWizard } from '@/components/catalog/vehicle-wizard';
import { dashboard } from '@/routes';
import { index, store } from '@/routes/sites/vehicles';
import type { WizardProps } from '@/types/catalog';

type CreateVehicleProps = WizardProps & {
    site: { public_id: string; name: string };
    taken: string[];
    can: { editPrices: boolean };
};

export default function CreateVehicle({
    site,
    taken,
    can,
    ...wizard
}: CreateVehicleProps) {
    return (
        <>
            <Head title={`Добавить автомобиль — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col">
                <header className="space-y-1 px-4 pt-4 sm:px-6 sm:pt-6">
                    <p className="truncate text-sm text-muted-foreground">
                        {site.name}
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Добавить автомобиль
                    </h1>
                </header>
                <VehicleWizard
                    wizard={wizard}
                    action={store(site.public_id)}
                    cancelHref={index.url(site.public_id)}
                    destination={`на сайт «${site.name}»`}
                    withPrices={can.editPrices}
                    taken={taken}
                />
            </main>
        </>
    );
}

CreateVehicle.layout = {
    breadcrumbs: [{ title: 'Все сайты', href: dashboard() }],
};
