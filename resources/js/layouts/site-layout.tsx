import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { SiteSidebar } from '@/components/site-sidebar';
import type { AppLayoutProps } from '@/types';

export default function SiteLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { siteContext } = usePage().props;

    return (
        <AppShell variant="sidebar">
            {siteContext ? <SiteSidebar site={siteContext} /> : <AppSidebar />}
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
