import { Head, Link } from '@inertiajs/react';
import { Plug, Plus } from 'lucide-react';
import type { SiteBinding } from '@/components/integrations/binding-dialog';
import { BindingDialog } from '@/components/integrations/binding-dialog';
import type { MetricaSettings } from '@/components/integrations/metrica-settings';
import { MetricaSettingsCard } from '@/components/integrations/metrica-settings';
import type { Choice } from '@/components/platform/form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { designer } from '@/routes/sites';

type SiteIntegrationsProps = {
    site: { public_id: string; name: string };
    bindings: SiteBinding[];
    profiles: Choice[];
    analytics: MetricaSettings;
    can: { manage: boolean };
};

export default function SiteIntegrations({
    site,
    bindings,
    profiles,
    analytics,
    can,
}: SiteIntegrationsProps) {
    return (
        <>
            <Head title={`Интеграции сайта — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <p className="truncate text-sm text-muted-foreground">
                            {site.name}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Интеграции сайта
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Какие подключения рабочего пространства использует
                            этот сайт и с какими параметрами.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can.manage && profiles.length > 0 && (
                            <BindingDialog
                                sitePublicId={site.public_id}
                                profiles={profiles}
                                trigger={
                                    <Button>
                                        <Plus aria-hidden="true" />
                                        Подключить
                                    </Button>
                                }
                            />
                        )}
                        <Button asChild variant="outline">
                            <Link href={designer(site.public_id)}>
                                Открыть дизайнер
                            </Link>
                        </Button>
                    </div>
                </header>

                <section
                    aria-labelledby="bindings-heading"
                    className="flex flex-col gap-4"
                >
                    <h2 id="bindings-heading" className="text-lg font-semibold">
                        Подключения
                    </h2>
                    {bindings.length === 0 ? (
                        <Card className="border-dashed">
                            <CardHeader>
                                <h3 className="leading-none font-semibold">
                                    Сайт пока не использует подключения
                                </h3>
                                <CardDescription>
                                    {profiles.length === 0
                                        ? 'Сначала создайте подключение в разделе «Интеграции» рабочего пространства.'
                                        : 'Подключите CRM или вебхук, чтобы настроить передачу заявок из форм.'}
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    ) : (
                        <ul className="grid gap-4 lg:grid-cols-2">
                            {bindings.map((binding) => (
                                <li
                                    key={binding.public_id}
                                    className="min-w-0 rounded-xl border bg-card p-4 shadow-sm"
                                >
                                    <article
                                        aria-label={
                                            binding.name ?? binding.profile.name
                                        }
                                        className="flex flex-col gap-3"
                                    >
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Plug
                                                aria-hidden="true"
                                                className="size-4 text-muted-foreground"
                                            />
                                            <h3 className="font-semibold break-words">
                                                {binding.name ??
                                                    binding.profile.name}
                                            </h3>
                                            <Badge
                                                variant={
                                                    binding.status === 'active'
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {binding.status_label}
                                            </Badge>
                                            {binding.profile.status !==
                                                'active' && (
                                                <Badge variant="destructive">
                                                    {`Подключение: ${binding.profile.status_label.toLowerCase()}`}
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {`${binding.profile.name} · ${binding.profile.provider_type_label}`}
                                        </p>
                                        {binding.overrides.length > 0 && (
                                            <dl className="grid gap-x-4 gap-y-1 text-sm sm:grid-cols-[minmax(0,10rem)_minmax(0,1fr)]">
                                                {binding.overrides.map(
                                                    (row) => (
                                                        <div
                                                            key={row.key}
                                                            className="contents"
                                                        >
                                                            <dt className="font-mono text-muted-foreground">
                                                                {row.key}
                                                            </dt>
                                                            <dd className="break-all">
                                                                {row.value}
                                                            </dd>
                                                        </div>
                                                    ),
                                                )}
                                            </dl>
                                        )}
                                        {can.manage && (
                                            <div>
                                                <BindingDialog
                                                    sitePublicId={
                                                        site.public_id
                                                    }
                                                    profiles={profiles}
                                                    binding={binding}
                                                    trigger={
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            aria-label={`Изменить подключение ${binding.name ?? binding.profile.name}`}
                                                        >
                                                            Изменить
                                                        </Button>
                                                    }
                                                />
                                            </div>
                                        )}
                                    </article>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <MetricaSettingsCard
                    sitePublicId={site.public_id}
                    settings={analytics}
                    canManage={can.manage}
                />
            </main>
        </>
    );
}

SiteIntegrations.layout = {
    breadcrumbs: [{ title: 'Панель управления', href: dashboard() }],
};
