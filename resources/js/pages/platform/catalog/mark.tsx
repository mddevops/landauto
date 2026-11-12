import { Head } from '@inertiajs/react';
import { CatalogImage, ModelGrid } from '@/components/catalog/catalog-grids';
import { index, mark as markRoute, model } from '@/routes/platform/catalog';
import type { CatalogMark, CatalogModel } from '@/types/catalog';

type MarkPageProps = {
    mark: CatalogMark & { country: string | null };
    models: CatalogModel[];
};

export default function CatalogMarkPage({ mark, models }: MarkPageProps) {
    return (
        <>
            <Head title={`${mark.name} — каталог`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex items-center gap-4">
                    <CatalogImage
                        src={mark.logo}
                        alt=""
                        fallback="logo"
                        className="size-14 shrink-0 object-contain"
                    />
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            {mark.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {[
                                mark.name_ru,
                                mark.country,
                                `моделей: ${models.length}`,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                    </div>
                </header>
                <ModelGrid
                    models={models}
                    href={(item) =>
                        model.url({ mark: mark.url, model: item.url })
                    }
                />
            </main>
        </>
    );
}

CatalogMarkPage.layout = (props: MarkPageProps) => ({
    breadcrumbs: [
        { title: 'Каталог автомобилей', href: index() },
        { title: props.mark.name, href: markRoute(props.mark.url) },
    ],
});
