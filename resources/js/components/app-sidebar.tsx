import { Link, usePage } from '@inertiajs/react';
import { Car, LayoutGrid } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { WorkspaceSwitcher } from '@/components/workspace-switcher';
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
import { index as catalogIndex } from '@/routes/platform/catalog';
import { edit as editProfile } from '@/routes/profile';
import type { Auth, NavItem } from '@/types';
import type { PlatformContext } from '@/types/platform';

const mainNavItems: NavItem[] = [
    {
        title: 'Панель управления',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const catalogNavItem: NavItem = {
    title: 'Каталог автомобилей',
    href: catalogIndex(),
    icon: Car,
};

export function AppSidebar() {
    const { auth, platform } = usePage<{
        auth: Auth;
        platform?: PlatformContext;
    }>().props;
    const isVerified = auth.user.email_verified_at !== null;
    const navItems = platform?.permissions.includes('view_catalog')
        ? [...mainNavItems, catalogNavItem]
        : mainNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link
                                href={isVerified ? dashboard() : editProfile()}
                                prefetch
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                {isVerified && <WorkspaceSwitcher />}
            </SidebarHeader>

            <SidebarContent>
                {isVerified && <NavMain items={navItems} />}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
