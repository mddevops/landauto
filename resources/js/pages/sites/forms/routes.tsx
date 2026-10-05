import { Head, Link } from '@inertiajs/react';
import { Mail, Plus, Send } from 'lucide-react';
import type {
    BindingChoice,
    FormRouteRow,
    RouteChoices,
    RouteField,
} from '@/components/integrations/route-dialog';
import { RouteDialog } from '@/components/integrations/route-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { show as formShow } from '@/routes/sites/forms';

type FormRoutesProps = {
    site: { public_id: string; name: string };
    form: { public_id: string; name: string };
    fields: RouteField[];
    routes: FormRouteRow[];
    bindings: BindingChoice[];
    choices: RouteChoices;
};

export default function FormRoutes({
    site,
    form,
    fields,
    routes,
    bindings,
    choices,
}: FormRoutesProps) {
    const dialogProps = {
        sitePublicId: site.public_id,
        formPublicId: form.public_id,
        fields,
        bindings,
        choices,
    };

    return (
        <>
            <Head title={`Передача заявок — ${form.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <p className="truncate text-sm text-muted-foreground">
                            <Link
                                href={formShow({
                                    site: site.public_id,
                                    form: form.public_id,
                                })}
                                className="underline-offset-4 hover:underline"
                            >
                                {`${site.name} · ${form.name}`}
                            </Link>
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Передача заявок
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Заявки из опубликованного сайта передаются по всем
                            активным маршрутам. Тестовые заявки из предпросмотра
                            никуда не передаются.
                        </p>
                    </div>
                    <RouteDialog
                        {...dialogProps}
                        trigger={
                            <Button>
                                <Plus aria-hidden="true" />
                                Добавить маршрут
                            </Button>
                        }
                    />
                </header>

                {routes.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader>
                            <h2 className="leading-none font-semibold">
                                Маршрутов пока нет
                            </h2>
                            <CardDescription>
                                Заявки сохраняются в разделе «Заявки». Добавьте
                                почту или CRM, чтобы получать их сразу.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <ul className="grid gap-4 lg:grid-cols-2">
                        {routes.map((route) => (
                            <li
                                key={route.public_id}
                                className="min-w-0 rounded-xl border bg-card p-4 shadow-sm"
                            >
                                <article
                                    aria-label={route.name}
                                    className="flex flex-col gap-3"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        {route.destination_type === 'email' ? (
                                            <Mail
                                                aria-hidden="true"
                                                className="size-4 text-muted-foreground"
                                            />
                                        ) : (
                                            <Send
                                                aria-hidden="true"
                                                className="size-4 text-muted-foreground"
                                            />
                                        )}
                                        <h2 className="font-semibold break-words">
                                            {route.name}
                                        </h2>
                                        <Badge variant="outline">
                                            {route.destination_label}
                                        </Badge>
                                        <Badge
                                            variant={
                                                route.status === 'active'
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {route.status_label}
                                        </Badge>
                                    </div>
                                    <p className="text-sm break-all text-muted-foreground">
                                        {route.destination_type === 'email'
                                            ? route.recipients.join(', ')
                                            : `${route.method} ${route.binding?.base_url ?? ''}${route.path}`}
                                    </p>
                                    <div>
                                        <RouteDialog
                                            {...dialogProps}
                                            route={route}
                                            trigger={
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    aria-label={`Изменить маршрут ${route.name}`}
                                                >
                                                    Изменить
                                                </Button>
                                            }
                                        />
                                    </div>
                                </article>
                            </li>
                        ))}
                    </ul>
                )}
            </main>
        </>
    );
}

FormRoutes.layout = {
    breadcrumbs: [{ title: 'Панель управления', href: dashboard() }],
};
