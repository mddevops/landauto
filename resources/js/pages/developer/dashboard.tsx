import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Blocks, LayoutTemplate } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { dashboard } from '@/routes/developer';
import { index as blocksIndex } from '@/routes/developer/blocks';
import type { DeveloperPermission } from '@/types/platform';

type DeveloperDashboardProps = {
    profile: {
        public_id: string;
        display_name: string;
        slug: string;
        status_label: string;
        bio: string | null;
    };
    capabilities: Record<DeveloperPermission, boolean>;
};

function ToolPlaceholder({
    title,
    icon: Icon,
    text,
    allowed = false,
}: {
    title: string;
    icon: LucideIcon;
    text: string;
    allowed?: boolean;
}) {
    return (
        <section
            aria-label={title}
            data-allowed={allowed}
            className="space-y-2 rounded-xl border border-dashed p-4 text-muted-foreground"
        >
            <h2 className="flex items-center gap-2 font-semibold text-foreground">
                <Icon aria-hidden="true" className="size-4" />
                {title}
            </h2>
            <p className="text-sm">{text}</p>
        </section>
    );
}

export default function DeveloperDashboard({
    profile,
    capabilities,
}: DeveloperDashboardProps) {
    const hasAny = Object.values(capabilities).some(Boolean);

    return (
        <>
            <Head title="Панель разработчика" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Панель разработчика
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Профиль разработчика не связан с вашими пространствами и
                        не меняет доступ к ним.
                    </p>
                </header>

                <section
                    aria-labelledby="developer-profile-title"
                    className="space-y-3 rounded-xl border bg-card p-4 shadow-sm"
                >
                    <div className="flex flex-wrap items-center gap-2">
                        <h2
                            id="developer-profile-title"
                            className="min-w-0 font-semibold break-words"
                        >
                            {profile.display_name}
                        </h2>
                        <Badge>{profile.status_label}</Badge>
                    </div>
                    <dl className="grid gap-1 text-sm sm:grid-cols-[auto_1fr] sm:gap-x-4">
                        <dt className="text-muted-foreground">Slug</dt>
                        <dd className="break-all">
                            <code>{profile.slug}</code>
                        </dd>
                        <dt className="text-muted-foreground">Статус</dt>
                        <dd>{profile.status_label}</dd>
                        <dt className="text-muted-foreground">
                            Отправка на модерацию
                        </dt>
                        <dd>
                            {capabilities.submit_marketplace_item
                                ? 'Разрешена'
                                : 'Нет разрешения'}
                        </dd>
                    </dl>
                    {profile.bio && (
                        <p className="text-sm whitespace-pre-line">
                            {profile.bio}
                        </p>
                    )}
                </section>

                {!hasAny && (
                    <p
                        role="status"
                        className="rounded-xl border bg-muted/40 p-4 text-sm"
                    >
                        У вас пока нет разрешений на создание контента.
                        Обратитесь к администратору Landflow.
                    </p>
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    {capabilities.create_blocks ? (
                        <Link
                            href={blocksIndex()}
                            data-allowed="true"
                            className="group space-y-2 rounded-xl border bg-card p-4 shadow-sm transition-colors outline-none hover:bg-muted/50 focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <h2 className="flex items-center gap-2 font-semibold">
                                <Blocks aria-hidden="true" className="size-4" />
                                Мои блоки
                                <ArrowRight
                                    aria-hidden="true"
                                    className="ml-auto size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                                />
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Создавайте блоки и редактируйте их основные
                                данные.
                            </p>
                        </Link>
                    ) : (
                        <ToolPlaceholder
                            title="Мои блоки"
                            icon={Blocks}
                            text="Нет разрешения на создание блоков."
                        />
                    )}
                    <ToolPlaceholder
                        title="Мои шаблоны"
                        icon={LayoutTemplate}
                        text={
                            capabilities.create_templates
                                ? 'Доступ разрешён. Инструмент появится на следующем этапе.'
                                : 'Нет разрешения на создание шаблонов.'
                        }
                        allowed={capabilities.create_templates}
                    />
                </div>
            </main>
        </>
    );
}

DeveloperDashboard.layout = {
    breadcrumbs: [{ title: 'Панель разработчика', href: dashboard() }],
};
