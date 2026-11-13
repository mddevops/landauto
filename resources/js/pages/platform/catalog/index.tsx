import { Head } from '@inertiajs/react';
import { MarkGrid } from '@/components/catalog/catalog-grids';
import { index, mark } from '@/routes/platform/catalog';
import type { CatalogMark } from '@/types/catalog';

export default function CatalogIndex({ marks }: { marks: CatalogMark[] }) {
    return (
        <>
            <Head title="Каталог автомобилей" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Каталог автомобилей
                    </h1>
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        Каталог auto.ru: марки, модели, поколения, кузова,
                        модификации и комплектации. Данные обновляет команда
                        каталога, здесь — просмотр и медиатека фото серий.
                    </p>
                </header>
                <MarkGrid marks={marks} href={(item) => mark.url(item.url)} />
            </main>
        </>
    );
}

CatalogIndex.layout = {
    breadcrumbs: [{ title: 'Каталог автомобилей', href: index() }],
};
