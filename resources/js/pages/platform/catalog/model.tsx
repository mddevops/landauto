import { Head } from '@inertiajs/react';
import { GenerationList } from '@/components/catalog/catalog-grids';
import {
    index,
    mark as markRoute,
    model as modelRoute,
    series,
} from '@/routes/platform/catalog';
import type { CatalogGeneration, CatalogMark } from '@/types/catalog';

type ModelPageProps = {
    mark: CatalogMark;
    model: {
        url: string;
        name: string;
        years: string | null;
        class: string | null;
    };
    generations: CatalogGeneration[];
};

export default function CatalogModelPage({
    mark,
    model,
    generations,
}: ModelPageProps) {
    const seriesCount = generations.reduce(
        (total, generation) => total + generation.series.length,
        0,
    );

    return (
        <>
            <Head title={`${mark.name} ${model.name} — каталог`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        {mark.name} {model.name}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {[
                            model.years,
                            model.class ? `класс ${model.class}` : null,
                            `поколений: ${generations.length}`,
                            `кузовов: ${seriesCount}`,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                </header>
                <GenerationList
                    generations={generations}
                    showMedia
                    href={(item) => series.url(item.ref)}
                />
            </main>
        </>
    );
}

CatalogModelPage.layout = (props: ModelPageProps) => ({
    breadcrumbs: [
        { title: 'Каталог автомобилей', href: index() },
        { title: props.mark.name, href: markRoute(props.mark.url) },
        {
            title: props.model.name,
            href: modelRoute({ mark: props.mark.url, model: props.model.url }),
        },
    ],
});
