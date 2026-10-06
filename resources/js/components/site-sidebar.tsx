import { Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Car,
    Eye,
    FileText,
    Globe,
    Inbox,
    LayoutDashboard,
    PencilRuler,
    Plug,
    Rocket,
    Search,
    Send,
    ShieldCheck,
    SquareStack,
} from 'lucide-react';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { designer, preview, show } from '@/routes/sites';
import { index as deliveries } from '@/routes/sites/deliveries';
import { index as domains } from '@/routes/sites/domains';
import { show as formSecurity } from '@/routes/sites/form-security';
import { index as forms } from '@/routes/sites/forms';
import { index as integrations } from '@/routes/sites/integrations';
import { index as popups } from '@/routes/sites/popups';
import { show as publishing } from '@/routes/sites/publishing';
import { index as seo } from '@/routes/sites/seo';
import { index as submissions } from '@/routes/sites/submissions';
import { index as vehicles } from '@/routes/sites/vehicles';
import type { NavItem, SiteContext } from '@/types';

function visible(items: Array<NavItem | false>): NavItem[] {
    return items.filter((item): item is NavItem => item !== false);
}

export function SiteSidebar({ site }: { site: SiteContext }) {
    const id = site.public_id;
    const can = site.can;

    const general = visible([
        { title: 'Общее', href: show(id), icon: LayoutDashboard },
        { title: 'Дизайнер', href: designer(id), icon: PencilRuler },
        can.preview && { title: 'Предпросмотр', href: preview(id), icon: Eye },
    ]);

    const content = visible([
        can.viewVehicles && {
            title: 'Автомобили',
            href: vehicles(id),
            icon: Car,
            matchPrefix: true,
        },
        { title: 'Формы', href: forms(id), icon: FileText, matchPrefix: true },
        { title: 'Попапы', href: popups(id), icon: SquareStack },
        can.editSeo && { title: 'SEO', href: seo(id), icon: Search },
    ]);

    const leads = visible([
        can.viewSubmissions && {
            title: 'Заявки',
            href: submissions(id),
            icon: Inbox,
        },
        can.viewDeliveryLogs && {
            title: 'Доставка заявок',
            href: deliveries(id),
            icon: Send,
        },
    ]);

    const settings = visible([
        can.viewIntegrations && {
            title: 'Интеграции',
            href: integrations(id),
            icon: Plug,
        },
        can.editForms && {
            title: 'Защита форм',
            href: formSecurity(id),
            icon: ShieldCheck,
        },
        (can.publish || can.manageDomains) && {
            title: 'Публикация',
            href: publishing(id),
            icon: Rocket,
        },
        can.manageDomains && {
            title: 'Домены',
            href: domains(id),
            icon: Globe,
        },
    ]);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild tooltip="Все сайты">
                            <Link href={dashboard()} prefetch>
                                <ArrowLeft aria-hidden="true" />
                                <span>Все сайты</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <div
                            className="flex min-w-0 items-center gap-2 px-2 py-1.5 group-data-[collapsible=icon]:hidden"
                            data-testid="site-shell-name"
                        >
                            <div className="flex aspect-square size-8 shrink-0 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                                <Globe className="size-4" aria-hidden="true" />
                            </div>
                            <div className="grid min-w-0 leading-tight">
                                <span className="text-xs text-muted-foreground">
                                    Сайт
                                </span>
                                <span className="truncate font-semibold">
                                    {site.name}
                                </span>
                            </div>
                        </div>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <nav
                    aria-label={`Разделы сайта «${site.name}»`}
                    className="flex flex-col gap-2"
                >
                    <NavMain items={general} label="Общее" />
                    <NavMain items={content} label="Контент" />
                    <NavMain items={leads} label="Заявки" />
                    <NavMain items={settings} label="Настройки" />
                </nav>
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
