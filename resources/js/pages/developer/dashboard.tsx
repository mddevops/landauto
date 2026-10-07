import { Head } from '@inertiajs/react';
import { Blocks, LayoutTemplate } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { dashboard } from '@/routes/developer';

type DeveloperDashboardProps = {
    profile: {
        public_id: string;
        display_name: string;
        slug: string;
        status_label: string;
        bio: string | null;
    };
};

const upcoming = [
    {
        title: 'Мои блоки',
        icon: Blocks,
        text: 'Создание блоков будет доступно на следующем этапе.',
    },
    {
        title: 'Мои шаблоны',
        icon: LayoutTemplate,
        text: 'Создание шаблонов будет доступно на следующем этапе.',
    },
];

export default function DeveloperDashboard({
    profile,
}: DeveloperDashboardProps) {
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
                    </dl>
                    {profile.bio && (
                        <p className="text-sm whitespace-pre-line">
                            {profile.bio}
                        </p>
                    )}
                </section>

                <div className="grid gap-4 sm:grid-cols-2">
                    {upcoming.map((item) => (
                        <section
                            key={item.title}
                            aria-label={item.title}
                            className="space-y-2 rounded-xl border border-dashed p-4 text-muted-foreground"
                        >
                            <h2 className="flex items-center gap-2 font-semibold text-foreground">
                                <item.icon
                                    aria-hidden="true"
                                    className="size-4"
                                />
                                {item.title}
                            </h2>
                            <p className="text-sm">{item.text}</p>
                        </section>
                    ))}
                </div>
            </main>
        </>
    );
}

DeveloperDashboard.layout = {
    breadcrumbs: [{ title: 'Панель разработчика', href: dashboard() }],
};
