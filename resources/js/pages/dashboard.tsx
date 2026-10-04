import { Head, Link, usePage } from '@inertiajs/react';
import { Archive, Car, CircleCheck, PencilRuler, Plus } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { create as createSite, designer } from '@/routes/sites';
import { index as vehicles } from '@/routes/sites/vehicles';

type SiteSummary = {
    public_id: string;
    name: string;
    status: 'active' | 'archived';
};

type DashboardProps = {
    currentWorkspace: {
        public_id: string;
        name: string;
    };
    sites: SiteSummary[];
    canViewSites: boolean;
    canViewVehicles: boolean;
    canCreateSites: boolean;
    siteLimit: {
        active: number;
        max: number;
        reached: boolean;
    };
};

const statusLabels: Record<SiteSummary['status'], string> = {
    active: 'Активен',
    archived: 'В архиве',
};

export default function Dashboard({
    currentWorkspace,
    sites,
    canViewSites,
    canViewVehicles,
    canCreateSites,
    siteLimit,
}: DashboardProps) {
    const createDescriptionId = 'create-site-description';
    const { url } = usePage();
    const createdSitePublicId = new URLSearchParams(
        url.split('?')[1] ?? '',
    ).get('site');
    const createdSite = sites.find(
        (site) => site.public_id === createdSitePublicId,
    );

    return (
        <>
            <Head title="Панель управления" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <p className="text-sm text-muted-foreground">
                            Рабочее пространство
                        </p>
                        <h1 className="truncate text-2xl font-semibold tracking-tight sm:text-3xl">
                            {currentWorkspace.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Сайты текущего рабочего пространства
                        </p>
                    </div>

                    {canCreateSites && (
                        <div className="flex flex-col items-start gap-1 sm:items-end">
                            {siteLimit.reached ? (
                                <>
                                    <Button
                                        type="button"
                                        disabled
                                        aria-describedby={createDescriptionId}
                                    >
                                        <Plus aria-hidden="true" />
                                        Создать сайт
                                    </Button>
                                    <p
                                        id={createDescriptionId}
                                        className="max-w-xs text-xs text-muted-foreground sm:text-right"
                                    >
                                        {`Достигнут лимит активных сайтов: ${siteLimit.active} из ${siteLimit.max}.`}
                                    </p>
                                </>
                            ) : (
                                <Button asChild>
                                    <Link href={createSite()}>
                                        <Plus aria-hidden="true" />
                                        Создать сайт
                                    </Link>
                                </Button>
                            )}
                        </div>
                    )}
                </header>

                {createdSite && (
                    <Alert>
                        <CircleCheck aria-hidden="true" />
                        <AlertTitle>Сайт создан</AlertTitle>
                        <AlertDescription>
                            {`«${createdSite.name}» добавлен в рабочее пространство.`}
                        </AlertDescription>
                    </Alert>
                )}

                {!canViewSites ? (
                    <Card>
                        <CardHeader>
                            <h2 className="leading-none font-semibold">
                                Нет доступа к сайтам
                            </h2>
                            <CardDescription>
                                Для просмотра сайтов требуется соответствующее
                                разрешение рабочего пространства.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : sites.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader className="items-start sm:items-center sm:text-center">
                            <h2 className="leading-none font-semibold">
                                Здесь пока нет сайтов
                            </h2>
                            <CardDescription className="max-w-lg">
                                Создайте первый сайт для этого рабочего
                                пространства. На следующем шаге можно будет
                                выбрать подходящий шаблон.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <section
                        aria-labelledby="sites-heading"
                        className="space-y-4"
                    >
                        <div className="flex items-end justify-between gap-4">
                            <div>
                                <h2
                                    id="sites-heading"
                                    className="text-xl font-semibold"
                                >
                                    Сайты
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Всего: {sites.length}
                                </p>
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {sites.map((site) => (
                                <Card
                                    key={site.public_id}
                                    className={cn(
                                        'min-w-0',
                                        site.public_id ===
                                            createdSite?.public_id &&
                                            'border-primary ring-2 ring-primary/20',
                                    )}
                                >
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-3">
                                            <h3 className="min-w-0 text-lg leading-snug font-semibold break-words">
                                                {site.name}
                                            </h3>
                                            <Badge
                                                variant={
                                                    site.status === 'active'
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {site.status === 'active' ? (
                                                    <CircleCheck aria-hidden="true" />
                                                ) : (
                                                    <Archive aria-hidden="true" />
                                                )}
                                                {statusLabels[site.status]}
                                            </Badge>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="flex flex-wrap gap-2">
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                href={designer(site.public_id)}
                                                aria-label={`Открыть дизайнер сайта «${site.name}»`}
                                            >
                                                <PencilRuler aria-hidden="true" />
                                                Открыть дизайнер
                                            </Link>
                                        </Button>
                                        {canViewVehicles && (
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link
                                                    href={vehicles(
                                                        site.public_id,
                                                    )}
                                                    aria-label={`Автомобили сайта «${site.name}»`}
                                                >
                                                    <Car aria-hidden="true" />
                                                    Автомобили
                                                </Link>
                                            </Button>
                                        )}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </section>
                )}
            </main>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Панель управления',
            href: dashboard(),
        },
    ],
};
