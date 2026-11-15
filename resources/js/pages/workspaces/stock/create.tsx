import { Head } from '@inertiajs/react';
import { StockWizard } from '@/components/stock/stock-wizard';
import { dashboard } from '@/routes';
import { index, store } from '@/routes/workspace/stock';
import type { WizardProps } from '@/types/catalog';

export default function CreateStockVehicle(wizard: WizardProps) {
    return (
        <>
            <Head title="Добавить на склад" />
            <main className="flex min-w-0 flex-1 flex-col">
                <header className="border-b px-4 py-4 sm:px-6">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Добавить автомобиль
                    </h1>
                </header>
                <StockWizard
                    wizard={wizard}
                    action={store()}
                    cancelHref={index.url()}
                />
            </main>
        </>
    );
}

CreateStockVehicle.layout = {
    breadcrumbs: [
        { title: 'Все сайты', href: dashboard() },
        { title: 'Склад', href: index() },
        { title: 'Добавить', href: '#' },
    ],
};
