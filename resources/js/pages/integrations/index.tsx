import { Head } from '@inertiajs/react';
import { Plug, Plus } from 'lucide-react';
import type {
    IntegrationChoices,
    IntegrationProfileRow,
} from '@/components/integrations/profile-dialog';
import { ProfileDialog } from '@/components/integrations/profile-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index as integrationsIndex } from '@/routes/integrations';

type IntegrationsIndexProps = {
    profiles: IntegrationProfileRow[];
    choices: IntegrationChoices;
    can: { manage: boolean };
};

export default function IntegrationsIndex({
    profiles,
    choices,
    can,
}: IntegrationsIndexProps) {
    return (
        <>
            <Head title="Интеграции" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Интеграции
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Подключения к CRM и внешним системам для всех сайтов
                            рабочего пространства.
                        </p>
                    </div>
                    {can.manage && (
                        <ProfileDialog
                            choices={choices}
                            trigger={
                                <Button>
                                    <Plus aria-hidden="true" />
                                    Добавить подключение
                                </Button>
                            }
                        />
                    )}
                </header>

                {profiles.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader>
                            <h2 className="leading-none font-semibold">
                                Подключений пока нет
                            </h2>
                            <CardDescription>
                                Добавьте вебхук или API своей CRM, чтобы
                                передавать заявки с сайтов.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <ul className="grid gap-4 lg:grid-cols-2">
                        {profiles.map((profile) => (
                            <li
                                key={profile.public_id}
                                className="min-w-0 rounded-xl border bg-card p-4 shadow-sm"
                            >
                                <article
                                    aria-label={profile.name}
                                    className="flex flex-col gap-3"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Plug
                                            aria-hidden="true"
                                            className="size-4 text-muted-foreground"
                                        />
                                        <h2 className="font-semibold break-words">
                                            {profile.name}
                                        </h2>
                                        <Badge
                                            variant={
                                                profile.status === 'active'
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {profile.status_label}
                                        </Badge>
                                    </div>
                                    <dl className="grid gap-x-4 gap-y-1 text-sm sm:grid-cols-[minmax(0,10rem)_minmax(0,1fr)]">
                                        <dt className="text-muted-foreground">
                                            Тип
                                        </dt>
                                        <dd>{profile.provider_type_label}</dd>
                                        <dt className="text-muted-foreground">
                                            Адрес
                                        </dt>
                                        <dd className="break-all">
                                            {profile.base_url ?? '—'}
                                        </dd>
                                        <dt className="text-muted-foreground">
                                            Авторизация
                                        </dt>
                                        <dd>{profile.auth_type_label}</dd>
                                        {profile.credentials_mask && (
                                            <>
                                                <dt className="text-muted-foreground">
                                                    Секрет
                                                </dt>
                                                <dd className="font-mono">
                                                    {profile.credentials_mask}
                                                </dd>
                                            </>
                                        )}
                                    </dl>
                                    {can.manage && (
                                        <div>
                                            <ProfileDialog
                                                choices={choices}
                                                profile={profile}
                                                trigger={
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        aria-label={`Изменить подключение ${profile.name}`}
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
            </main>
        </>
    );
}

IntegrationsIndex.layout = {
    breadcrumbs: [
        { title: 'Панель управления', href: dashboard() },
        { title: 'Интеграции', href: integrationsIndex() },
    ],
};
