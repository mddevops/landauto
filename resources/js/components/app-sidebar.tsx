import { Link, usePage } from '@inertiajs/react';
import {
    Blocks,
    Car,
    CarFront,
    Code,
    Images,
    LayoutGrid,
    Plug,
    Settings,
    Users,
} from 'lucide-react';
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
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as integrationsIndex } from '@/routes/integrations';
import { index as assetsIndex } from '@/routes/workspace/assets';
import { index as platformBlocksIndex } from '@/routes/platform/blocks';
import { index as catalogIndex } from '@/routes/platform/catalog';
import { index as developersIndex } from '@/routes/platform/developers';
import { edit as editProfile } from '@/routes/profile';
import { edit as workspaceSettings } from '@/routes/workspace/settings';
import { index as teamIndex } from '@/routes/workspace/team';
import { index as vehicleLibraryIndex } from '@/routes/workspace/vehicles';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth, platform, workspace } = usePage().props;
    const isVerified = auth.user.email_verified_at !== null;
    const permissions = workspace.permissions;

    const workspaceItems: NavItem[] = [
        { title: 'Все сайты', href: dashboard(), icon: LayoutGrid },
        ...(permissions.includes('view_integrations')
            ? [
                  {
                      title: 'Интеграции',
                      href: integrationsIndex(),
                      icon: Plug,
                  },
              ]
            : []),
        ...(permissions.includes('manage_workspace_vehicle_library')
            ? [
                  {
                      title: 'Библиотека автомобилей',
                      href: vehicleLibraryIndex(),
                      icon: CarFront,
                      matchPrefix: true,
                  },
              ]
            : []),
        ...(permissions.includes('manage_workspace_assets')
            ? [{ title: 'Медиатека', href: assetsIndex(), icon: Images }]
            : []),
        ...(permissions.includes('manage_members')
            ? [{ title: 'Команда', href: teamIndex(), icon: Users }]
            : []),
    ];

    const settingsItems: NavItem[] = permissions.includes('edit_workspace')
        ? [
              {
                  title: 'Настройки пространства',
                  href: workspaceSettings(),
                  icon: Settings,
              },
          ]
        : [];

    const platformItems: NavItem[] = [
        ...(platform.permissions.includes('view_catalog')
            ? [
                  {
                      title: 'Каталог автомобилей',
                      href: catalogIndex(),
                      icon: Car,
                      matchPrefix: true,
                  },
              ]
            : []),
        ...(platform.permissions.includes('manage_platform_content')
            ? [
                  {
                      title: 'Блоки',
                      href: platformBlocksIndex(),
                      icon: Blocks,
                      matchPrefix: true,
                  },
              ]
            : []),
        ...(platform.permissions.includes('manage_developers')
            ? [
                  {
                      title: 'Разработчики',
                      href: developersIndex(),
                      icon: Code,
                  },
              ]
            : []),
    ];

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
                {isVerified && (
                    <nav
                        aria-label="Навигация по пространству"
                        className="flex flex-col gap-2"
                    >
                        <NavMain items={workspaceItems} />
                        {settingsItems.length > 0 && (
                            <>
                                <SidebarSeparator className="mx-0" />
                                <NavMain items={settingsItems} />
                            </>
                        )}
                        <NavMain items={platformItems} label="Платформа" />
                    </nav>
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
