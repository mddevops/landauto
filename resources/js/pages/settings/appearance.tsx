import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="Внешний вид" />

            <h1 className="sr-only">Внешний вид</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Внешний вид"
                    description="Настройте оформление интерфейса для своего аккаунта"
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Внешний вид',
            href: editAppearance(),
        },
    ],
};
