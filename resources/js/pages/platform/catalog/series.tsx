import { Head } from '@inertiajs/react';
import { CatalogImage } from '@/components/catalog/catalog-grids';
import { SeriesMediaManager } from '@/components/catalog/series-media-manager';
import { TrimsExplorer } from '@/components/catalog/trims-explorer';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    index,
    mark as markRoute,
    model as modelRoute,
    series as seriesRoute,
} from '@/routes/platform/catalog';
import type {
    AngleOption,
    CatalogSeries,
    CharacteristicSection,
    OptionSection,
    SeriesLibrary,
    Trim,
} from '@/types/catalog';

type SeriesPageProps = {
    series: CatalogSeries;
    library: SeriesLibrary;
    trims: Trim[];
    characteristics: CharacteristicSection[];
    options: OptionSection[];
    angles: AngleOption[];
    can: { manageMedia: boolean };
};

export default function CatalogSeriesPage({
    series,
    library,
    trims,
    characteristics,
    options,
    angles,
    can,
}: SeriesPageProps) {
    const equipments = trims.reduce(
        (total, trim) => total + trim.equipments.length,
        0,
    );

    return (
        <>
            <Head title={`${series.title} — каталог`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <CatalogImage
                        src={series.image}
                        alt=""
                        className="aspect-[16/10] w-full shrink-0 rounded-xl bg-muted object-cover sm:w-56"
                    />
                    <div className="space-y-2">
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            {series.title}
                        </h1>
                        <div className="flex flex-wrap gap-1.5">
                            <Badge variant="secondary">
                                {series.generation.name}
                            </Badge>
                            {series.generation.years && (
                                <Badge variant="outline">
                                    {series.generation.years}
                                </Badge>
                            )}
                            <Badge variant="outline">
                                Цветов: {library.colors.length}
                            </Badge>
                            <Badge variant="outline">
                                Модификаций: {trims.length}
                            </Badge>
                            <Badge variant="outline">
                                Комплектаций: {equipments}
                            </Badge>
                        </div>
                    </div>
                </header>

                <Tabs defaultValue="photos" className="gap-6">
                    <TabsList>
                        <TabsTrigger value="photos">Фото</TabsTrigger>
                        <TabsTrigger value="trims">
                            Модификации и комплектации
                        </TabsTrigger>
                    </TabsList>
                    <TabsContent value="photos">
                        <SeriesMediaManager
                            seriesRef={series.ref}
                            library={library}
                            angles={angles}
                            canManage={can.manageMedia}
                        />
                    </TabsContent>
                    <TabsContent value="trims">
                        <TrimsExplorer
                            trims={trims}
                            options={options}
                            characteristics={characteristics}
                            emptyText="У этой серии пока нет модификаций в каталоге."
                        />
                    </TabsContent>
                </Tabs>
            </main>
        </>
    );
}

CatalogSeriesPage.layout = (props: SeriesPageProps) => ({
    breadcrumbs: [
        { title: 'Каталог автомобилей', href: index() },
        {
            title: props.series.mark.name,
            href: markRoute(props.series.mark.url),
        },
        {
            title: props.series.model.name,
            href: modelRoute({
                mark: props.series.mark.url,
                model: props.series.model.url,
            }),
        },
        { title: props.series.name, href: seriesRoute(props.series.ref) },
    ],
});
